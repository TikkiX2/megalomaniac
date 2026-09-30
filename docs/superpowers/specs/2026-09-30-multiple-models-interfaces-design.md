# Diseño: Varios modelos y proveedores — resolver por scope, fallback con health, prompts compuestos y asistentes por módulo

- **Fecha:** 2026-09-30
- **Estado:** aprobado en conversación (secciones 1–5)
- **Path:** arquitectónico → implementación vía `writing-plans`

## 1. Problema y estado actual

Hoy la app tiene **un solo proveedor por usuario** (BYO): `Settings → IA` guarda
`users.ai_provider_url` + `ai_provider_key` + `ai_model` + `ai_embeddings_model`.
`AiProviderResolver::for()` resuelve ese único par y **todo** lo consume igual:
chat global, agentes programados (`AgentRunner`), insights por módulo
(`AiInsightController`: workout, finance, nutrition, grocery, tasks) y
embeddings del feed digest.

El prompt del agente es **hardcodeado** en `MegalomaniacAgent::instructions()`
(base + bloques por módulo + skills + memoria), sin capa editable.

El app ya tiene esqueleto de "secciones" que este diseño reutiliza: rutas de
insights por módulo, `ChatThread::category` (chat de salud categorizado) y
grupos de herramientas por módulo.

## 2. Objetivos

1. **Varios proveedores** configurables a la vez por usuario (cada uno con URL
   openai-compatible, key cifrada y modelo).
2. **Asignación jerárquica** de proveedor por scope: `superficie > módulo > global`.
3. **Fallback con failover por salud**: cadena ordenada por scope + circuit
   breaker persistente por proveedor (backoff exponencial).
4. **Prompt personalizable** en 3 capas editables (global / módulo / superficie)
   compuestas sobre la base del código.
5. **UI nueva**:
   - `Settings → IA` rediseñada en 3 pestañas (Proveedores, Asignaciones, Prompts).
   - **7 paneles de asistente por módulo** con hilos propios:
     gimnasio, nutrición, grocery, finanzas, freelance, salud, personas.
   - El chat global (`/ai/chat`, `module=null`) sigue funcionando y resuelve su
     proveedor por la misma jerarquía.

### No objetivos (YAGNI, esta iteración)

- Tracking de costo/tokens por mensaje.
- Drivers no openai-compatible (Anthropic/Gemini reales): la columna `protocol`
  es future-proofing, hoy solo soporta `openai-compatible`.
- Memoria por módulo (hoy la memoria es `global` / `thread`).
- Endpoint "testear todos los proveedores".
- API pública de proveedores (solo web Inertia).

## 3. Modelo de datos

Convenciones del repo: migraciones anónimas, `$table->id()`,
`foreignId()->constrained()->cascadeOnDelete()`, casts en método `casts()`.

### 3.1 `ai_providers`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint | |
| `user_id` | FK users cascade | |
| `name` | string(80) | único por usuario |
| `protocol` | string(30) | enum `AiProtocol`; hoy solo `openai_compatible` |
| `url` | text | base URL **sin** `/chat/completions` |
| `key` | text | cast `encrypted` (como `users.ai_provider_key` hoy) |
| `model` | string(100) | |
| `embeddings_model` | string(100) nullable | hereda `users.ai_embeddings_model` en la migración |
| `enabled` | boolean default true | |
| `sort_order` | integer default 0 | orden de la UI; **no** afecta cadenas explícitas |

Índices: `unique(user_id, name)`, `index(user_id, enabled, sort_order)`.

### 3.2 `ai_scopes`

Una fila por scope; junta **cadena de fallback** y **capa de prompt**.

- `id`, `user_id` FK cascade
- `scope` string(30) — enum `AiScope` (13 valores):
  - `global`
  - superficies: `surface:chat`, `surface:agents`, `surface:insights`,
    `surface:feed`, `surface:embeddings`
  - módulos: `module:gym`, `module:nutrition`, `module:grocery`,
    `module:finance`, `module:freelance`, `module:health`, `module:people`
- `provider_chain` json nullable — ids de `ai_providers` en orden
  (primario → backups). `null`/`[]` = **heredar del scope de arriba**.
- `prompt` text nullable — capa editable de ese scope (ver §5).
- `unique(user_id, scope)`

Las 13 filas **no se crean al migrar**: se crean a demanda cuando el usuario
edita un scope. El resolver usa defaults de código cuando no existe la fila.

### 3.3 `ai_provider_health`

Circuit breaker persistente por proveedor.

- `id`, `user_id` FK cascade, `provider_id` FK `ai_providers` cascade
- `consecutive_failures` int unsigned default 0
- `last_success_at` timestamp nullable
- `last_failure_at` timestamp nullable
- `broken_until` timestamp nullable
- `last_error` string(255) nullable
- `unique(user_id, provider_id)`

Regla de salud:

- Éxito → `consecutive_failures = 0`, `broken_until = null`.
- Falla → `consecutive_failures += 1`; al llegar a **3** →
  `broken_until = now + min(2^consecutive_failures, 1440)` minutos
  (backoff idéntico al existente en `AgentRunner`).
- El provider está "fuera de circuito" mientras `broken_until > now`.

### 3.4 `agent_conversations.module`

`string(30) nullable` + index sobre `agent_conversations` (la tabla de
conversaciones de laravel/ai, `ChatThread` la extiende). Marca el módulo del
hilo; `null` = chat general.

El chat de salud existente (con `category='salud'` + contexto + tools pinned)
**no se toca**; el scope `module:health` se mapea a esos hilos vía
`category='salud'` para no partir el historial. El resto de módulos usa la
columna nueva.

### 3.5 Data migration

- El BYO actual de cada user con `ai_provider_url` + `ai_provider_key` pasa a
  un provider `name='Principal'` (`protocol='openai_compatible'`,
  `embeddings_model = users.ai_embeddings_model`) y a una fila
  `ai_scopes.global` con `provider_chain = [id]`.
- Las columnas `users.ai_provider_url`, `ai_provider_key`, `ai_model`,
  `ai_embeddings_model`, `ai_enabled` **se conservan** como legacy (el código
  deja de usarlas en esta feature; su retiro es una migración posterior).
- `users.ai_enabled` sigue siendo el switch maestro de habilitación de IA.

## 4. Resolver + Executor

### 4.1 `AiScopeResolver`

Reemplaza a `AiProviderResolver` (que deja de existir; el config provider
dinámico `'user'` se retira).

- Firma: `resolve(User $user, AiScope $scope, ?string $sessionId): AiResolution`
- `AiResolution` (value object) expone:
  - `primary`: provider + modelo (o null si no hay ningún proveedor usable)
  - `chain`: lista ordenada provider + modelo (todos los attempts posibles)
  - `promptLayers`: las capas de prompt a componer (global → módulo → superficie)
  - `effective`: cadena real después del filtro de salud (lo que la UI muestra)
- Jerarquía: para un scope `surface:X` dentro de un módulo `Y` se consulta
  `surface:X` → `module:Y` → `global`. **El primer scope con
  `provider_chain` no vacía gana la cadena completa** (no hay merge de
  cadenas entre niveles).
- Sin ninguna fila con cadena: cadena implícita = todos los providers
  `enabled` ordenados por `sort_order` (el caso "tengo N providers y no toqué
  asignaciones" funciona desde el día uno).
- **Health filter**: se excluyen providers `enabled=false` o
  `broken_until > now`. Si **todos** los candidatos están fuera de circuito →
  *half-open*: se usa el de `broken_until` más cercano (sonda y resucita).
- Caching: las filas de `ai_scopes` y `ai_provider_health` se leen **una vez
  por request por usuario** (static), no por intento.

### 4.2 `AiProviderConfigurator`

- `wire(AiProvider $provider, ?string $sessionId)`: escribe
  `url` / `key` / headers en `config('ai.providers.pm{id}')` — naming dinámico
  estable por provider (generaliza el `'user'` actual).
- Headers: `User-Agent: megalomaniac-pro/1.0`; si la URL es OpenCode Go
  (`opencode.ai` / `*.opencode.ai`) se agrega `x-opencode-session` con el mismo
  valor estable que hoy (thread id o `user-{id}`). El mismo thread puede
  continuar en otro proveedor tras un fallback sin romper la sesión.

### 4.3 `AiRequestExecutor`

- `execute(AiResolution $resolution, callable $attempt)` donde
  `$attempt(string $providerKey, string $model)` hace el prompt/stream de
  laravel/ai con ese provider.
- **Errores que disparan fallback**: 400, 401, 402, 403, 408, 429, 5xx,
  timeout y connection errors. (Un 400 "modelo no existe" en A justifica
  probar B; las cadenas son cortas y las define el usuario.)
- **Límite de reintento**: solo fallback **antes del primer chunk** del
  stream. Si el stream ya emitió contenido y falla a mitad, **no** se
  reintenta: el error se devuelve tal cual (el usuario ya vio respuesta
  parcial). En `prompt()` no-streaming se reintenta la llamada completa.
- **Health**: cada intento real registra éxito/falla en `ai_provider_health`
  (reglas de §3.3). El último error va en `last_error` (limitado a 255).
- **Cadena exhausta** → lanza `AiAllProvidersFailedException`
  (`App\Ai\Support`) con los errores por proveedor. Los callers (chat,
  `AgentRunner`, insights, digest) la convierten en un mensaje honesto para el
  usuario y `report($e)`.

### 4.4 Callers

| Caller | Scope de resolución | Notas |
|---|---|---|
| `ChatService` (send/regenerate/edit/approve) | `surface:chat` + `module:{thread.module}` | threads de salud usan `category` para el módulo |
| `AgentRunner` | `surface:agents` | mantiene su backoff de ejecución; el health queda por debajo |
| `AiInsightController` | `surface:insights` + módulo del endpoint | workout→gym, finance→finance, nutrition→nutrition, grocery→grocery, tasks→freelance |
| Feed digest / embeddings | `surface:embeddings` (embeddings) y `surface:feed` (digest) | embeddings usa `embeddings_model` del provider resuelto |

## 5. Composición del prompt

`AiPromptComposer::for(User $user, AiScope $scope, ?string $module): string`

Orden de composición:

1. **Base del código** — la instrucción core de `MegalomaniacAgent`
   (comportamiento, herramientas, aprobaciones, seguridad). No editable desde
   UI.
2. **Capas editables** (de `ai_scopes.prompt`), en orden
   `global` → `module:{módulo}` → `surface:{superficie}`, cada una con su
   separador:

   ```
   ## Personalización global
   {texto}

   ## Personalización: Gimnasio
   {texto}

   ## Personalización: Chat
   {texto}
   ```

   Capa vacía → se omite. No se seedea contenido: el estado inicial es solo la
   base del código (igual que hoy).
3. **Contexto de runtime** (datos del módulo, memoria, skills, documentos del
   hilo) — inyectado por el código después de las capas, como hoy.

Reglas:

- El bloque de personalización se inserta **después** de la base y **antes** de
  los bloques de herramientas/skills, para que las instrucciones del usuario
  actúen como guía principal (precedente existente: "El usuario pidió seguir
  estas skills…").
- Límite de **8.000 caracteres por capa** (validación de Form Request).
- **Vista previa**: la UI del tab Prompts muestra el prompt **final compuesto**
  del scope seleccionado (construido server-side y pasado como prop).
- `AgentDefinition.instructions` (agentes programados) **no cambia**: su
  prompt ya es editable por agente; solo su proveedor/fallback pasa por el
  resolver.
- El hilo pasa su `module` explícito al agente (hoy el contexto de módulo se
  deriva del mensaje/herramientas).

## 6. UI

Dirección visual: **extender el sistema de diseño existente** (tokens
`bg-background` / `bg-card` / `border-border`, primary ember,
`material-symbols-outlined`, cards `rounded-xl`), no inventar estética nueva.
La distinción la pone el contenido del producto (cadenas, salud, prompt
compuesto). Componentes reutilizados de `components/ui`: `tabs`, `select`,
`table`, `badge`, `sheet`, `textarea`, `skeleton`, `tooltip`.

### 6.1 `Settings → IA` (rediseño, 3 pestañas)

Fuera de las pestañas (sección permanente arriba de las tabs):

- Switch maestro `ai_enabled` (como hoy).
- Key de Tavily (búsqueda web) — card pequeña, misma UX de hoy.

**Pestaña «Proveedores»** (acción principal: *tener un proveedor que funcione*):

- Tabla: nombre · protocolo · modelo · URL truncada · enabled · **badge de
  salud** (`OK` / `N fallas` / `caído hasta HH:MM`) · acciones.
- Acciones por fila:
  - **Probar conexión** → endpoint nuevo (`POST /settings/ai/providers/{id}/test`):
    prompt mínimo contra el proveedor; devuelve ok/fallo + latencia. Badge de
    resultado inline con spinner mientras corre.
  - **Editar** → `Sheet` lateral con el formulario actual (URL, key enmascarada
    "•••• guardada", modelo, embeddings model).
  - Habilitar/deshabilitar, eliminar.
- Estado vacío: "Todavía no tenés proveedores" + CTA «Agregar proveedor».
- Un solo proveedor = solo crea filas de provider; la asignación sigue
  heredando.

**Pestaña «Asignaciones»** (patrón de model router):

- Tabla jerárquica:
  - Fila **Global**.
  - Sección **Superficies**: chat, agentes, insights, feed, embeddings.
  - Sección **Módulos**: gimnasio, nutrición, grocery, finanzas, freelance,
    salud, personas.
- Cada fila:
  - Selector de **primario** + chips de **backups** (agregables, reordenables
    con arriba/abajo, quitables).
  - Estado "hereda de ↑" cuando la fila no define cadena.
  - **Cadena efectiva resuelta**: "activo: X → Y" calculada con el health
    filter (lo que el resolver devolvería hoy).
- Persiste al escribir en `ai_scopes` (crear/actualizar fila del scope).

**Pestaña «Prompts»**:

- Select de scope (global / 7 módulos / 4 superficies de prompt: chat,
  agentes, insights, feed — `surface:embeddings` no expone prompt).
- Textarea con contador `0/8000`.
- **Vista previa colapsable** con el prompt final compuesto del scope
  seleccionado.
- Acción "Vaciar capa" por scope.

### 6.2 Asistentes por módulo (7)

- Rutas: `/ai/gym`, `/ai/finance`, `/ai/health`, `/ai/freelance`,
  `/ai/nutrition`, `/ai/grocery`, `/ai/people`.
- Cada página es un **wrapper fino** de la superficie de chat existente
  (`/ai/chat` se mantiene como chat general, `module=null`):
  - lista de hilos filtrada al módulo (`agent_conversations.module`; para
    salud, `category='salud'`),
  - scope `module:{key}` en el resolver,
  - capa de prompt del módulo compuesta.
- **Header del panel**: badge de provider resuelto
  (ej. "OpenCode Go · gpt-4o-mini") + indicator de fallback cuando la última
  respuesta corrió en un backup ("usó fallback: 2.º proveedor").
- **Entry points**:
  - Botón «Asistente IA» en el header de cada página de módulo.
  - Sección «IA» en el sidebar: Chat + los 7 módulos (8 ítems).
  - El botón flotante sigue yendo a `/ai/chat`.
- **Estados**:
  - Streaming: como hoy.
  - Vacío: 3 chips de sugerencia relevantes al módulo (ej. gym: "¿Cómo va mi
    semana?", "Sugerí un ejercicio para pecho", "Compará mi último PR").
  - Error total: "Todos los proveedores fallaron" + botón reintentar +
    link a `Settings → IA`.

## 7. Rollout

Todo en la misma feature; el camino viejo no queda a medio camino.

1. **Migraciones**: crear `ai_providers`, `ai_scopes`, `ai_provider_health`;
   agregar `agent_conversations.module`.
2. **Data migration**: BYO → provider `Principal` + fila `ai_scopes.global`.
3. **Backend**:
   - `App\Ai\Support`: `AiScopeResolver`, `AiRequestExecutor`,
     `AiPromptComposer`, `AiProviderConfigurator`,
     `AiAllProvidersFailedException`.
   - Enums `AiProtocol`, `AiScope`.
   - Modelos `AiProvider`, `AiScope`, `AiProviderHealth` (+ factories).
   - `AiSettingsController` ampliado: CRUD de providers, asignaciones,
     prompts, test de conexión.
   - Rutas: settings de IA + 7 páginas de módulo.
   - `ChatService`, `AgentRunner`, `AiInsightController` y feed digest pasan
     por el executor; se retira el uso directo del `AiProviderResolver` legacy
     y el config provider `'user'`.
4. **Frontend**:
   - `settings/ai.tsx` con 3 tabs (cards `ai_enabled` + Tavily fuera de tabs).
   - Páginas `/ai/{module}`; chat general sin cambios de ruta.
   - Badge de provider + indicator de fallback en el header del chat.
   - Sidebar: sección IA de 8 ítems; botones de asistente en headers de
     módulo.
5. **QA**: agregar las 7 rutas de módulo y la `Settings → IA` rediseñada al
   crawl diario Playwright (`docs/qa`).

## 8. Testing (Pest)

- **Feature — providers**: CRUD con scoping por usuario (no se ve/toca
  providers ajenos); validación de nombre/url/key/modelo; test de conexión con
  `Http::fake` (éxito y fallo).
- **Feature — resolver**: prioridad `surface > module > global`; cadena vacía
  hereda; cadena implícita por `sort_order` sin filas; half-open cuando todos
  están caídos; provider deshabilitado excluido.
- **Feature — executor**: provider A responde 401 → B responde (fallback);
  cadena exhausta → `AiAllProvidersFailedException` y el chat muestra el
  mensaje honesto; error **mid-stream** no reintenta; health: 3 fallas →
  `broken_until` set, éxito → reset.
- **Feature — prompts**: composición de 3 capas en orden; capa vacía omitida;
  límite 8.000 chars rechaza; la vista previa iguala el string enviado.
- **Feature — hilos por módulo**: `POST /ai/gym` crea hilo `module='gym'`; la
  lista de `/ai/gym` solo trae hilos del módulo; salud sigue por
  `category='salud'`.
- **Feature — data migration**: user con BYO → provider `Principal` + scope
  global con cadena; user sin BYO → sin providers.
- **Regresión**: el suite actual de chat/agentes mantiene verde.
- **Smoke**: crawl Playwright de las 7 rutas de módulo + `Settings → IA`.

## 9. Riesgos y decisiones

- **Config dinámico por intento**: `pm{id}` evita colisiones y es estable por
  provider; el detalle OpenCode Go (header de sesión) queda dentro del
  configurador, no en el caller.
- **Half-open**: solo 1 intento de sonda por resolver; si falla de nuevo el
  backoff sigue su curso. No hay "semáforo" por concurrent requests (app
  personal de un usuario; no es un problema real).
- **Salud y chat de salud**: el módulo `health` reutiliza `category` y no
  `module`; documentado en el composer y en la lista de hilos para no
  duplicar historial.
- **Fallback de embeddings**: el scope `surface:embeddings` usa el mismo
  mechanism; si el provider resuelto no tiene `embeddings_model` se omite
  embeddings (comportamiento actual: "leave empty to skip").
