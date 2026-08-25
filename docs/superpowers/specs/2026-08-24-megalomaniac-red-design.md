# Spec — Megalomaniac Rojizo + QA Diario + Roadmap Módulos — 2026-08-24

## Decisiones
- Paleta **B Ember #EF4444** sobre **#1C0F0F**, Solo Dark, Playwright MCP, Factories :8010, Pack inicial Fitness.

## Objetivo
Migrar Megalomaniac de verde #13ec5b a rojizo Ember, auditar toda la web como uso diario real con browser, corregir fallos, y planear features por módulo.

## Alcance (10 fases)
0 Docs → 1 Rojizo → 2 QA MCP diario completo → 3 Shell → 4 Gym → 5 Nutrition/Grocery/Supplement → 6 Finance core → 7 Finance stats → 8 Freelance → 9 Auth/Settings → 10 Polish

## Fase 0 — Docs (hecho 2026-08-24)
- README, architecture.md, design-tokens.md, docs/modules/*.md, este spec.

## Fase 1 — Design System Rojizo
Tokens en `app.css` + codemod 40 archivos. Criterio: 0 ocurrencias `102216/193322/23482f/92c9a4/13ec5b/green-` fuera de `chart` y comments. `vendor/bin/pint --dirty`, `tsc --noEmit`, screenshots antes/después.

## Fase 2 — QA Playwright MCP (uso diario completo)
Escenario diario:
1. Landing → Register → Login (Fortify)
2. Dashboard fitness (calories, macros, workouts, inventory, hydration, freelance link)
3. Gym: crear workout, añadir exercise, log sets (kg/reps/RPE/complete), finish, timer, quick library
4. Routines: listar/crear
5. Nutrition: navegar fecha, macros, add food por mealType, search foods
6. Supplements: listar, log intake, low stock
7. Grocery: inventory tab (search, filter category, stats, consume 1), add item, edit, delete, bulk restock modal, history tab, export
8. Finance: dashboard (balances, overdue, recentTx, monthly stats), purchases (filtros), incomes, debts+payments, credit-cards, currencies+restore, exchange-rates convert, income-sources, categories, withdrawals, savings-reserves deposit/withdraw, currency-exchanges, statistics
9. Freelance: dashboard stats, projects CRUD + payments/media/comments, tasks board + Notion sync, clients, quotes pdf/duplicate/convert
10. Settings: profile, password, appearance, 2FA, nav footer
Por cada paso: snapshot, screenshot 390+1440, console, network, a11y.

## Fases 4-9 — Módulos
Cada fase: fixes P0/P1 del reporte + 1 feature flagship:
- 4 Gym: PR Timeline + Volume Chart
- 5 Nutrition: AI meal scan + macro ring; Grocery: price history sparkline
- 6 Finance core: Budgets mensuales + Savings Goals ring
- 7 Finance stats: cashflow + breakdown chart-1..5
- 8 Freelance: Kanban drag + Time tracker + Invoices
- 9 Auth: appearance rojizo polish

## Fase 10 — Polish
Pint, types, Pest --compact, Playwright re-run, Lighthouse ≥90, web-design-guidelines AA.

## Riesgos
- 100+ hardcoded classes → codemod con regex cuidadoso, no replace ciego.
- html-preview deuda → migrar o aislar.
- Notion sync requiere creds → mock en QA.

## Métricas éxito
- 0 green tokens, contraste AA pass, 0 console errors en crawl, screenshots diff aprobados, Pest verde.
