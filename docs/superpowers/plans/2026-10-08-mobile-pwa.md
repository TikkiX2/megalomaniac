# Mobile + PWA Instalable Mínima Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dejar Megalomaniac usable en 360px e instalable como PWA sin romper desktop ni Inertia.

**Architecture:** Shell incremental (bottom nav solo mobile) + escala/tablas responsive + `vite-plugin-pwa` con precache app-shell y `navigateFallback: null`.

**Tech Stack:** Laravel 12 + Inertia v2 + React 19 + Tailwind v4 + vite-plugin-pwa + Playwright QA manual

**Spec:** `docs/superpowers/specs/2026-10-08-mobile-pwa-design.md`

## Global Constraints

- Paleta Ember Solo Dark intacta: `background #1C0F0F`, `card #2B1A1A`, `border #3E2121`, `primary #EF4444`, `muted-fg #E8B4B4` — un solo cambio es bug.
- SW con `navigateFallback: null` — Inertia es server-driven, un fallback rompería navegación.
- Targets táctiles >=44px en mobile (`h-11`/`h-12` mínimo) — un solo `h-8`/`h-9` interactivo en mobile es bug.
- Sin scroll-X en 360px: `document.documentElement.scrollWidth <= window.innerWidth` en Panel/Gym/Nutrition/Grocery/Finance/Freelance.
- Convenciones: Eloquent `casts()`, Wayfinder `@/routes`, sin `DB::`, Form Requests; frontend `bg-background` etc.
- Tras tocar PHP correr `vendor/bin/pint --dirty --format agent`; tras cada tarea `npm run build` debe pasar.

## Review Focus

- 360px con teclado iOS abierto: el bottom nav no debe tapar el input con foco ni el FAB de gym — se espera `pb` con safe-area y FAB desplazado.
- `min-w-[600px]` / `whitespace-nowrap` residual en mobile: se espera cards apiladas, no scroll-X forzado.
- Instalación iOS Safari: se espera `apple-touch-icon` + `mobile-web-app-capable` aunque no haya prompt automático.
- Dialog largo en `92dvh`: se espera scroll interno sin bloquear la página de fondo.
- Zoom iOS en inputs: se espera `text-base` (16px) en inputs mobile para no disparar auto-zoom.

---

### Task 1: PWA base instalable

**Files:**
- Create: `public/manifest.webmanifest`
- Create: `public/icons/icon-192.png`, `public/icons/icon-512.png`, `public/icons/maskable-512.png`
- Modify: `resources/views/app.blade.php:4-12`
- Modify: `vite.config.ts:7-23`
- Modify: `package.json:29-84` (add `vite-plugin-pwa`)

**Interfaces:**
- Consumes: `public/favicon.svg` como fuente de iconos sobre fondo `#1C0F0F`.
- Produces: `/manifest.webmanifest` servido + `VitePWA()` registrado; Task 2-4 no dependen de código, solo de head.

- [ ] **Step 1: Añadir `vite-plugin-pwa` y configurar precache sin fallback**

```bash
npm i -D vite-plugin-pwa
```

Run: `npm run build`
Expected: FAIL antes del cambio (sin manifest/SW) → tras implementar PASS con `dist/manifest.webmanifest` o `public/manifest.webmanifest` copiado.

- [ ] **Step 2: Implementar `manifest.webmanifest` + head en `app.blade.php` + `VitePWA({ registerType: 'autoUpdate', manifest: { name: 'Megalomaniac Pro', short_name: 'Megalomaniac', display: 'standalone', background_color: '#1C0F0F', theme_color: '#1C0F0F' }, workbox: { navigateFallback: null } })`**

Valores exactos del spec §4.9. Iconos 192/512 + maskable exportados de `favicon.svg`.

- [ ] **Step 3: Verificar instalabilidad**

Run: `npm run build && ls public/manifest.webmanifest public/icons/icon-192.png public/icons/icon-512.png && grep -q 'manifest.webmanifest' resources/views/app.blade.php && grep -q 'theme-color' resources/views/app.blade.php && echo OK`
Expected: `OK`

- [ ] **Step 4: Commit**

```bash
git add public/manifest.webmanifest public/icons vite.config.ts package.json resources/views/app.blade.php
git commit -m "feat(pwa): instalable minima manifest + SW precache"
```

### Task 2: Shell mobile (bottom nav + layout)

**Files:**
- Create: `resources/js/components/mobile-bottom-nav.tsx`
- Modify: `resources/js/layouts/main-layout.tsx:19-28,32-38`
- Modify: `resources/css/app.css:98-106` (safe-area util)

**Interfaces:**
- Consumes: rutas de `resources/js/components/app-sidebar.tsx:27-83`; `usePage().url` para active.
- Produces: `<MobileBottomNav />` con 5 items (`Panel / Gym / Nutrición / Finanzas / Más`), `h-16 md:hidden`, `padding-bottom: env(safe-area-inset-bottom)`; `MainLayout` exporta mismo props.

- [ ] **Step 1: Crear `MobileBottomNav` con active por `url.startsWith()` y overflow “Más” vía dropdown nativo**

Reutilizar iconos lucide: `LayoutGrid, Dumbbell, Utensils, Wallet, MoreHorizontal`.

- [ ] **Step 2: Integrar en `MainLayout`: `<div className="flex flex-1 flex-col overflow-auto pb-24 md:pb-0">` + `<MobileBottomNav/>`, FAB a `bottom-24 right-4 md:bottom-6 md:right-6`**

- [ ] **Step 3: Verificar sin scroll-X y nav visible solo mobile**

Run: `npm run build && grep -q 'MobileBottomNav' resources/js/layouts/main-layout.tsx && grep -q 'md:hidden' resources/js/components/mobile-bottom-nav.tsx && echo OK`
Expected: `OK` + QA Playwright 360px: nav visible, sidebar trigger intacto en desktop.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/mobile-bottom-nav.tsx resources/js/layouts/main-layout.tsx resources/css/app.css
git commit -m "feat(mobile): bottom nav + safe-area + FAB offset"
```

### Task 3: Headers con wrap + escala tipográfica

**Files:**
- Modify: `resources/js/pages/finance/dashboard.tsx:94-114`
- Modify: `resources/js/pages/fitness/grocery.tsx:182-219`
- Modify: `resources/js/pages/fitness/dashboard.tsx:78-96`
- Modify: `resources/js/pages/freelance/Dashboard.tsx:59-76`
- Modify: `resources/js/pages/fitness/nutrition.tsx:277-280`, `resources/js/pages/fitness/gym-routine.tsx:500`

**Interfaces:**
- Consumes: `<MobileBottomNav/>` montado (no tapa CTAs por `pb-24`).
- Produces: headers con `flex flex-wrap`, CTAs `w-full sm:w-auto`, títulos y contenedores escalados.

- [ ] **Step 1: Aplicar `flex flex-wrap` + CTAs `w-full sm:w-auto justify-center` + tabs `overflow-x-auto no-scrollbar` en los 4 headers**

- [ ] **Step 2: Escalar contenedores a `p-4 md:p-6 lg:p-8`, H1 a `text-2xl md:text-4xl`, hero numbers a `text-4xl md:text-5xl tabular-nums`**

- [ ] **Step 3: Verificar build + grep de clases**

Run: `npm run build && grep -R "p-4 md:p-6" resources/js/pages/finance/dashboard.tsx resources/js/pages/fitness/dashboard.tsx | head -5 && echo OK`
Expected: `OK` + QA 360px sin desface de CTAs.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/finance/dashboard.tsx resources/js/pages/fitness/grocery.tsx resources/js/pages/fitness/dashboard.tsx resources/js/pages/freelance/Dashboard.tsx resources/js/pages/fitness/nutrition.tsx resources/js/pages/fitness/gym-routine.tsx
git commit -m "fix(mobile): headers wrap + escala 360px"
```

### Task 4: Tablas → cards + sets touch + dialogs

**Files:**
- Modify: `resources/js/pages/fitness/history.tsx:370-436`
- Modify: `resources/js/pages/fitness/grocery.tsx:542-627`
- Modify: `resources/js/pages/fitness/gym-routine.tsx:614-682`
- Modify: `resources/js/components/personal/views/TaskTimeline.tsx:49`
- Modify: `resources/js/components/ui/dialog.tsx` (DialogContent base)

**Interfaces:**
- Consumes: headers escalados de Task 3 (misma data, sin nuevos fetches).
- Produces: lista cards `<md` + tabla `hidden md:...`; sets gym mobile `grid-cols-[28px_1fr_44px_44px]` con inputs `h-11`; dialogs `max-h-[92dvh]`.

- [ ] **Step 1: History/Grocery: render condicional `block md:hidden` cards + `hidden md:table` tabla con misma `filteredItems`/filas; Timeline `min-w-0 md:min-w-[600px]`**

- [ ] **Step 2: Sets gym mobile + dialogs/inputs `h-11/h-12 text-base`, `DialogContent max-h-[92dvh] overflow-y-auto`, footer `w-full sm:w-auto`**

- [ ] **Step 3: Verificar QA + tests**

Run: `npm run build && php artisan test --compact`
Expected: PASS + Playwright 360px: Gym sets operables con pulgar, dialogs con scroll interno, `scrollWidth <= innerWidth`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/fitness/history.tsx resources/js/pages/fitness/grocery.tsx resources/js/pages/fitness/gym-routine.tsx resources/js/components/personal/views/TaskTimeline.tsx resources/js/components/ui/dialog.tsx
git commit -m "fix(mobile): tablas a cards + touch 44px + dialogs dvh"
```
