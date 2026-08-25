# Arquitectura — Megalomaniac Pro

## Visión
Triple cockpit: **Fitness** (cuerpo) + **Finance** (dinero) + **Freelance** (trabajo) en una sola app dark rojiza. Usuario = pro athlete/freelancer que gestiona todo sin salir.

## Stack
Laravel 12, Inertia v2, React 19, Tailwind v4, Wayfinder, Fortify, Pest 4, Vite 7, Tcpdf, Medialibrary, Laravel Notion API.

## Capas
- **Routes** `routes/web.php` — prefix groups con `auth+verified` middleware.
- **Controllers** `app/Http/Controllers/{Gym,Nutrition,Supplement,Grocery,Finance,Freelance}` — apiResource + Inertia::render.
- **Models** 35 — relaciones Eloquent, casts() en método, factories presentes.
- **Frontend** `resources/js/{pages,layouts,components/ui,lib}` — Inertia + React Compiler.
- **Tokens** `resources/css/app.css` — @theme Ember.

## Módulos

### Gym
Models: `Exercise, Routine, RoutineExercise, Workout, WorkoutExercise, WorkoutSet, User`
Flujo: `routines/index` → `gym-routine.tsx` (activeWorkout + suggestedRoutine) → `POST /gym/workouts` → `POST /gym/workouts/{id}/exercises` → `POST /gym/workout-exercises/{id}/sets`
Estado: timer en `useState` + `setInterval`, `router.post/patch` con Inertia.

### Nutrition
Models: `Food, MealLog, MealItem`
Rutas: `nutrition/foods/search`, `nutrition/logs`, `nutrition/logs/items`
UI: `nutrition.tsx` (mealTypes breakfast/lunch/dinner/snack) + `nutrition-layout.tsx`.

### Supplements
Models: `Supplement, SupplementLog`
Rutas: `supplements/items` (apiResource), `supplements/{id}/log`, `supplements/logs`
UI: `supplement.tsx` + `supplement-layout.tsx`, card `lowStock`.

### Grocery
Models: `GroceryItem, GroceryPriceHistory`
Rutas: `grocery/items`, `grocery/history`, `grocery/bulk-restock`, `grocery/{item}/consume`
UI: `grocery.tsx` (638 líneas) + `grocery-layout.tsx` — tabs inventory/history, deficitItems, restockData, Dialog forms.

### Finance (13 recursos)
Models: `Purchase, PurchaseCategory, Income, IncomeSource, Debt, DebtPayment, CreditCard, Currency, ExchangeRate, CurrencyExchange, Withdrawal, WithdrawalCategory, SavingsReserve, ReserveTransaction`
Dashboard: `finance/dashboard.tsx` — balances por moneda, recentTransactions, stats, overdueDebts.
Submódulos: `purchases/index`, `incomes/*`, `debts/*`, `credit-cards/*`, `currencies/*`, `exchange-rates/convert`, `income-sources/*`, `categories/*`, `withdrawals/*`, `savings-reserves/{deposit,withdraw}`, `currency-exchanges/*`, `statistics/index`.

### Freelance
Models: `Client, Project, ProjectTask, ProjectComment, ProjectPayment, Quote, QuoteItem` (+ MediaLibrary + Notion)
Rutas: `freelance/dashboard`, `clients`, `projects/{payments,media}`, `comments`, `quotes/{pdf,duplicate,convert}`, `projects.tasks`, `tasks/sync-to-notion`, `notion/webhook`
UI: `freelance/Dashboard.tsx`, `projects/{Index,Form,Show}`, `clients/*`, `quotes/*`, components `YooptaEditor, TaskBoard, MediaGallery, CommentSection`.

### Dashboard & Landing
`welcome.tsx` (hero + pricing), `fitness/dashboard.tsx` (bento), `finance/dashboard.tsx`, `freelance/Dashboard.tsx`, `layouts/main-layout.tsx` (sidebar 7 items).

### Auth & Settings
Fortify + `auth/*` pages + `settings/{profile,password,appearance,two-factor}` + `layouts/auth/*`.

## ERD (resumen)
```
User 1—N Workout N—N Exercise (via WorkoutExercise 1—N WorkoutSet)
User 1—N Routine 1—N RoutineExercise N—1 Exercise
User 1—N MealLog 1—N MealItem N—1 Food
User 1—N Supplement + SupplementLog
User 1—N GroceryItem 1—N GroceryPriceHistory
User 1—N Purchase N—1 PurchaseCategory + Currency (+Debt)
User 1—N Income N—1 IncomeSource + Currency
User 1—N Debt 1—N DebtPayment
User 1—N CreditCard, Currency, ExchangeRate, Withdrawal, SavingsReserve
User 1—N Client 1—N Project 1—N {Task, Comment, Payment, Quote}
```

## Build
`vite.config.ts` — laravel + react(compiler) + tailwindcss + wayfinder
`composer.json:dev` — concurrently serve :8010 + queue + pail + vite :9333

## Deuda conocida (pre-rojizo)
- 100+ hardcoded `#102216` etc. — migrar a tokens.
- `html-preview/*` duplicado.
- `app-logo.tsx` dice "Laravel Starter Kit".
- Filtros `purchases` sin Wayfinder binding.
- Sin coverage frontend, skeletons inconsistentes.

## QA
Playwright MCP crawl diario completo: ver `docs/qa/playwright-report.md`.

## Roadmap
Ver README + spec `docs/superpowers/specs/2026-08-24-megalomaniac-red-design.md`.
