# Spec — Cartas por proyecto con filtros con memoria (Semana, Mañana, Hoy)

- **Fecha:** 2026-10-11
- **Aprobación:** diseño presentado en chat y aprobado con "Procede". Decisiones del usuario: filtros con las **4 dimensiones** (prioridad, estado, etiquetas, tipo de proyecto) + filtro de proyecto; **memoria en servidor** con vista reservada; **Mañana incluida** en el alcance.
- **Investigación:** `anti-ui-slop` (playbook operate: patrones familiares, agrupar con spacing/bordes mínimos, estados completos) + `ui-radar` (UIZZE: Asana "My Tasks" = secciones colapsables con fila de chips de filtro; Planny "Inbox" = filas de 1 línea con metadatos inline). Las referencias son iOS: se toman los patrones, no el look (producto Ember dark).

## 1. Objetivo

Mostrar las tareas en **cartas agrupadas por proyecto** en Semana (`/today/week`), Mañana (`/today/tomorrow`) y el panel Hoy del dashboard, con **filtros por proyecto, prioridad, estado, etiqueta y tipo** que **recuerdan la última selección** (memoria servidor) al volver.

## 2. Alcance

- **IN:** Semana (pool + pendientes + búsqueda de backlog), Mañana (pool), Hoy (los 3 ítems elegidos).
- **OUT:** Cola/media, bloques, archivadas, otros módulos. **Sin migraciones** (se reusa `task_saved_views`).

## 3. Diseño de UI

### 3.1 Cartas por proyecto — componente compartido `ProjectCards`

- Agrupa por `project.id`; `project === null` → grupo **"Sin proyecto"**.
- Cabecera: punto de color (`project.color` inline; si es `null` usar `bg-muted-foreground`), nombre del proyecto, badge con la cantidad, chevron de colapso.
- **Cantidad y colapso solo en Semana y Mañana. En el panel Hoy: sin contadores y sin colapso** (lista negra §4 de la spec Hoy: nada de backlog/contadores en Hoy).
- Orden de cartas: Semana/Mañana → cantidad desc, luego nombre asc; **Hoy → orden de aparición** de los ítems (posición elegida).
- Orden de filas dentro de cada carta: Semana/Mañana → `created_at` asc; Hoy → `position`.
- Fila según vista: Semana-pool → título + "Sacar"; Semana-pendientes → título + "Agregar"; Mañana → título + "Elegir/Elegida" (activo con `border-primary bg-primary/10`, comportamiento toggle actual); Hoy → `DayItemRow` actual (ancla, nota, tachado) sin cambios de comportamiento.
- Estados vacíos: sin filtros → mensajes existentes ("No hay pendientes para agregar.", "No hay tareas en la semana. Agregalas desde Semana."); con filtros activos → **"No hay tareas que coincidan con los filtros."** Texto `text-muted-foreground`, sin iconos rojos/verdes.

### 3.2 Barra de filtros — `TaskFilterBar` (Semana y Mañana; NO en Hoy)

- Controles single-select (mismo `Select` shadcn que usa `TaskFilters.tsx`): **Proyecto · Prioridad · Estado · Etiqueta · Tipo** + botón **"Limpiar filtros"** (visible solo con ≥1 filtro activo). Placeholder **"Todos"**.
- Valores y etiquetas:
  - `project_id`: proyectos del usuario (orden nombre).
  - `priority` ∈ `Low|Normal|High|Urgent` → "Baja|Normal|Alta|Urgente" (coincidencia **case-insensitive** en BD; existe un registro con `high` minúscula).
  - `status` ∈ `Pending|In Progress` → "Pendiente|En progreso".
  - `tag`: etiquetas existentes en las tareas pendientes del usuario.
  - `type` ∈ `personal|freelance` → "Personal|Freelance".
- Interacción: cambiar un filtro → `router.get` con los parámetros de filtro; "Limpiar filtros" → `?cleared=1`. Componente **controlado** (los `filters` que llegan por props son la fuente de verdad).
- Los filtros aplican a **pool y pendientes de Semana (incluida la búsqueda de backlog) y al pool de Mañana**.

### 3.3 "Mostrar más" (Semana)

- Los pendientes arrancan en **20**; botón **"Mostrar más"** suma 20 por clic hasta **100**. Param `?limit=40`; el `limit` **no se persiste** en la memoria. Prop `hasMoreCandidates` decide si se muestra el botón.

### 3.4 Memoria (servidor, vista reservada)

- Filas `task_saved_views` reservadas por usuario:
  - `view_type` = `today_week` | `today_tomorrow`
  - `name` = "Semana (automática)" | "Mañana (automática)"
  - `is_default` = false, `filters` = JSON **solo** con las claves `project_id, priority, status, tag, type`.
- Semántica del GET (en `TaskFilterService::resolve`):
  - trae alguna clave de filtro → sanear + **persistir** + aplicar;
  - sin claves → aplicar lo recordado (y **sanear** lo leído, descartando valores hoy inválidos);
  - con `cleared=1` → **borrar** la fila y aplicar nada.
- Al sanear: `project_id` debe existir y pertenecer al usuario (si no, se descarta); `priority/status/type` fuera de sus listas → se descartan; `tag` → string recortado, ≤64.
- `/personal/saved-views` (`TaskSavedViewController::index`) **excluye** los `view_type` reservados; su `store` ya no los acepta (validación `in:` existente).

## 4. Backend

| Archivo | Cambio |
|---|---|
| `app/Services/Today/TaskFilterService.php` (nuevo) | Constantes `FILTER_KEYS`, `PRIORITIES`, `STATUSES`, `TYPES`, `VIEW_TYPES`; métodos `resolve(Request, string $section): array`, `sanitize(int $userId, array $input): array`, `remember`, `forget`, `options(int $userId): array{projects: Collection, tags: array}`. |
| `app/Models/ProjectTask.php` | `scopeApplyFilters($query, array $filters)`: `project_id` exact; `status` exact; `priority` con `LOWER(priority) = ?`; `type` vía `whereHas('project', type)`; `tag` vía `whereJsonContains('tags', $tag)`. |
| `app/Http/Controllers/Today/WeekController.php` | `index` y `search` aplican filtros; `with('project:id,name,color')` + `project_id` en select; props nuevas `projects`, `availableTags`, `filters`, `limit`, `hasMoreCandidates` (fetch `limit+1`); candidates `created_at asc, id asc`. |
| `app/Http/Controllers/Today/TomorrowController.php` | pool: + `notDone()` (hoy flojo vs. Semana), orden `created_at asc`, filtros, `project`; props `projects`, `availableTags`, `filters`. |
| `app/Http/Controllers/Today/TodayController.php` | `visibleItems` eager-load `task.project`; payload de item suma `project: {id, name, color} | null`. |
| `app/Http/Controllers/Personal/TaskSavedViewController.php` | `index` excluye `today_week`/`today_tomorrow`. |

Props nuevas (Semana y Mañana): `projects: {id, name, type}[]`, `availableTags: string[]`, `filters: Partial<{project_id: number, priority: string, status: string, tag: string, type: string}>` (solo claves activas).

## 5. Frontend

| Archivo | Cambio |
|---|---|
| `resources/js/components/today/ProjectCards.tsx` (nuevo) | Agrupador genérico: props `items: T[]` (con `id` y `project`), `renderRow`, `showCount?`, `collapsible?`, `order?: 'count' \| 'appearance'`. |
| `resources/js/components/today/TaskFilterBar.tsx` (nuevo) | 5 selects controlados + "Limpiar filtros"; `onChange(nextFilters)`, `onClear`. |
| `resources/js/pages/today/Week.tsx` | Barra arriba; cartas para pool, pendientes y resultados de búsqueda; "Mostrar más". |
| `resources/js/pages/today/Tomorrow.tsx` | Barra; cartas para el pool; conserva "Ayer quedó pendiente", selector de anclas y acciones. |
| `resources/js/components/today/TodayPanel.tsx` | `items` agrupados en cartas por proyecto (sin count, sin colapso). |

## 6. Copy (español)

"Limpiar filtros", "Mostrar más", "Sin proyecto", "Todos", "No hay tareas que coincidan con los filtros." — el resto ("Agregar", "Sacar", "Elegir", "Elegida", mensajes vacíos existentes) ya está.

## 7. Restricciones globales

- Tokens Ember: `bg-background bg-card border-border text-muted-foreground bg-primary` — sin verde `#13ec5b` ni rojo triste en estados vacíos.
- §4 spec Hoy: en el panel Hoy **sin contadores por proyecto, sin backlog, sin métricas** (rachas/puntos/%/historial).
- Copy visible en español; código, tablas, rutas y claves de props en inglés.
- Eloquent con `casts()`; sin `DB::` crudo; Form Requests existentes intactos.
- Wayfinder: si se tocan rutas con nombre, correr `php artisan wayfinder:generate --with-form`; validar con `npm run types`.
- Responsive: 375px y 1280×720 sin recortes (finish gate anti-ui-slop).

## 8. Tests (TDD)

- **`tests/Feature/Today/ViewMemoryTest.php` (nuevo)**
  1. GET `/today/week?priority=High` crea la fila reservada `today_week` con `filters.priority = 'High'`.
  2. Visita sin params aplica lo recordado (pool/candidates acotados).
  3. `?cleared=1` borra la fila y devuelve todo sin filtrar.
  4. `?project_id=` de otro usuario se descarta (no persiste, no filtra).
  5. `?limit=40` no persiste `limit` en `filters`.
  6. `GET /personal/saved-views` no lista las vistas reservadas (pero sí las propias del usuario).
- **`tests/Feature/Today/WeekTest.php` (extiende)**: pool y candidates incluyen `project {id,name,color}`; cada dimensión de filtro acota candidates; `?limit=40` devuelve 40 con 45 creadas; orden `created_at` asc sigue vigente.
- **`tests/Feature/Today/TomorrowTest.php` (nuevo)**: pool excluye `notDone`, aplica filtros recordados, incluye `project`, orden `created_at` asc.
- **`tests/Feature/Today/FlowTest.php` (extiende)**: item de Hoy incluye `project` (tarea con proyecto → objeto; item manual → `null`).

## 9. Verificación

`vendor/bin/pint --dirty --format agent` → `php artisan test --compact` → `npm run types` → `npx eslint` → `npm run build`. Smoke Playwright tras deploy: Semana/Mañana/Hoy a 1280×720 y 375px; en Hoy las cartas no muestran contadores.
