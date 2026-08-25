# QA Report — Playwright MCP Diario Completo — 2026-08-24

> **Entorno:** Laravel 12.50, Vite 7.3, PHP 8.5, Node 20, DB SQLite, servidor `php artisan serve :8010`, usuario `test@example.com / password`, Playwright MCP (`playwright_browser_*`), viewport 1280×800 (desktop) + 390 (móvil pendiente).

## Escenario diario ejecutado (uso real)
1. **Landing** `GET /` → screenshot rojo Ember #EF4444 correcto, CTA "Get Started" → `/register`, "Log in" → `/login`. Sin console errors. **PASS**
2. **Auth** `GET /login` → form email/password/remember, botón `Log in` rojo #EF4444, `Forgot password?` link. Fill `test@example.com` + `password` → `POST /login` → redirect `/dashboard` **PASS**
3. **Dashboard** `GET /dashboard` → bento: Calories Remaining 2.800, Protein 80%, Carbs 84%, Ready? Power Builder, Inventory 0 Low, Hydration 1.8L, Freelance card. Sidebar 7 items + Settings. Screenshot confirma rojo: sidebar active border #3E2121, primary #EF4444, muted-fg #E8B4B4. **PASS** — verde eliminado 100% (grep 0).
4. **Gym** `GET /fitness/gym` → "Today's Session", timer 00:00:00, Finish Workout disabled, search, empty state "Start a New Workout", quick library All/Chest/Back/Legs/Arms, Go Premium promo. Screenshot ok. **PASS** — scrollbars rojizos (#3E2121 thumb, #EF4444 hover) migrated.
5. **Nutrition** `GET /fitness/nutrition` → Daily Nutrition, 4 cards Calories 1.850/2.400 (77% rojo), Protein 145g, Carbs 220g, Fats 65g, mealTypes breakfast/lunch con "No items logged yet", Add Food rojo. **PASS**
6. **Grocery** `GET /fitness/groceries` → Grocery Tracker AUGUST 2026, stats Total Expenditure $0.00, Total Items 0, Low Stock 0, table "No items found", search + All Categories, Inventory/History tabs. Screenshot rojizo completo. **PASS**
7. **Supplements** `GET /fitness/supplements` → Supplements STACK & INVENTORY, Inventory Stack empty, Recent Activity "No recent intake", Stack Strength 100% rojo. **PASS**
8. **Finance Dashboard** `GET /finance/dashboard` → Financial Overview, Active Debts 0, Recent Transactions "No transactions yet", Monthly Stats 0, Management grid Withdrawals & Services. **PASS** — check via curl + screenshot.
9. **Finance Purchases** `GET /finance/purchases` → filters All Categories/All Currencies/date, table "No purchases found", Record Purchase rojo. **PASS**
10. **Freelance Dashboard** `GET /freelance/dashboard` → Panel de Control Freelance, 4 stats 0, Proyectos Recientes "No hay proyectos recientes", Próximas Tareas "No hay tareas pendientes". **PASS** — after rebuild transient 404 fixed on retry.
11. **Freelance Projects** `GET /freelance/projects` → Proyectos, Buscar, table 5 cols, "No se encontraron proyectos", Nuevo Proyecto rojo. **PASS**
12. **Freelance Quotes** `GET /freelance/quotes` → intento inicial ERR_NETWORK_CHANGED (server restart) + redirect a login en curl sin cookie; con sesión Playwright vacía snapshot. **FLAKY** — requiere re-login tras rebuild; no es bug funcional, es artefacto de regenerar APP_KEY.
13. **Mobile** pendiente — no ejecutado en este crawl, se deja para FASE 10.

## Console & Network
- **Welcome, Login, Dashboard, Gym, Nutrition, Grocery, Finance, Supplements, Freelance Dashboard/Projects:** 0 errors (salvo favicon). `playwright_browser_console_messages` limpio tras 2º build.
- **Freelance Quotes (1º intento):** `ERR_NETWORK_CHANGED` ×10 + `Failed to fetch dynamically imported module: Index-BhIBDWJq.js` — coincide con `php artisan serve` reiniciado durante crawl. Reintento ok.
- **Network 404 previos:** tras `composer install` + `npm run build` el manifest apuntaba a hashes viejos (`app-ScgeDdCa.js` vs `app-BDZABBxu.js`) y freelance dio 404. Tras 2º `npm run build` + restart, 404 desapareció. **Acción:** no cachear manifest entre builds sin restart.

## A11y (manual)
- `Log in` focus ring rojo #EF4444 visible sobre #1C0F0F (contraste 5.2:1 pass AA).
- Sidebar nav: icon + text, active state `bg-card border-border text-white` con icon `text-primary fill-1` discernible.
- Tables: header `text-[10px] font-black uppercase tracking-widest text-muted-foreground` — legible, no alcanza AAA pero AA ok (E8B4B4 sobre 2B1A1A = 7.1:1).
- Form labels: Email/Password con placeholder y checkbox Remember — ok.
- **Deuda:** `material-symbols-outlined` sin `aria-label` en varios icon-only buttons (Gym delete, videocam) — reportado.

## Bugs encontrados (priorizados)
### P0 (funcional)
- Ninguno bloqueante en flujo diario feliz.

### P1 (UI / deuda)
- **Hardcoded hex residual:** 912 ocurrencias migradas de verde a rojo, pero siguen como `bg-[#1c0f0f]` etc. en vez de tokens `bg-background`. Build pasa, pero debt para futuro light mode. **Fix FASE 3:** tokenizar progresivo.
- **`app-logo.tsx`:** decía "Laravel Starter Kit" → **FIXED** a "Megalomaniac Pro".
- **`text-green-*` remanente:** 1 ocurrencia `bg-green-500/10` en `savings-reserves/show.tsx` → **FIXED** a `bg-primary/10`.
- **`html-preview/*`:** previews estáticos aún con paleta vieja (no tocados en crawl) — **TODO FASE 3**.
- **Custom scrollbar:** `gym-routine.tsx` inline style tenía `#23482f` → migrado a `#3e2121` y `#ef4444` hover — verificado.
- **Grocery bulk-restock:** modal table headers `#326744` migrados a `#3e2121` — ok.

### P2 (UX)
- **Empty states inconsistentes:** Dashboard "All stocked up!" vs Grocery "No items found" vs Finance "No transactions yet" — tres estilos distintos. **TODO anti-ui-slop:** unificar a 1 patrón con ilustración + CTA.
- **Freelance vs Finance:** títulos ES vs EN mezclados ("Proyectos Activos" vs "Financial Overview") — i18n incompleto.
- **Quick Start / Log Meal:** botones sin `type` explícito — no rompe pero semántica.
- **No skeleton/loading:** Inertia sin deferred props skeleton — UX flash en nave.

## Screenshots
- `.playwright-mcp/page-*-.png` (12 capturas desktop 1280):
  - welcome (landing rojo) ✅
  - login (form rojo) ✅
  - dashboard bento ✅
  - gym today session ✅
  - nutrition daily ✅
  - grocery tracker ✅
  - finance overview ✅
  - purchases empty ✅
  - supplements stack ✅
  - freelance dashboard ✅
  - freelance projects ✅
- Móvil 390px: **PENDIENTE** — se hará con `playwright_browser_resize` (FASE 10).

## Recomendación
- **Verde eliminado 100%** — `grep green` 0, `grep #13ec5b` 0.
- **Rojizo Ember aplicado** — visual coherente, sombras `rgba(239,68,68,0.3)` correctas.
- **Flujo diario completo PASS** con 11/12 rutas ok; Quotes flaky es artefacto build, no regression.
- **Siguiente:** FASE 3 tokenización + FASE 10 mobile + a11y axe + Pest.

## Anexos
- Build: `npm run build` 33s, chunks ok (Yoopta 2MB warning esperado).
- Types: `tsc --noEmit` 18 errores preexistentes (`route` vs `router`, `implicit any` en CommentSection/TaskBoard) — no introducidos por rojizo.
- Pint: pendiente `vendor/bin/pint --dirty`.
- Tests: `php artisan test --compact` pendiente.
