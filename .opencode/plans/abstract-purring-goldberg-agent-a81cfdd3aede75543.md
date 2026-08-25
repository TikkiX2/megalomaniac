# Plan — Megalomaniac Fix ES + Sync + Sidebar + Freelance + Gym + Nutrition + Grocery — 2026-08-24
> Clasificación: **Arquitectónica** (multi-subsistema). Requiere spec → plan → implementación por fases. Plan actual cubre Dashboard sync, ES, Sidebar hover/pin, Freelance navegación/CRUD, Workout historial/filtros/add, Nutrition peso/altura, Grocery tipografía, QA browser.

## Contexto explorado (read-only)
- **Dashboard stats bug:** `routes/web.php:16` `first()?->total_calories` solo 1 MealLog vs `NutritionController` suma todos; protein/carbs hardcode 145/180 `dashboard.tsx:102-131` vs nutrition hardcode 220/250, goal 2800 vs 2400 mismatch. `MealLog->items->food` chain existe pero no se usa.
- **Sidebar dual:** `main-layout.tsx` vivo 125 líneas `w-64` fijo sin collapse vs `ui/sidebar.tsx` 722 líneas huérfano con `SidebarProvider` `collapsible="icon"` `sidebar_state` cookie ya. Docs `architecture.md:20` confirma main-layout canónico.
- **Freelance:** Sidebar 1 link `/freelance/dashboard` (main-layout:16) → listas clientes/projects/quotes huérfanas (solo dashboard `Ver todo`). `TaskBoard.tsx` Nueva Tarea placeholder sin POST, `CommentSection` mezcla `route()` vs Wayfinder, `MediaGallery` `files[]` vs backend `file` 422.
- **Workout:** No historial (`WorkoutController@index` JSON sin Inertia), filtros `All/Chest...` sin `useState` siempre All, no add exercise UI (solo via routines modal indirecto), `Go Premium` promo `gym-routine.tsx:469-483`.
- **Nutrition:** `nutrition.tsx` ignora prop `logs`, cards hardcode 1,850, donut hardcode 28/43/29, Add Food botón sin modal, date picker sin `router.get`, rutas `foods/search` `foods` no implementadas. `User` sin `weight/height`.
- **Grocery:** `grocery.tsx:653` `@import Lexend` + `font-display` único en codebase vs global `Instrument Sans` (`app.css:9`).

## Decisiones pre-aprobadas
- Idioma: **todo ES** (consistencia Freelance ES). Traducir EN → ES (Welcome, Dashboard, Nutrition, Gym, Finance, Settings).
- Palette Ember #EF4444 ya OK (no tocar).
- No nueva dep sin aprobación.

## Enfoques (2-3) — Recomendado A
- **A (recomendado) Migrar a `ui/sidebar`:** Reusar `SidebarProvider` (ya persiste `sidebar_state` cookie), mover `navItems` reales a `app-sidebar.tsx`, `variant="sidebar"` `collapsible="icon"`, pin = `open` + `localStorage`, hover peek `onMouseEnter/Leave` con `group-data-[state=collapsed]:hover:w-[16rem]`. Menos código (722 líneas ya hechas) vs parchear main-layout 40 líneas frágiles.
- **B Parche main-layout:** Añadir `isPinned`, `isCollapsed`, `isHovered` con `localStorage` y `w-64`↔`w-[3rem]`. Rápido pero duplica lógica huérfana.
- **C Híbrido:** Mantener main-layout como wrapper de `AppSidebar` (compatibilidad). A elegido por mantenibilidad.

## Plan de implementación — 8 fases secuenciales
Cada fase: branch/worktree → tests → Pint → build → Playwright MCP `:8010` verificación.

### FASE 0 — i18n ES base
- Extraer `navItems` y títulos a ES: `Dashboard→Panel`, `Workout Log→Entrenamiento`, `Nutrition→Nutrición`, `Supplements→Suplementos`, `Grocery List→Compras`, `Welcome back`→`Bienvenido`, `Nutrition Daily`→`Nutrición Diaria`, `Financial Overview`→`Resumen Financiero`, etc. Sin lib i18n, hardcode ES consistente. Archivos: `main-layout.tsx`, `dashboard.tsx`, `nutrition.tsx`, `gym-routine.tsx`, `finance/dashboard.tsx`, `welcome.tsx`.

### FASE 1 — Dashboard sync Nutrition (P0)
- **Backend:** `routes/web.php:dashboard` corregir `first()` → `MealLog::with('items')->whereDate()->get()` sum `total_calories` + `protein/carbs/fats` via `MealItem::whereHas(mealLog) sum snapshot` o `getTotalMacros`. Añadir props `proteinToday, carbsToday, fatsToday, calorieGoal, proteinGoal, carbsGoal, fatsGoal` (centralizar 2800/2400 → 2400).
- **Frontend:** `fitness/dashboard.tsx` extender `Props`, reemplazar hardcode `145/180 80%`, `210/250 84%`, añadir card `Grasas`, calcular `progress = today/goal*100`, colores `bg-indigo-500/sky-500` → tokens rojizos. Hidratación queda fake o `HydrationLog` futuro.
- **Nutrition:** `nutrition.tsx` consumir `logs`, agrupar `meal_type`, render `items.food`, computar summary desde `logs` (no hardcode), alimentar donut `strokeDasharray` proporcional, implementar date `router.get` y delete `logs/items/{id}`.

### FASE 2 — Sidebar plegable hover+pin (P0)
- **Migrar:** Mover `navItems` ES + submenú freelance (`Clientes→/freelance/clients`, `Proyectos→/projects`, `Cotizaciones→/quotes`) con `NavMain` collapsible `group-data-[collapsible=icon]`. Freelance y Finance con `Collapsible` niños. `MainLayout` → `SidebarProvider` wrapper.
- **Hover peek:** `Sidebar` `onMouseEnter` si `!isPinned && state===collapsed` → `setOpen(true)` timeout 150ms, `onMouseLeave` close. Respetar `isMobile` y `prefers-reduced-motion`.
- **Pin:** botón `PanelLeftOpen/Close` en `SidebarHeader` que hace `toggleSidebar()` + `localStorage.setItem('sidebar-pinned', pin)`, sync con cookie `sidebar_state` ya existente.
- **Tokens:** usar `bg-sidebar`/`text-sidebar-foreground` en vez de `bg-background`. Tests: `sidebar_state` cookie toggle Pest.

### FASE 3 — Freelance navegación + CRUD (P0)
- **Sidebar:** añadir subnav freelance (ver FASE 2) → acceso directo listas.
- **TaskBoard:** reemplazar placeholder `scrollIntoView` por modal `useForm` + `router.post(freelance.projects.tasks.store, {title,status, due_date})`, mover ya funciona `router.patch`, validar `title/status sometimes` ya fix.
- **CommentSection:** importar `Separator` arriba, cambiar `route()` → Wayfinder `freelance.projects.comments.store`, manejar `content` Yoopta `array`, `processing` disable, error toast.
- **MediaGallery:** cambiar `files[]` → `file` (singular) o backend `hasFile('files')` plural consistente, fix `ProjectController@uploadFile` `addMediaFromRequest('file')`, validar ownership.
- **Backend:** `ClientController@show` retornar Inertia o 404, `ProjectTaskController` validar ownership `task->project->user_id`.

### FASE 4 — Workout historial + filtros + add (P0)
- **Historial:** Crear `GET /fitness/history` → `WorkoutController@history` Inertia `fitness/history.tsx` con lista paginada `workouts with routine, exercises.sets`, filtros fecha, PR timeline reuse `weeklyVolume`. Reutilizar `loadWorkoutWithHistory`.
- **Filtros:** `gym-routine.tsx` añadir `const [activeFilter, setActiveFilter]=useState('All')` + `onClick` botones, filtrar `libraryExercises.filter(ex => (activeFilter==='All'||ex.muscle_group===activeFilter) && matchesSearch)`, search incluye `muscle_group`/`type`, categorías dinámicas `[...new Set(...)]` incluye `Shoulders`.
- **Add Exercise:** Dialog `+ Nuevo Ejercicio` footer Quick Library con `useForm {name,muscle_group,type,video_url}` → `router.post('/gym/exercises')`, invalidar `exercises` prop.
- **Premium:** eliminar bloque `Go Premium` `gym-routine.tsx:469-483` y `html-preview`.

### FASE 5 — Nutrition add meals + peso/altura (P1)
- **Add Food modal:** Copiar patrón grocery `Dialog` con `foods/search` (implementar `NutritionController@searchFoods` `where('name','like')`) + `storeFood` + `storeMealItem` (`date,meal_type,food_id,quantity`). Ya existe `storeMealItem/deleteMealItem` pero faltan `searchFoods/storeFood`.
- **Peso/Altura:** Migración `add_weight_height_to_users_table` `weight decimal 5,2, height decimal 5,2, target_weight, birth_date`, modelo `User` casts, `BodyMetric` opcional historial. Nueva page `settings/profile` campos peso/altura + cálculo IMC, o `fitness/nutrition` card peso + gráfico 7 días si `BodyMetric`.
- **Delete:** conectar `deleteMealItem` botón en cada item.

### FASE 6 — Grocery tipografía (P1)
- Eliminar `grocery.tsx:159` `font-display` y `dangerouslySetInnerHTML` Lexend, usar `font-sans` global `Instrument Sans`. Si se quiere display distinta, añadir token `--font-display` en `app.css` pero no Lexend hardcode. Verificar `html-preview` ya migrado.

### FASE 7 — QA browser completo tras cambios
- **Playwright MCP diario:** repetir flujo 1-12 + nuevos: dashboard macros sync, sidebar collapse/hover/pin (3 estados), freelance navegar clientes→projects→quotes sin URL directa, tasks add/move, comments/media upload, workout historial + filtro Chest + add exercise, nutrition add food + peso, grocery font check `getComputedStyle fontFamily === Instrument Sans`.
- **Screenshots:** desktop 1280 + mobile 390 por ruta, console 0 errors, network 0 404.
- **Otros:** `vendor/bin/pint --dirty`, `npm run build` (ver Wayfinder regenerado), `php artisan test --compact` (Gym 5 pass, Grocery factory fix si aplica).

## Archivos tocados (resumen)
- `routes/web.php` (dashboard macros)
- `app/Http/Controllers/Nutrition/NutritionController.php` (searchFoods/storeFood)
- `app/Models/User.php` + migración weight/height
- `resources/js/pages/fitness/dashboard.tsx`, `nutrition.tsx`, `grocery.tsx`, `gym-routine.tsx` (nuevo history), `fitness/history.tsx` (nuevo)
- `resources/js/layouts/main-layout.tsx` → wrapper `SidebarProvider`, `app-sidebar.tsx`, `ui/sidebar.tsx` hover
- `resources/js/pages/freelance/*` + `components/freelance/TaskBoard/CommentSection/MediaGallery`
- `resources/css/app.css` (si token display)
- `docs/qa/playwright-report.md` update

## Riesgos
- Migrar sidebar rompe `isActive = url.startsWith` para subrutas → usar `isCurrentUrl` helper.
- `User` weight migration con datos null → default null, no breaking.
- `searchFoods` sin datos seed → test con tinker `Food::create`.

## Verificación
- `curl -H "X-Inertia: true" http://localhost:8010/dashboard | jq .props.proteinToday`
- `playwright_browser_evaluate` fontFamily grocery `Instrument Sans`
- `php artisan test --filter=Grocery` debe pasar tras factory fix `is_purchased` → `current_stock`

¿Aprobás este plan para salir de plan mode y ejecutar FASE 0-1? O prefieres ajustar p.ej. peso en `settings` vs nutrition?
