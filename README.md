# Megalomaniac Pro

Fitness + Freelance + Finance — all-in-one dark performance cockpit.

> Stack: **Laravel 12 • Inertia v2 • React 19 • Tailwind v4 • Wayfinder • Fortify • Pest 4** — desesperadamente rojizo (Ember #EF4444) desde 2026-08-24.

## ¿Qué es?
- **Gym** — workouts, routines, exercises, sets con RPE, timer, PR tracking.
- **Nutrition** — MealLog por fecha, foods/search, macros, calorías.
- **Supplements** — inventario, logs, stock bajo.
- **Grocery** — lista de compra, consume/restock, price history, bulk-restock.
- **Finance** — dashboard, purchases/incomes/debts/credit-cards/currencies/rates/income-sources/categories/withdrawals/savings-reserves/exchanges + statistics.
- **Freelance** — clients/projects/tasks/comments/media/quotes (pdf/duplicate/convert) + Notion sync.
- **Auth** — Fortify login/register/2FA + settings (profile/password/appearance/2FA).

## Quick start
```bash
composer setup          # install + key + migrate + npm build
composer run dev        # serve :8010 + queue + pail + vite :9333
php artisan migrate --seed
php artisan test --compact
npm run build && npm run types
vendor/bin/pint --dirty
```

## Estructura
```
app/Http/Controllers/{Gym,Nutrition,Supplement,Grocery,Finance,Freelance}
app/Models/* (35 modelos)
resources/js/pages/{fitness,finance,freelance,auth,settings}
resources/js/layouts/{main-layout,gym-layout,nutrition-layout,grocery-layout,supplement-layout}
resources/css/app.css  # tokens Ember rojizo (single source)
routes/web.php + routes/settings.php
html-preview/*           # previews estáticos (deuda)
```

## Design System (Ember B)
Tokens en `resources/css/app.css`:
```
--background #1C0F0F  --card #2B1A1A  --border #3E2121
--primary #EF4444  --primary-hover #DC2626  --ring #EF4444
--muted #3A2020  --muted-foreground #E8B4B4
--chart-1 #EF4444  --chart-2 #F97316  --chart-3 #EAB308  --chart-4 #A3A3A3  --chart-5 #7C3AED
```
Ver `docs/design-tokens.md` + `docs/architecture.md`. Landing `resources/js/pages/welcome.tsx`.

## Wayfinder
```ts
import finance from '@/routes/finance'        // rutas con nombre
import groceryItems from '@/routes/grocery/items' // acciones
finance.purchases.create().url
groceryItems.store.url()
```

## Testing
- Pest: `php artisan test --compact --filter=Dashboard`
- Playwright MCP: `docs/qa/playwright-report.md` — crawl diario completo (18+ rutas, 390+1440px, a11y, network).

## Docs
- `docs/architecture.md` — módulos y ERD
- `docs/design-tokens.md` — escala Ember completa
- `docs/modules/*.md` — por dominio
- `docs/qa/playwright-report.md` — reporte browser real
- `docs/superpowers/specs/2026-08-24-megalomaniac-red-design.md` — spec rojizo

## Roadmap (10 fases)
0 Docs → 1 Rojizo → 2 QA Playwright diario completo → 3 Shell → 4 Gym → 5 Nutrition/Grocery → 6 Finance core → 7 Finance stats → 8 Freelance → 9 Auth/Settings → 10 Polish (Pint/types/Lighthouse)

## Convenciones
- Eloquent con casts() + eager loading, sin DB::.
- Form Requests para validación.
- Tailwind v4 sin clases hardcoded `#102216`; usa `bg-background` etc.
- Wayfinder para links, no strings.

## Licencia
MIT — starter kit Laravel.
