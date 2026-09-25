# Kanban: columnas configurables, popup de detalle y descripción por IA — Diseño

> Fecha: 2026-09-22 · Estado: aprobado · Alcance: tableros Kanban Personal y Freelance

## Problema

1. El detalle de tarea vive en un `Sheet` lateral (Personal) o no existe (Freelance). Se pide un popup centrado con edición completa.
2. La descripción no se puede generar con IA a partir de un prompt.
3. Las columnas Kanban están hardcodeadas (3 en cada tablero, con normalizaciones divergentes). Se piden columnas configurables según necesidad del usuario.

## Decisiones aprobadas

| # | Decisión |
|---|---|
| 1 | Aplica a **ambos tableros** (Personal y Freelance). |
| 2 | Columnas **por proyecto en ambos**; en Personal multi-proyecto: set default del usuario + override por proyecto. |
| 3 | **Flag `is_done` por columna**; progreso/dashboards/insights lo usan en vez de comparar strings. |
| 4 | Provider IA **por usuario** (URL/key/model de Settings → IA) con fallback al default del server. |
| 5 | IA **reemplaza el editor** (con confirmación si ya había texto); el usuario revisa y guarda manualmente. |
| 6 | **Popup de edición completa**; reemplaza el Sheet. La página `/personal/tasks/{id}` se mantiene como deep link. |
| 7 | Vista "Todos" = default ∪ columnas extra presentes; reorden por flechas; `is_done` único (Cancelada no cuenta como completada). |

## Modelo de datos

### Nueva tabla `task_board_columns`

```
id, user_id (FK, cascade), project_id (FK nullable, cascade),
key (string 50, slug), label (string 80), color (string 20),
sort_order (int, default 0), is_done (bool, default false),
timestamps
unique (user_id, project_id, key)
```

- `project_id = null` ⇒ set default del usuario (Personal "Todos" y tareas sin proyecto).
- `key` es lo que se persiste en `project_tasks.status`. Renombrar `label` no cambia `key`.

### `project_tasks`

- Nueva columna `is_done` (bool, default false, indexada) — desnormalización para que dashboards/progreso consulten sin joins.
- Se mantiene en 2 puntos: cambio de status de una tarea y toggle de `is_done` de una columna (bulk update de sus tareas).

### Seeds y normalización

- Al crear un proyecto: 3 columnas. Personal: `Pending / In Progress / Done`. Freelance: `To Do / In Progress / Done` (keys actuales, cero churn en tests).
- Set default por usuario: mismas 3 columnas con `project_id = null`.
- Migración normaliza los statuses existentes (`Pending`, `To Do`, `In Progress`, `in_progress`, `Done`) a la key de la columna correspondiente y calcula `is_done` inicial.

## Backend

### Servicio `TaskBoardColumnService`

- `defaultColumnsFor(User): Collection` (lazy-create si no existen).
- `columnsFor(?Project $project, User $user)` → columnas del proyecto o default.
- `seedFor(Project)`.
- `propagateIsDone(TaskBoardColumn)` → bulk update `project_tasks.is_done` para las tareas con ese `status` en el scope de la columna.
- `doneStatusKeys(?Project, User): array` (fallback si `is_done` no está backfilleado).

### CRUD

`TaskBoardColumnController` + rutas `task-board-columns`:
- `POST` crear (project_id opcional, label, color, is_done) → `key` autogenerado del label (slug único en scope).
- `PATCH /{column}` renombrar/color/is_done/sort_order.
- `PATCH /reorder` (ordered_ids).
- `DELETE /{column}?move_to={key}` → si tiene tareas, `move_to` es obligatorio; mueve tareas y reindexa; luego elimina.
- Autorización: `column.user_id === auth()->id()` y, si tiene proyecto, `project.user_id === auth()->id()`.

### Semántica `is_done`

Reemplazar comparaciones hardcodeadas por `is_done`/columnas en:
- `Project::getProgressAttribute`
- `FreelanceDashboardController` (pending_tasks, upcoming)
- `Ai\Services\SuggestionService` (overdue)
- `Ai\Services\InsightService` (tasks)
- `ProjectTask::scopePending` (redefinido con `is_done`)
- Defaults de MCP tools y API V1 (`'Pending'`/`'To Do'` → primera columna del scope)
- `move()` valida que `status` exista en las columnas del scope; setea `is_done` según la columna.
- `update()` (Personal y Freelance) devuelve JSON si `expectsJson()`.

## Frontend

### `TaskDetailDialog` (nuevo, compartido)

- `Dialog` centrado (Radix ya instalado), estilo tokens (`bg-card`, `border-border`), densidad compacta.
- Campos: título, descripción (Yoopta + sección IA), columna (select dinámico), prioridad, `due_date`, `start_date`, `estimated_time`, tags; slot para propiedades personalizadas (Personal) y responsable/área (Freelance).
- Acciones: Guardar (fetch PATCH JSON + actualización optimista del board, sin recarga), Cancelar, Eliminar.
- Estados: loading, error, disabled, focus visible; sin clipping en mobile (`max-h` + scroll interno).
- Reemplaza el `Sheet` de `personal/tasks/Index.tsx`. La página Show se mantiene.

### Columnas UI

- Tile "+ Añadir columna" al final del grid.
- Menú `⋯` por columna: Renombrar, Color (paleta de tokens), "Cuenta como completada", Mover ←/→, Eliminar.
- Eliminar con tareas → diálogo con select de columna destino.
- Personal "Todos": default ∪ columnas extra presentes (read-only para las extra).
- Drag & drop entre columnas custom (mismo `ordered_ids`).

### IA UI (dentro del popup)

- Textarea de prompt + botón "Generar" con loading.
- `ai_enabled=false` → estado disabled con link a Settings → IA.
- Error del endpoint → mensaje inline.
- Al generar: confirmación si el editor ya tenía texto; remount del editor con `key` (el editor Yoopta no reacciona a `value` externo).

## IA backend

- `AiProviderResolver`: si `ai_enabled` y `ai_provider_url` + `ai_provider_key` seteados → `provider: 'user'`, `model: ai_model ?: 'gpt-4o-mini'`; si no → `provider: null` (default del server) y `model: null`.
- Aplicar el resolver a los call sites existentes y al nuevo endpoint.
- `TaskDescriptionAgent`: sin tools, instrucciones en español, devuelve solo markdown.
- `MarkdownToYoopta`: convierte `# heading`, `- bullet`, párrafos → bloques Yoopta (`{id, type, children:[{text}]}`). Testeable con Pest.
- `POST /ai/generate-task-description` `{prompt, title, context}` → `{description: [blocks], message}`.

## Testing

- **Pest**: resolver (con/sin config), conversor (headings/bullets/párrafos/vacío), endpoint (fake + disabled), CRUD columnas (403, validaciones, destino obligatorio, reorder), seed al crear proyecto, normalización de migración, propagación `is_done`, move valida columna, tests existentes adaptados.
- **Playwright** (`:8010`): popup abre/edita/guarda sin recarga, click-vs-drag, añadir/renombrar/reordenar/eliminar columna con destino, drag entre columnas custom, UI IA con error controlado sin provider.
- **Lint/format**: `vendor/bin/pint --dirty`, `npx eslint`, `npx tsc --noEmit`, `npm run build`.

## Riesgos

- Provider real requiere que el usuario configure URL/key en Settings → IA (o `.env`); en tests se usa fake.
- Statuses mixtos existentes (`in_progress`, `To Do`) — la migración los normaliza.
- `is_done` desnormalizado: mantener en 2 puntos (status y flag de columna).
- Editor Yoopta no reacciona a `value` externo → remount con `key`.
- Click vs drag en tarjetas → guard anti-click post-drag + `stopPropagation` en controles.
- Rutas freelance `create/edit/show` apuntan a métodos inexistentes → `except([...])`.

## Fuera de alcance (YAGNI)

Drag horizontal para reordenar columnas, WIP limits, permisos por columna, columnas compartidas entre usuarios, eliminar la página Show personal.
