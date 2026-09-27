# Memoria del Agente (general + hilo) — Design

**Fecha:** 2026-09-27
**Autor:** sesión opencode (aprobado por admin@tikkix2.space)
**Estado:** para revisión
**Alcance:** memoria explícita del asistente en el chat (global por usuario + por hilo), con tools de escritura del agente y edición manual en UI.

## Problema

- El agente no retiene nada entre turnos salvo el historial crudo del SDK: `agent_conversation_messages` (últimos 100 mensajes) es toda su "memoria". No hay hechos ni preferencias persistentes que crucen hilos.
- Skills ≠ memoria: las Skills (`skills.instructions`) son procedimientos reutilizables que se cargan on-demand; no hay lugar para "el usuario prefiere X" o "su objetivo es Y".
- Sin visibilidad ni control: no existe forma de ver, editar, promover ni borrar lo que el agente recuerda.

## Decisiones aprobadas (brainstorming)

| # | Decisión |
|---|---|
| 1 | Sistema de memoria **nuevo** con dos ámbitos: `global` (por usuario, cruza hilos) y `thread` (por hilo de chat). |
| 2 | Escritura: **tools del agente** (`remember`/`forget`/`promote`) + **edición manual** desde la UI. Sin extracción automática. |
| 3 | Lectura: **inyección completa con presupuesto de caracteres** en el system prompt. Sin embeddings ni retrieval en v1. |
| 4 | Ciclo de vida: la memoria del hilo está **atada al hilo** (FK cascade) y se puede **promover a general**. |
| 5 | UI: **página dedicada "Memoria"** en el sidebar (grupo Asistente IA) + **chip de acceso desde el hilo** del chat. |
| 6 | Modelo: **tabla única `memories`** con `scope` (enfoque A). Promover = cambiar scope + `thread_id = null`. |
| 7 | Límites: **500 chars** por entrada, **100 globales**, **50 por hilo**. |

## Diseño

### 1. Datos

Migración `create_memories_table`:

| Columna | Tipo | Notas |
|---|---|---|
| `id` | uuid PK | uuid7, patrón chat/skills |
| `user_id` | FK `users` → cascade | |
| `scope` | string(16) | `global` \| `thread` |
| `thread_id` | uuid nullable, FK `agent_conversations` → **cascade on delete** | requerido si `scope=thread` |
| `content` | text | máx 500 chars (validado) |
| `source` | string(16) | `agent` \| `user` |
| `content_hash` | char(64) | sha256 del contenido normalizado (dedup) |
| `metadata` | json nullable | `origin_message_id`, `promoted_at` |
| `created_at`/`updated_at` | timestamps | orden por `updated_at desc` |

Índices: `(user_id, scope)` y `(thread_id)`. El dedup se resuelve en app (no hay unique con `thread_id` nullable porque los NULL no colisionan en SQL); la consistencia `scope=thread ⇒ thread_id` se garantiza en catálogo + Form Request.

- **Enum** `App\Ai\Enums\MemoryScope: string { case Global = 'global'; case Thread = 'thread'; }` (patrón `app/Integrations/Enums`).
- **Modelo** `App\Models\Memory`: `$guarded = []`, uuid7 en `booted()` (patrón `Skill`), `casts()` → `scope => MemoryScope`, `metadata => array`. Relaciones `user()`, `thread()`. Scopes `forUser`, `global`, `forThread`.
- **Factory** `MemoryFactory` con estados `global()` / `forThread(ChatThread)`.

### 2. Catálogo

`App\Ai\Memory\MemoryCatalog` (patrón `SkillCatalog`):

```php
const MAX_CONTENT = 500;
const MAX_GLOBAL = 100;
const MAX_THREAD = 50;
const INJECTION_BUDGET = 8000; // chars totales en el system prompt

public function blockFor(User $user, ?ChatThread $thread): ?string;
public function remember(User $user, string $content, MemoryScope $scope, ?ChatThread $thread, string $source = 'agent'): Memory;
public function update(Memory $memory, string $content): Memory;
public function forget(Memory $memory): void;
public function promote(Memory $memory): Memory;          // thread → global
public function candidatesFor(User $user, ?ChatThread $thread, string $query): Collection;
```

- **Normalización/hash:** `hash('sha256', Str::lower(trim(preg_replace('/\s+/', ' ', $content'))))`.
- **Dedup en `remember`:** si ya existe una memoria del mismo usuario + ámbito + hilo con el mismo hash, actualiza el contenido/`updated_at` en vez de duplicar.
- **`update`:** recalcula hash; si colisiona con **otra** entrada, `ValidationException` ("ya existe una memoria igual").
- **Caps:** al alcanzar `MAX_GLOBAL`/`MAX_THREAD`, `remember` lanza `ValidationException` con mensaje accionable (el tool la convierte en texto para el modelo).
- **`blockFor`:** arma los bloques de inyección con orden `updated_at desc`, global primero y luego el hilo; respeta `INJECTION_BUDGET` (se corta por memoria completa, no a mitad de línea) y agrega `(+N memorias no mostradas)` si recortó. Devuelve `null` si no hay nada.

### 3. Escritura — tools del agente

Tres tools nuevos en `App\Ai\Tools\`, construidos con el hilo cuando existe:

- **`RememberMemoryTool(User, MemoryCatalog, ?ChatThread)`**
  - `content` (string, required), `scope` (enum `global|thread`, required).
  - Guardar: preferencias, objetivos y hechos estables del usuario → `global`; detalles situacionales del proyecto/conversación → `thread`.
  - `scope=thread` sin hilo → devuelve texto de error (los one-shot solo pueden guardar general).
  - Errores de validación/cap se devuelven como string (nunca excepción hacia el modelo).
- **`ForgetMemoryTool(User, MemoryCatalog, ?ChatThread)`**
  - `id` (string, opcional) o `query` (string, opcional). `id` preferido.
  - `query`: busca por contenido normalizado en las memorias visibles (general + hilo actual). 1 coincidencia → borra; varias → devuelve candidatos con id y contenido; 0 → mensaje.
- **`PromoteMemoryTool(User, MemoryCatalog)`**
  - `id` required; solo memorias de hilo → general. Devuelve confirmación.

**Registro y disponibilidad:** grupo `memory` → label `"Memoria"` en `ToolCatalog::groups()` + `toolsFor(User $user, array $groups, ?ChatThread $thread = null)` para inyectar el hilo en el constructor. El `ToolsPicker` del composer lo lista automáticamente y `prepareToolPolicy` ya valida contra `allGroups()`.

- **Modo auto (default):** `ChatService::normalizeToolPolicy` agrega `memory` siempre a los grupos que devuelve `ToolRouter` (la memoria debe acumularse pasivamente; si dependiera de keywords, casi ningún turno tendría las tools). No se tocan `config/ai_tools.php`.
- **Modo manual:** manda la selección del usuario; si desmarca "Memoria", el hilo no lee ni escribe memoria (kill switch).

**Guía al modelo** (se agrega a las instrucciones cuando el grupo está activo): entradas cortas de una sola idea; preferir actualizar/olvidar antes que duplicar; nunca guardar credenciales, secretos ni datos de pago; no guardar datos efímeros; escribir en el idioma del usuario.

### 4. Lectura — inyección al prompt

- **`MegalomaniacAgent::instructions()`**: si el grupo `memory` está activo (`in_array('*') || in_array('memory')`, mismo patrón que `skillsToolEnabled()`), agrega el bloque de `MemoryCatalog::blockFor($this->user, $this->thread)` + la guía. Regla única: grupo activo ⇒ se lee y se puede escribir; grupo ausente ⇒ ni inyección ni tools. En auto el grupo siempre está activo; en manual lo decide el usuario desde el ToolsPicker (default encendido).
- **Formato** (español, consistente con el bloque de skills):

```text
Memoria general del usuario:
- [<id>] <contenido>

Memoria de este hilo:
- [<id>] <contenido>
```

  Los ids viajan para que `forget`/`promote` puedan referenciar entradas sin ambigüedad.
- **`RuntimeAgent::instructions()`**: agrega solo el bloque **general** (lectura), sin tools de memoria. La memoria "en general" también aplica a los agentes programados del usuario.
- **One-shots** (`InsightService`, `AiInsightController`, `AiFitnessController`): usan `MegalomaniacAgent` con `forUser()` y sin hilo → inyectan solo la general, sin cambios extra.
- Sin tool `recall`: las memorias ya viajan en el prompt (determinista, debuggable).

### 5. Ciclo de vida, errores y seguridad

- **Borrar hilo:** el FK cascade elimina las memorias del hilo dentro de `ChatService::deleteThread()`. El diálogo de borrado del chat agrega el aviso "y N memorias del hilo"; `ChatThreadResource` expone `memories_count` (`withCount('memories')` en `ChatController@show`; `null` en los listados del rail, donde no se necesita).
- **Promover:** setea `scope=global`, `thread_id=null`, `metadata.promoted_at` y conserva `source`/contenido; `updated_at` se bumpea (pasa al tope por recencia).
- **Autorización:** `App\Policies\MemoryPolicy` (view/update/delete por `user_id`, `denyAsNotFound` como `ChatThreadPolicy`); `thread_id` validado como perteneciente al usuario en `StoreMemoryRequest`.
- **Validación:** `StoreMemoryRequest` (`content` required|string|max:500, `scope` required|in:global,thread, `thread_id` nullable|uuid|exists + owner cuando `scope=thread`); `UpdateMemoryRequest` (`content` required|max:500).
- **Contadores UI:** la página muestra `N / 100` y `N / 50`; el form se bloquea con mensaje al llenar el ámbito.
- **Privacidad:** el contenido de memoria nunca se loggea; borrar una memoria no toca el historial del chat; borrar historial no toca las memorias (son capas independientes).

### 6. UI

- **Rutas** (grupo AI de `routes/web.php`): `GET ai/memory` (`ai.memory.index`), `POST ai/memory` (`ai.memory.store`), `PATCH ai/memory/{memory}` (`ai.memory.update`), `DELETE ai/memory/{memory}` (`ai.memory.destroy`), `POST ai/memory/{memory}/promote` (`ai.memory.promote`) → `App\Http\Controllers\Ai\MemoryController`.
- **Sidebar:** ítem "Memoria" (icono `BrainCircuit` de lucide) en el grupo Asistente IA, después de "Fuentes".
- **Página** `resources/js/pages/ai/memory.tsx` + tipos en `resources/js/types/memory.ts`:
  - Header con título y descripción corta; segmented **General | Por hilo**; contador `N/max` por ámbito; buscador client-side.
  - Form "Nueva memoria" (textarea con contador 500) y **edición inline** por fila (textarea + guardar/cancelar).
  - Fila: contenido, badge de fuente (`Agente`/`Usuario`), fecha relativa, acciones: **promover** (solo ámbito hilo), **borrar** (confirm).
  - Ámbito hilo: selector de hilo con búsqueda; `?thread=<uuid>` preselecciona (permite deep-link desde el chat); empty states para "sin memorias generales", "hilo sin memorias" y "sin hilos".
  - Estados obligatorios (anti-slop): loading/skeleton, vacío, error de validación, overflow de contenido largo (500 chars), confirmaciones destructivas.
- **Chat:** chip `Memoria · N` en el header del hilo (`resources/js/pages/ai/thread.tsx`) que linkea a `/ai/memory?thread={id}`; el diálogo de borrado del hilo incluye el conteo de memorias.
- **Diseño fino:** usar las skills **ui-radar** (referencias reales de gestores de memoria/CRUD antes de dibujar) y **anti-ui-slop** (gap de estados + gate de finish) durante la implementación; tokens Ember existentes (`bg-card`, `border`, `primary`) y patrones de `settings/skills.tsx`.

### 7. Testing

Pest en `tests/Feature/Ai/`:

- `MemoryCatalogTest`: crear, dedup por hash (actualiza, no duplica), caps 100/50, hash normalizado, `update` con colisión, promote, orden por recencia.
- `MemoryToolsTest`: `remember` global/hilo, thread sin hilo → error string, cap → error string; `forget` por id, por query única, ambigua (candidatos) y sin match; `promote`.
- `MemoryInjectionTest`: `MegalomaniacAgent` incluye bloques con orden y presupuesto, recorte con nota; grupo `memory` apagado → sin bloque y sin tools; sin hilo → solo general; `RuntimeAgent` incluye general.
- `ChatToolsPolicyTest` (extender): modo auto siempre incluye `memory`; modo manual con exclusión no lo incluye.
- `MemoryPageTest`: index props/contadores, store/update/destroy/promote, validaciones, política (usuario ajeno → 404), cascade al borrar hilo + `memories_count`.
- Frontend: `npm run types` + smoke Playwright MCP (crear, editar, promover, borrar, chip desde el hilo).

## No objetivos

- Extracción automática post-turno / destilación de conversaciones (solo tools + manual).
- Embeddings, retrieval semántico o knowledge graph para memorias.
- Inspector/editor del historial crudo del SDK (se descartó en brainstorming).
- Tools de memoria para agentes programados (`RuntimeAgent` solo lee la general).
- Compartir memoria entre usuarios.
- Compactar el historial del chat en memorias.

## Riesgos

- **Prompt creep:** mitigado con caps (500 chars, 100/50) + presupuesto de inyección (8.000 chars) + kill switch por hilo.
- **Duplicados semánticos** (paráfrasis, no hash exacto): mitigado con la guía al modelo y la edición/promoción manual; el dedup semántico queda como mejora futura.
- **Borrado accidental:** cascade con confirmación que informa el conteo.
