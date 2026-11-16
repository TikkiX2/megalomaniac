# Diario — Fase 2: candado y reflexión (diseño)

**Fecha:** 2026-11-16
**Estado:** pendiente de aprobación
**Base:** Fase 1 mergeada en `main` (`f183e53`), spec `2026-11-15-diario-design.md`

## Intención

Cerrar las dos promesas que la Fase 1 dejó abiertas: que "privado" también signifique **no visible sin contraseña** (además de cifrado en reposo), y que el diario **devuelva algo**: una reflexión semanal escrita por la IA a partir de lo público.

El candado protege al dueño de una mirada ajena sobre la pantalla o la sesión abierta. La reflexión no es un chatbot: es un texto corto, guardado, que se puede releer y que el chat puede pedir.

## Objetivos

- Que el texto de una memoria privada **nunca viaje al cliente** mientras el diario está bloqueado.
- Desbloquear una vez y que valga 15 minutos para día, timeline, repaso y fotos.
- Generar y guardar un resumen semanal con temas, ánimo, destacados y una pregunta.
- Que el chat pueda pedir el resumen con una tool de sólo lectura/generación, sin acceso a memorias sueltas.
- Reusar la cadena de proveedores, los fallbacks y los mensajes honestos que ya existen.

## No objetivos

- Preguntas libres del chat sobre memorias concretas ("¿qué hice en marzo?").
- Herramientas que escriban o editen memorias.
- Cifrado de imágenes o de metadata (fecha, ánimo, etiquetas, personas siguen en claro).
- Passphrase zero-knowledge.
- Compartir, exportar, multiusuario.
- Recordatorios o notificaciones.

## Anti-objetivos

- Un candado cosmético que oculte con CSS y mande el texto igual.
- Un resumen que invente momentos que no están en los datos.
- Un chat que lea memorias privadas "porque el usuario está desbloqueado" hasta que él lo pida explícitamente en una fase futura.
- Métricas con puntaje o gamificación.

## Restricciones

- Laravel 12, Inertia v2, React 19, Tailwind v4, Wayfinder, Pest 4, Postgres.
- Sin dependencias nuevas. `laravel/ai` y `spatie/laravel-medialibrary` ya están.
- Todo bajo `middleware(['auth','verified'])`; todo scoped por `user_id`.
- Los tests corren con SQLite en memoria; el código no puede depender de SQL de Postgres.
- UI en castellano, tokens Ember, targets ≥44px, foco visible.
- `APP_KEY` sigue siendo la clave del cifrado en reposo.

## Enfoque elegido

- **Candado:** sesión propia de 15 minutos (`journal_unlocked_until`), validación con `Hash::check` y saneamiento de props en el servidor. Se descartó Fortify `password.confirm` porque su timeout es global y su flujo de redirect no encaja con un desbloqueo inline.
- **IA:** `JournalReflectionService` sobre `AiInsightRunner` + `MegalomaniacAgent`, con fallback determinístico y persistencia semanal. Se descartó un agente dedicado porque duplicaría la resolución de proveedores y los fallbacks.
- **Tool del chat:** lee la reflexión guardada de la semana y, si no existe, la genera en el momento. No recibe memorias ni devuelve texto privado.

## Candado

### Sesión

Clave de sesión `journal_unlocked_until` (timestamp Unix). TTL 900 segundos.

`App\Services\Journal\JournalAccess`:

- `unlocked(Request $request): bool` — `(int) $request->session()->get('journal_unlocked_until', 0) > now()->timestamp`.
- `unlock(Request $request): void` — guarda `now()->addSeconds(900)->timestamp`.
- `lock(Request $request): void` — olvida la clave.
- `expiresAt(Request $request): ?CarbonImmutable` — para mostrar el contador.

El logout invalida la sesión y con ella el desbloqueo. No hay "recordarme" entre sesiones.

### Ruta

`POST /diario/desbloquear` → `Journal\UnlockController@store`, nombre `journal.unlock`, `throttle:5,1`.

- Valida `password` requerido.
- `Hash::check($password, $request->user()->password)`; si falla, error de validación en `password` con "Contraseña incorrecta.".
- Éxito: `JournalAccess::unlock()` + redirect `back()` + flash "Diario desbloqueado por 15 minutos.".
- Invitado no llega: `auth`.

### Saneamiento (el punto crítico)

Mientras está bloqueado, el controlador **no envía** el contenido privado. No se oculta con CSS.

| Superficie | Bloqueado |
|---|---|
| Día privado | `body: null`, `locked: true` |
| Momento privado | `title: null`, `body: null`, `people: []`, `photos: []`, `locked: true`; se conserva `occurred_at` e `is_private` |
| Timeline | día sin contenido público: `excerpt: null`, `photo_url: null`, `locked: true` |
| Repaso y búsqueda | sin cambios: ya excluyen privadas |
| Calendario | sin cambios: nunca muestra texto |
| Foto de momento privado | `423 Locked` si el diario está bloqueado; `200` si está desbloqueado y es del dueño |

Mutaciones sobre una memoria privada (`PUT /diario/{date}`, `PATCH|DELETE /diario/momentos/{moment}`) también exigen desbloqueo: por Inertia redirigen `back()` con un error en `password` ("Desbloqueá el diario para editar esta memoria.") y devuelven `423` sólo a peticiones que no aceptan HTML. Las públicas se editan sin desbloquear.

Prop adicional en las páginas: `locked: bool` y `unlock_expires_at: string|null`.

### UI

- `resources/js/components/journal/LockedNotice.tsx`: card con candado, texto "Hay memorias privadas", input de contraseña, botón "Desbloquear 15 min" (`min-h-11`), error inline, link "¿Olvidaste tu contraseña?" a `/forgot-password`.
- Se muestra en el día cuando hay al menos una memoria privada y `locked`; en `Todas` como banner cuando hay días bloqueados.
- Al desbloquear: `router.reload()` y el contenido aparece.
- `MomentCard` y el editor muestran un candado y "Privado — desbloqueá para leer" en lugar del texto.
- Fotos privadas bloqueadas: celda con candado en vez de imagen; no se pide la URL.

## Reflexión semanal

### Modelo

`journal_reflections`:

| Columna | Tipo |
|---|---|
| `id` | bigint PK |
| `user_id` | FK users, cascade |
| `week_start` | date, lunes de la semana local |
| `body` | text |
| `source` | string: `ai` \| `fallback` |
| `model` | string nullable |
| `generated_at` | timestamp |
| timestamps | |
| | `unique(user_id, week_start)` |

La reflexión no se cifra: se construye sólo con memorias públicas y no contiene texto privado. Aun así, el prompt no incluye memorias privadas ni sus metadatos.

### Servicio

`App\Services\Journal\JournalReflectionService`:

- `weekStart(User $user): CarbonImmutable` — lunes de la semana local actual.
- `forWeek(User $user, CarbonImmutable $weekStart): ?JournalReflection`.
- `recap(User $user, ?string $weekStart = null): ?JournalReflection` — devuelve la guardada o la genera.
- `generate(User $user, CarbonImmutable $weekStart): JournalReflection` — arma payload público, llama a la IA, cae a fallback, guarda con `updateOrCreate`.
- `publicPayload(User $user, CarbonImmutable $weekStart): array{text: string, days: int, moods: array<int, int>}` — días con `is_private = false` y momentos públicos; cuerpos truncados a 500 caracteres por día y 200 por momento; **máximo 7000 caracteres** en total; etiquetas y nombres de personas incluidos sólo si la memoria es pública.

Reglas:

- `! $user->ai_enabled` → fallback directo, sin llamar al runner.
- `AiInsightRunner` con módulo `journal`, scope `surface:insights`; `MegalomaniacAgent($user)->forUser($user)->withPersonalization($block)->prompt($prompt, provider: $key, model: $model)`.
- Cadena vacía o agotada → fallback, con `source = 'fallback'`.
- La generación se hace con `throttle:3,1` en la ruta.

Prompt (castellano, fijo):

> Sos un asistente que ayuda a mirar la semana, no un terapeuta. Con las memorias públicas de esta semana, devolvé exactamente cuatro bloques cortos: (1) dos o tres temas que se repiten, (2) cómo se movió el ánimo, (3) dos o tres momentos destacados citando la fecha, (4) una pregunta abierta para la semana que viene. Máximo 250 palabras. No inventes nada que no esté en los datos; si la semana tiene poco, decilo.

Fallback determinístico (sin IA):

- Días escritos y racha.
- Ánimo promedio con su etiqueta (Bajo…Muy bien) y cuántos días lo registraron.
- Hasta 3 etiquetas más repetidas.
- Hasta 3 momentos públicos, con fecha y primer renglón.
- Cierre: "Sin IA configurada; este resumen se armó con tus datos."

### Rutas

- `GET /diario/repaso` suma props `weekStart`, `reflections` (últimas 8, descendente) y `reflection` (la de la semana actual si existe).
- `POST /diario/reflexiones` → `Journal\ReflectionController@store`, nombre `journal.reflections.store`, body `week_start` opcional (`Y-m-d`, lunes, dentro de las últimas 8 semanas), `throttle:3,1`.
- Regenerar una semana existente la sobrescribe (misma fila).

### UI

- Sección "Reflexión semanal" arriba de "Tal día como hoy" en `Repaso`.
- Estado vacío: explicación de una línea + botón "Resumir esta semana" (`min-h-11`).
- Con reflexión: texto en `whitespace-pre-line` (`text-[15px] leading-relaxed`), badge "IA · {modelo}" o "Sin IA", fecha de generación, botón "Regenerar" con confirmación.
- Historial: las últimas 8 en un acordeón, con la semana como `10–16 nov`.
- Si la generación falla por proveedor, el fallback igual se guarda y la UI muestra "Sin IA" (nunca un 500).

### Tool del chat

`App\Ai\Tools\JournalRecapTool implements Tool`:

- Constructor `(User $user, JournalReflectionService $reflections)`.
- `description(): string` — "Devuelve la reflexión semanal del diario. Sólo lee memorias públicas; si la semana no tiene resumen, lo genera. No accede a memorias privadas."
- Schema: `week_start` opcional (string `Y-m-d`, lunes).
- `handle(Request $request)` — valida la semana (lunes, últimas 8), llama a `recap()` y devuelve texto plano con la semana y el cuerpo; si no hay datos públicos, "No hay memorias públicas en esa semana."
- Registro: grupo `journal` en `ToolCatalog::groups()` (label "Diario"), caso en `make()` y keywords en `config/ai_tools.php` (`diario`, `memoria`, `memorias`, `recap`, `reflexion`, `reflexión`).
- No es `Approvable`: no escribe memorias ni sale de la app.

## Tests

- Candado: contraseña incorrecta deja error; correcta setea sesión; a los 901 s vuelve a bloquear; 6º intento en un minuto da 429; logout limpia.
- Props: día/momento privado bloqueado no expone body/title/fotos y marca `locked`; desbloqueado los expone.
- Fotos: privada bloqueada → 423; desbloqueada → 200; ajena → 404 siempre.
- Mutaciones: editar/borrar privada bloqueada → redirect con error en `password` (y 423 sin HTML); pública → 200.
- Reflexión: se guarda una por semana; regenerar sobrescribe; el payload sólo incluye memorias públicas (test con una privada que no debe aparecer); sin IA usa fallback con `source=fallback`; con IA mockeada usa `source=ai` y guarda el modelo.
- Tool: devuelve la guardada; genera si falta; rechaza una semana que no es lunes; no filtra privadas.
- UI HTTP: Repaso renderiza la sección y el estado vacío; el POST redirect con flash.

## Criterios de aceptación

- Con el diario bloqueado, ninguna respuesta HTTP contiene el texto de una memoria privada (props ni HTML).
- A los 15 minutos el candado vuelve solo.
- La reflexión se guarda una vez por semana y se puede regenerar.
- El prompt de la IA se arma sólo con memorias públicas (test que falla si se cuela una privada).
- El chat puede pedir el resumen sin aprobación y sin acceso a memorias sueltas.
- Suite completa en verde, `npm run types` y `npm run build` limpios.
- Sin clipping ni controles inertes en 1280 y 375 (inspección visual; en esta máquina no hay navegador, se documenta el límite).

## Decisiones

- Sesión propia de 15 min sobre Fortify `password.confirm`.
- Un desbloqueo cubre día, timeline, repaso, edición y fotos.
- La búsqueda sigue excluyendo privadas aun desbloqueado: no se hace `LIKE` sobre ciphertext ni se descifra toda la tabla.
- El candado se aplica en el servidor; el cliente sólo recibe placeholders.
- `423 Locked` para fotos y mutaciones privadas bloqueadas (más honesto que 403/404 y distinguible de "no existe").
- La reflexión no se cifra porque sólo usa datos públicos, pero se guarda por usuario y semana.
- La tool genera si falta (el usuario ya pidió el resumen al preguntar).
- Semana ISO local, lunes a domingo, en la zona del usuario.

## Riesgos

- El desbloqueo en sesión no protege de alguien con la sesión abierta y la contraseña; es una barrera, no cifrado zero-knowledge.
- El costo de IA por regeneración: se limita a 3 por minuto y a 8 semanas de historial visible.
- Si `APP_KEY` cambia, las privadas quedan ilegibles aunque se desbloquee (ya documentado en Fase 1).
- La reflexión puede ser superficial en semanas con poco contenido; el prompt obliga a decirlo.
- Las fotos privadas siguen en disco sin cifrar; el candado protege el acceso por la app, no el disco.
