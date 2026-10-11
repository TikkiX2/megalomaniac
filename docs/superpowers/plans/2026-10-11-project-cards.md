# Cartas por proyecto con filtros con memoria — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mostrar las tareas de Semana, Mañana y el panel Hoy como cartas agrupadas por proyecto, con filtros por proyecto/prioridad/estado/etiqueta/tipo que recuerdan la última selección (memoria servidor).

**Architecture:** Un servicio (`TaskFilterService`) resuelve, sanitiza y persiste los filtros en filas reservadas de `task_saved_views`; los controllers de Today lo usan para acotar sus queries y devolver props (`filters`, `projects`, `availableTags`, datos de `project` por tarea). En el frontend dos componentes nuevos (`ProjectCards`, `TaskFilterBar`) se comparten entre las tres vistas.

**Tech Stack:** Laravel 12, Inertia v2 + React 19, Tailwind v4 (tokens Ember), Pest 4 (SQLite en memoria), Wayfinder.

**Spec:** `docs/superpowers/specs/2026-10-11-project-cards-design.md` — el plan arguye desde ella; es la autoridad ante conflicto.

## Global Constraints

- Copy visible en español; código/tablas/rutas/claves de props en inglés.
- Tokens: `bg-background bg-card border-border text-muted-foreground bg-primary` — sin verde `#13ec5b` ni rojo triste en estados vacíos.
- Lista negra §4 spec Hoy: en el panel Hoy **sin contadores por proyecto, sin backlog, sin métricas**.
- Sin migraciones; reusar `task_saved_views` con `view_type` reservados `today_week`/`today_tomorrow`.
- Valores de filtro: `priority ∈ Low|Normal|High|Urgent` (match case-insensitive), `status ∈ Pending|In Progress`, `type ∈ personal|freelance`, `tag` string ≤64, `project_id` del usuario.
- Claves persistidas en memoria: solo `project_id, priority, status, tag, type` (nunca `limit` ni `cleared`).
- Orden de filas: Semana/Mañana `created_at asc, id asc`; Hoy `position`. Pool de Mañana gana `notDone()` (alineación con Semana).
- Candidatos de Semana: default `limit` 20, `?limit=` clamp 20..100, `hasMoreCandidates` vía fetch de `limit+1`.
- Eloquent con `casts()`; sin `DB::` crudo. Si se agregan rutas con nombre: `php artisan wayfinder:generate --with-form`.
- Verificación por tarea: `php artisan test --compact` (solo tests de la tarea), `vendor/bin/pint --dirty --format agent` al final; frontend: `npm run types`, `npx eslint <archivos>`, `npm run build` solo en tareas que tocan TSX (Task 7 al final).

## Review Focus

1. **Memoria tóxica:** `?project_id=` de otro usuario o `priority` inválida debe descartarse al sanear (no persiste, no filtra) — `ViewMemoryTest`, test "descarta project_id ajeno".
2. **`limit` no es filtro:** "Mostrar más" no debe alterar la memoria de filtros — `ViewMemoryTest`, test "limit no persiste".
3. **Memoria stale:** proyecto luego borrado (soft delete) en la memoria → la visita sin params descarta el filtro y no rompe — `ViewMemoryTest`, test "sanitiza memoria con proyecto borrado".
4. **Búsqueda + filtros:** la búsqueda de backlog de Semana debe respetar los filtros activos — `WeekTest`, test "busqueda respeta filtros".
5. **§4 en Hoy:** el panel Hoy no puede ganar contadores/información de backlog en las cartas — pin: revisión de Task 7 contra el diff + smoke Playwright (375px y 1280×720) al final; no hay runner de tests JS.

---

### Task 1: Backend Semana — TaskFilterService, scope de filtros, WeekController

**Files:**
- Create: `app/Services/Today/TaskFilterService.php`
- Modify: `app/Models/ProjectTask.php`
- Modify: `app/Http/Controllers/Today/WeekController.php`
- Modify: `app/Http/Controllers/Personal/TaskSavedViewController.php`
- Test: `tests/Feature/Today/ViewMemoryTest.php` (nuevo), `tests/Feature/Today/WeekTest.php` (extiende)

**Interfaces:**
- Consume: `ProjectTask` scopes `inWeek/notDone/notArchived` (existentes), `TaskSavedView` (fillable incluye `view_type/filters`), rutas existentes `GET /today/week`, `GET /today/week/search`, `GET /personal/saved-views`.
- Produce (lo usan Tasks 2-7):
  - `App\Services\Today\TaskFilterService`: `const FILTER_KEYS = ['project_id','priority','status','tag','type']`; `const PRIORITIES = ['Low','Normal','High','Urgent']`; `const STATUSES = ['Pending','In Progress']`; `const TYPES = ['personal','freelance']`; `const VIEW_TYPES = ['week' => 'today_week', 'tomorrow' => 'today_tomorrow']`; `resolve(Request $request, string $section): array` (sanitiza+recuerda si trae claves, lee+sanitiza si no, borra con `cleared=1`); `sanitize(int $userId, array $input): array`; `remember(int $userId, string $section, array $filters): void` (borra la fila si el array queda vacío); `forget(int $userId, string $section): void`; `options(int $userId): array{projects: Collection, tags: array}` (proyectos no borrados orden nombre; tags distintos de tareas pendientes del usuario, orden alfabético).
  - `ProjectTask::scopeApplyFilters($query, array $filters)` → `project_id` exact; `status` exact; `priority` con `whereRaw('LOWER(priority) = ?', [strtolower($v)])`; `type` con `whereHas('project', fn ($q) => $q->where('type', $v))`; `tag` con `whereJsonContains('tags', $v)`.
  - Props nuevas de `GET /today/week`: `projects` `[{id,name,type}]`, `availableTags: string[]`, `filters` (solo claves activas), `limit` (int), `hasMoreCandidates` (bool); `pool`, `candidates` y resultados de `search` con `project: {id,name,color}|null`.
  - TS (Task 4+): `resources/js/types/today.ts` con `ProjectBrief {id,name,color}`, `TaskRow {id,title,project}`, `ActiveFilters`.

- [ ] **Step 1: Escribir los tests fallando**

En `tests/Feature/Today/ViewMemoryTest.php` (estilo WeekTest: `$this->get(...)->assertOk()->viewData('page')['props']`):

```php
test('week recuerda filtros aplicados en vista reservada', ...)   // GET /today/week?priority=High → fila today_week con filters = ['priority' => 'High']
test('week aplica filtros recordados en visita sin params', ...)   // fila previa priority=High + tareas High/Low → candidates solo High
test('week cleared borra la memoria', ...)                         // fila previa + GET ?cleared=1 → sin fila y candidates completas
test('week descarta project_id ajeno', ...)                        // GET ?project_id=<proyecto de otro usuario> → sin fila y candidates completas
test('week limit no persiste en la memoria', ...)                  // GET ?limit=40 → fila (si existe) sin clave limit; hasMoreCandidates correcto
test('week sanitiza memoria con proyecto borrado', ...)            // fila con project_id de proyecto soft-deleted → visita sin params no filtra
test('saved views del modulo personal excluye vistas reservadas', ...) // fila today_week + GET /personal/saved-views → JSON sin today_week, con las propias
```

En `WeekTest.php` agrega:

```php
test('week pool y candidates incluyen proyecto con color', ...)    // tareas con proyecto (color #3B82F6) y sin proyecto → props con project {id,name,color} y null
test('week filtra candidates por cada dimension', ...)             // dataset: priority=High / status='In Progress' / tag=fase-a / type=freelance / project_id=X → candidates solo con la coincidencia
test('week limit 40 devuelve 40 candidatas', ...)                 // 45 tareas → ?limit=40 → 40 items y hasMoreCandidates true
test('week busqueda respeta filtros', ...)                        // ?q=foo&priority=Low → resultados solo Low
```

- [ ] **Step 2: Verificar que fallan**

Run: `php artisan test --compact --filter=ViewMemoryTest` y `--filter=WeekTest`
Expected: FAIL (props/Endpoints aún sin filtros).

- [ ] **Step 3: Implementar**

`TaskFilterService` como en Interfaces (inyección por método: `public function index(Request $request, TaskFilterService $filters)`), `scopeApplyFilters`, y WeekController: pool/candidates/search con `->applyFilters($filters)` + `with('project:id,name,color')` y `project_id` en el select; candidates con `limit($limit + 1)` → `hasMoreCandidates = count > limit` → `->take($limit)`; `options()` para `projects`/`availableTags`. `TaskSavedViewController::index` agrega `->whereNotIn('view_type', array_values(TaskFilterService::VIEW_TYPES))`. El `limit` del request: `(int)`, clamp 20..100, default 20; nunca entra en `filters`.

- [ ] **Step 4: Verificar que pasan**

Run: `php artisan test --compact --filter=ViewMemoryTest && php artisan test --compact --filter=WeekTest`
Expected: PASS (los 3 tests viejos de WeekTest siguen verdes).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Today/TaskFilterService.php app/Models/ProjectTask.php app/Http/Controllers/Today/WeekController.php app/Http/Controllers/Personal/TaskSavedViewController.php tests/Feature/Today/ViewMemoryTest.php tests/Feature/Today/WeekTest.php
git commit -m "feat(today): filtros con memoria y proyecto en la semana"
```

---

### Task 2: Backend Mañana — TomorrowController alineado

**Files:**
- Modify: `app/Http/Controllers/Today/TomorrowController.php`
- Test: `tests/Feature/Today/TomorrowTest.php` (nuevo)

**Interfaces:**
- Consume: `TaskFilterService` (Task 1) — `resolve($request, 'tomorrow')`, `options($userId)`; `ProjectTask::scopeApplyFilters`.
- Produce: props de `GET /today/tomorrow` += `projects`, `availableTags`, `filters`; `pool` con `project: {id,name,color}|null`, excluye `is_done`, orden `created_at asc, id asc`, filtros aplicados (se mantiene el límite 10).

- [ ] **Step 1: Tests fallando** — `tests/Feature/Today/TomorrowTest.php`:

```php
test('tomorrow pool excluye tareas hechas', ...)
test('tomorrow pool ordenado por creacion ascendente', ...)
test('tomorrow pool incluye proyecto con color', ...)
test('tomorrow pool aplica filtros recordados', ...)   // fila today_tomorrow con priority=High → pool solo High
```

- [ ] **Step 2: Verificar fallan**: `php artisan test --compact --filter=TomorrowTest` → FAIL.
- [ ] **Step 3: Implementar** — pool: `->notDone()->applyFilters($filters)->orderBy('created_at')->orderBy('id')->limit(10)` + `with('project:id,name,color')` + `project_id` en select; props nuevas desde `TaskFilterService`.
- [ ] **Step 4: Verificar pasan** + `php artisan test --compact --filter=FlowTest` (los tests de mañana existentes siguen verdes).
- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Today/TomorrowController.php tests/Feature/Today/TomorrowTest.php
git commit -m "feat(today): pool de mañana con filtros, proyecto y orden de creacion"
```

---

### Task 3: Backend Hoy — items con proyecto

**Files:**
- Modify: `app/Http/Controllers/Today/TodayController.php`
- Test: `tests/Feature/Today/FlowTest.php` (extiende)

**Interfaces:**
- Consume: `Day::visibleItems`, `DayItem::task()` (existente), `ProjectTask::project()`.
- Produce: payload de item suma `project: {id,name,color}|null` (Task 7 lo consume en `TodayPanel`).

- [ ] **Step 1: Test fallando** — en FlowTest:

```php
test('items de hoy incluyen el proyecto de su tarea', ...)
// Day de hoy con 2 visibleItems: uno con task_id de tarea con proyecto (color), otro manual sin task_id → props day.items[0].project = {id,name,color}, items[1].project = null
```

- [ ] **Step 2: Verificar falla**: `php artisan test --compact --filter=FlowTest` → FAIL.
- [ ] **Step 3: Implementar** — `Day::with(['visibleItems' => fn ($q) => $q->orderBy('position'), 'visibleItems.task.project'])`; en `dayPayload` agregar `'project' => $item->task?->project ? ['id' => ..., 'name' => ..., 'color' => ...] : null` (actualizar el PHPDoc del método).
- [ ] **Step 4: Verificar pasa**: `php artisan test --compact --filter=FlowTest`.
- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Today/TodayController.php tests/Feature/Today/FlowTest.php
git commit -m "feat(today): items del panel hoy incluyen su proyecto"
```

---

### Task 4: Frontend — types compartidos, ProjectCards y TaskFilterBar

**Files:**
- Create: `resources/js/types/today.ts`
- Create: `resources/js/components/today/ProjectCards.tsx`
- Create: `resources/js/components/today/TaskFilterBar.tsx`

**Interfaces:**
- Produce (Tasks 5-7):

```ts
// types/today.ts
export interface ProjectBrief { id: number; name: string; color: string | null; }
export interface TaskRow { id: number; title: string; project: ProjectBrief | null; }
export interface ActiveFilters { project_id?: number; priority?: string; status?: string; tag?: string; type?: string; }
export interface FilterOption { id: number; name: string; type: string; }
```

```tsx
// ProjectCards.tsx — agrupador controlado, sin estado propio salvo el colapso por carta
interface ProjectCardsProps<T extends { id: number; project: ProjectBrief | null }> {
    items: T[];
    renderRow: (item: T) => React.ReactNode;
    showCount?: boolean;   // default false
    collapsible?: boolean; // default false
    order?: 'count' | 'appearance'; // default 'count' (count desc, luego nombre asc; null = "Sin proyecto")
}
```

```tsx
// TaskFilterBar.tsx — controlado
interface TaskFilterBarProps {
    filters: ActiveFilters;
    projects: FilterOption[];
    tags: string[];
    onChange: (next: ActiveFilters) => void; // onChange({...filters, [key]: value}) con value tipado
    onClear: () => void;
}
```

- [ ] **Step 1: Implementar los 3 archivos** — `ProjectCards`: tarjeta `rounded-xl border border-border bg-card overflow-hidden` por proyecto; cabecera botón con dot (`bg-muted-foreground` o `style={{ backgroundColor: project.color }}`), nombre en `text-xs font-black uppercase tracking-widest text-muted-foreground`, `Badge` con la cantidad (solo `showCount`), chevron (solo `collapsible`); filas en `divide-y divide-border`. `TaskFilterBar`: fila `flex flex-wrap gap-2` con 5 `Select` (valor `'todos'` mapeado a `undefined`; Radix no acepta `value=""`) y botón "Limpiar filtros" (`variant="outline" size="sm"`) condicional a algún filtro activo. Prioridades: Baja/Normal/Alta/Urgente; Estados: Pendiente/En progreso; Tipos: Personal/Freelance.
- [ ] **Step 2: Verificar**: `npm run types && npx eslint resources/js/types/today.ts resources/js/components/today/ProjectCards.tsx resources/js/components/today/TaskFilterBar.tsx` → sin errores.
- [ ] **Step 3: Commit**

```bash
git add resources/js/types/today.ts resources/js/components/today/ProjectCards.tsx resources/js/components/today/TaskFilterBar.tsx
git commit -m "feat(today): componentes ProjectCards y TaskFilterBar"
```

---

### Task 5: Frontend Semana — Week.tsx con cartas y filtros

**Files:**
- Modify: `resources/js/pages/today/Week.tsx`

**Interfaces:**
- Consume: props de Task 1 (`pool/candidates` con `project`, `projects`, `availableTags`, `filters`, `limit`, `hasMoreCandidates`) + componentes de Task 4.

- [ ] **Step 1: Implementar** — Props nuevas en la interfaz `Props`. Barra `TaskFilterBar` debajo del título "Pool semanal". Handlers: `applyFilters(next)` → `router.get('/today/week', clean(next), { preserveState: true, replace: true })` (el controller persiste la memoria); `onClear` → `router.get('/today/week', { cleared: 1 }, { preserveState: true, replace: true })`; "Mostrar más" → `router.get('/today/week', { ...clean(filters), limit: limit + 20 }, { preserveState: true, replace: true })` (deshabilitado si `!hasMoreCandidates`). Pool y pendientes (y resultados de búsqueda) renderizados con `ProjectCards` (`showCount`, `collapsible`, `order="count"`); filas: pool → "Sacar", pendientes/búsqueda → "Agregar" (lógica actual). Vacío con filtros activos → "No hay tareas que coincidan con los filtros."; sin filtros → mensajes actuales. El buscador se conserva tal cual (300 ms debounce + fetch).
- [ ] **Step 2: Verificar**: `npm run types && npx eslint resources/js/pages/today/Week.tsx` y smoke `npm run build`.
- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/today/Week.tsx
git commit -m "feat(today): semana muestra tareas en cartas por proyecto con filtros"
```

---

### Task 6: Frontend Mañana — Tomorrow.tsx con cartas y filtros

**Files:**
- Modify: `resources/js/pages/today/Tomorrow.tsx`

**Interfaces:**
- Consume: props de Task 2 (`pool` con `project`, `projects`, `availableTags`, `filters`) + componentes de Task 4.

- [ ] **Step 1: Implementar** — Barra `TaskFilterBar` bajo el título; pool renderizado con `ProjectCards` (`showCount`, `collapsible`) y fila actual de toggle Elegir/Elegida (mismo `active` y bordes). "Ayer quedó pendiente", selector de anclas, "Guardar mañana" y "Dejarlo vacío" sin cambios. Handlers igual que Task 5 apuntando a `/today/tomorrow`. Vacío con filtros activos → "No hay tareas que coincidan con los filtros."
- [ ] **Step 2: Verificar**: `npm run types && npx eslint resources/js/pages/today/Tomorrow.tsx && npm run build`.
- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/today/Tomorrow.tsx
git commit -m "feat(today): mañana muestra el pool en cartas por proyecto con filtros"
```

---

### Task 7: Frontend Hoy — TodayPanel agrupa por proyecto (§4)

**Files:**
- Modify: `resources/js/components/today/TodayPanel.tsx`

**Interfaces:**
- Consume: `project` en `TodayItem` (Task 3) + `ProjectCards` (Task 4).

- [ ] **Step 1: Implementar** — `TodayItem` suma `project: ProjectBrief | null`. Reemplazar el `div` de filas por `ProjectCards` con `order="appearance"` y **sin** `showCount` ni `collapsible` (§4: Hoy sin contadores); `renderRow` = `DayItemRow` actual. Estados vacíos y el resto del panel (MediaLine, bloque/rutina) intactos.
- [ ] **Step 2: Verificar**: `npm run types && npx eslint resources/js/components/today/TodayPanel.tsx && npm run build`.
- [ ] **Step 3: Commit**

```bash
git add resources/js/components/today/TodayPanel.tsx
git commit -m "feat(today): panel hoy agrupa los elegidos por proyecto"
```

---

### Verificación final (controlador, no tarea)

`vendor/bin/pint --dirty --format agent` → `php artisan test --compact` (suite completa) → `npm run types` → `npx eslint` → `npm run build` → revisión final de rama completa (subagent-driven-development) → smoke Playwright tras deploy (Semana/Mañana/Hoy a 1280×720 y 375px; en Hoy sin contadores por proyecto).
