# Mobile + PWA Instalable Mínima — Design Spec

**Fecha:** 2026-10-08
**Enfoque aprobado:** A — Incremental + PWA instalable mínima
**Paleta:** Ember Dark Solo — `background #1C0F0F`, `card #2B1A1A`, `border #3E2121`, `primary #EF4444`, `muted-fg #E8B4B4`

## 1. Objetivo
Eliminar scroll-X en 360px, targets táctiles >=44px y headers usables con una mano en fitness/finance/freelance, y dejar la app instalable (Android/iOS) sin offline avanzado.

## 2. Alcance / No-alcance
- Sí: shell mobile (bottom nav), escala tipográfica/contenedores, headers con wrap, tablas → cards en mobile, dialogs/forms touch, `manifest.webmanifest` + iconos + `theme-color` + SW precache app-shell vía `vite-plugin-pwa`.
- No: offline lectura/escritura, background sync, push, rediseño desktop, cambios de IA/sync de datos.

## 3. Arquitectura
- Mantener `AppShell variant="sidebar"` + `AppSidebar` en `md+`. Añadir `MobileBottomNav` solo `md:hidden` en `MainLayout`, reutilizando items de `resources/js/components/app-sidebar.tsx:27-83` (Panel `/`, Gym `/fitness/gym`, Nutrición `/fitness/nutrition`, Finanzas `/finance/dashboard`, Más → sheet con resto).
- `MainLayout` suma `pb-24 md:pb-0` al contenedor de children para no tapar con bottom nav; FAB chat `resources/js/layouts/main-layout.tsx:32-38` se mueve a `bottom-24 right-4 md:bottom-6 md:right-6`.
- PWA: `vite-plugin-pwa` en `vite.config.ts` con `registerType: autoUpdate`, precache `resources/js/app.tsx` + `resources/css/app.css`; `public/manifest.webmanifest` + iconos generados desde `public/favicon.svg`; head en `resources/views/app.blade.php` con `viewport-fit=cover`, `theme-color #1C0F0F`, `link manifest`, `apple-touch-icon`, `mobile-web-app-capable`.

## 4. Componentes / cambios por archivo
1. `resources/js/components/mobile-bottom-nav.tsx` (nuevo): nav 5 items, `usePage().url` para active, iconos lucide ya usados, `safe-area-inset-bottom`, altura `h-16`.
2. `resources/js/layouts/main-layout.tsx:19-28`: render nav + padding bottom; FAB offset mobile.
3. `resources/css/app.css`: utilidades `safe-area` (`padding-bottom: env(safe-area-inset-bottom)`), `.no-scrollbar` ya existe — reutilizar; sin nuevos tokens.
4. Headers: `resources/js/pages/finance/dashboard.tsx:94-114`, `resources/js/pages/fitness/grocery.tsx:182-219`, `resources/js/pages/fitness/dashboard.tsx:87-96`, `resources/js/pages/freelance/Dashboard.tsx:59-76`: `flex flex-wrap`, CTAs `w-full sm:w-auto justify-center`, tabs con `overflow-x-auto no-scrollbar`.
5. Escala: contenedores `mx-auto w-full max-w-7xl p-4 md:p-6 lg:p-8` (cambiar `p-6 lg:p-8` actuales); H1 `text-2xl md:text-4xl`, hero numbers `text-4xl md:text-5xl tabular-nums`. Archivos: `fitness/dashboard.tsx:78,81,109`, `finance/dashboard.tsx:84,88`, `fitness/grocery.tsx:171,175,433,448,458`, `fitness/nutrition.tsx:277,280`, `fitness/gym-routine.tsx:500`.
6. Tablas → cards: `fitness/history.tsx:370-436`, `fitness/grocery.tsx:542-627`: en `<md` ocultar `<table>` y renderizar lista de cards (misma data, sin duplicar fetch); `overflow-x-auto` se mantiene solo `md+`. `TaskTimeline.tsx:49` `min-w-[600px]` → `min-w-0 md:min-w-[600px]` con scroll solo desktop.
7. Sets gym `fitness/gym-routine.tsx:614,629-682`: en mobile colapsar a layout `grid-cols-[28px_1fr_44px_44px]` (set / inputs apilados kg+reps+RPE / check / delete); inputs `h-11` min 44px. Mantener header desktop intacto con `hidden md:grid`.
8. Dialogs/forms: `DialogContent` con `max-h-[92dvh] overflow-y-auto rounded-2xl`; inputs `h-12 md:h-14 text-base` para evitar zoom iOS; `DialogFooter` botones `w-full sm:w-auto`.
9. PWA assets: `public/manifest.webmanifest`, `public/icons/icon-192.png`, `icon-512.png`, `maskable-512.png` (exportados de `favicon.svg` en `#1C0F0F`); `vite.config.ts` + `resources/views/app.blade.php:5-12`.

## 5. Data flow
Sin cambios de datos. Bottom nav es solo enlaces Inertia `prefetch`. SW solo precache estático, `navigateFallback: null` para no romper Inertia (navegación server-driven).

## 6. Error handling / estados
- Si SW falla registro → console.warn, app sigue usable.
- Si iconos/manifest 404 → Lighthouse avisa pero no bloquea; verificación `npm run build` + curl manifest.
- Tablas vacías mantienen empty-state actual; cards mobile reutilizan mismo `filteredItems.length===0`.

## 7. Testing / QA
- `npm run build` + `php artisan test --compact` (smoke, sin nuevos tests backend).
- Playwright manual 360x740: Panel, Gym (sets), Nutrition, Grocery (tabla), Finance, Freelance, Settings → sin scroll-X (`document.documentElement.scrollWidth <= innerWidth`), FAB no tapa CTA, install prompt visible en Chrome.
- Lighthouse PWA: instalable + theme-color + icons.

## 8. Self-review
- Sin TBD: archivos y valores exactos arriba.
- Consistencia: bottom nav reutiliza rutas de sidebar; tokens Ember intactos; SW sin `navigateFallback` para no romper Inertia.
- Alcance: cabe en un plan (shell + escala + tablas + PWA). No incluye sync offline.
