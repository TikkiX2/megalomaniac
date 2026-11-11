# Sidebar por áreas — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar la sidebar que lista los ~40 destinos por un selector de 9 áreas con subnavegación contextual del área activa.

**Architecture:** Un módulo de datos (`navigation-areas.ts`) define áreas, grupos, hrefs y helpers de coincidencia de URL. `app-sidebar.tsx` renderiza el rail de áreas y, por debajo de `lg`, los subitems inline. `sidebar-area-nav.tsx` renderiza la columna contextual en escritorio. `main-layout.tsx` compone rail + columna + contenido; `mobile-bottom-nav.tsx` alinea sus accesos diarios con las áreas.

**Tech Stack:** React 19, Inertia v2, TypeScript 5.7, Tailwind v4, lucide-react, primitivas shadcn de `@/components/ui/sidebar`.

**Spec:** `docs/superpowers/specs/2026-11-11-sidebar-areas-design.md`

## Global Constraints

- No añadir dependencias ni cambiar `package.json` / `package-lock.json`.
- No tocar rutas, controladores, modelos ni PHP.
- Etiquetas en español exactamente como aparecen en el mapa del spec.
- Mantener los tokens Ember (`bg-background`, `bg-card`, `border-border`, `text-muted-foreground`, `bg-primary`); no introducir hex.
- Imports con alias `@/`; íconos desde `lucide-react`; primitivas de `@/components/ui/sidebar`.
- No commitear los archivos sueltos `qa-*.png` de la raíz.
- Verificación en el contenedor de build (no hay Node en el host):
  ```bash
  docker exec megalomaniac-build npm run types
  docker exec megalomaniac-build npx eslint <archivos tocados>
  docker exec megalomaniac-build npm run build
  ```
- Cada tarea termina con sus checks en verde y un commit propio.

## Review Focus

Estos son los casos que el cambio puede romper y que ningún test automático cubre (no existe runner de frontend). El barrido final del controlador debe verificarlos:

1. **Query string en la URL:** `/finance/purchases?page=2` debe seguir resaltando “Compras” (hay que quitar `?` antes de comparar).
2. **Rutas de detalle:** `/finance/purchases/12/edit`, `/people/5` y `/ai/chat/9` resaltan su ítem y su área.
3. **Prefijos hermanos:** `/health/conditions` no debe activar “Panel”; `/inspiration/settings` no debe activar “Explorar”. Gana siempre el href más largo.
4. **Rutas sin área:** `/settings/profile` deja el rail intacto y sin columna contextual, sin errores de render.
5. **Rail colapsado y tablet:** con la sidebar en iconos, la columna contextual desaparece y no hay scroll horizontal; entre 768 y 1024px los subitems del área activa siguen accesibles inline.
6. **Badge de aprobaciones:** el contador sigue visible en el rail móvil y en la columna de escritorio dentro de “Más”.

## Setup (lo hace el controlador antes de la Tarea 1)

- [ ] Crear imagen de build con PHP 8.4 + Node 22 y arrancar el contenedor persistente:

```bash
mkdir -p /tmp/opencode
cat > /tmp/opencode/Dockerfile.build <<'EOF'
FROM php:8.4-fpm
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl ca-certificates xz-utils git unzip procps \
    && curl -fsSL https://nodejs.org/dist/v22.23.4/node-v22.23.4-linux-x64.tar.xz \
        | tar -xJ -C /usr/local --strip-components=1 \
    && rm -rf /var/lib/apt/lists/*
EOF
docker build -t megalomaniac-build -f /tmp/opencode/Dockerfile.build /tmp/opencode
docker run -d --name megalomaniac-build \
  --user "$(id -u):$(id -g)" -e HOME=/tmp \
  -v /home/defaultuser/megalomaniac/app:/app -w /app \
  megalomaniac-build sleep infinity
docker exec megalomaniac-build npm ci --no-audit --no-fund
```

- [ ] Registrar la línea base: `docker exec megalomaniac-build npm run types` (si falla, anotar los errores preexistentes para no atribuirlos a este cambio).

---

### Task 1: Módulo de datos y helpers de navegación

**Files:**
- Create: `resources/js/lib/navigation-areas.ts`

**Interfaces:**
- Consumes: `dashboard` de `@/routes`, `freelance` de `@/routes/freelance`, `inspiration` de `@/routes/inspiration`, íconos de `lucide-react`.
- Produces:
  - `interface NavAreaItem { title: string; href: string; badge?: 'approvals' }`
  - `interface NavAreaGroup { label?: string; items: NavAreaItem[] }`
  - `interface NavArea { key: string; title: string; icon: LucideIcon; href: string; match: string[]; groups: NavAreaGroup[] }`
  - `const navAreas: NavArea[]`
  - `function findActiveArea(pathname: string): NavArea | null`
  - `function findActiveItem(pathname: string, area: NavArea): NavAreaItem | null`
  - `function matchesHref(pathname: string, href: string): boolean`

- [ ] **Step 1: Crear el archivo con el contenido exacto**

```typescript
import type { LucideIcon } from 'lucide-react';
import {
    Brain,
    Briefcase,
    CheckSquare,
    Contact,
    Dumbbell,
    FolderKanban,
    HeartPulse,
    LayoutGrid,
    Wallet,
} from 'lucide-react';
import { dashboard } from '@/routes';
import freelance from '@/routes/freelance';
import inspiration from '@/routes/inspiration';

export interface NavAreaItem {
    title: string;
    href: string;
    badge?: 'approvals';
}

export interface NavAreaGroup {
    label?: string;
    items: NavAreaItem[];
}

export interface NavArea {
    key: string;
    title: string;
    icon: LucideIcon;
    href: string;
    match: string[];
    groups: NavAreaGroup[];
}

export const navAreas: NavArea[] = [
    {
        key: 'today',
        title: 'Hoy',
        icon: CheckSquare,
        href: dashboard().url,
        match: ['/dashboard', '/today'],
        groups: [
            {
                items: [
                    { title: 'Hoy', href: dashboard().url },
                    { title: 'Semana', href: '/today/week' },
                    { title: 'Cola', href: '/today/queue' },
                    { title: 'Archivadas', href: '/today/archived' },
                ],
            },
        ],
    },
    {
        key: 'gym',
        title: 'Gym',
        icon: Dumbbell,
        href: '/fitness/gym',
        match: ['/fitness', '/gym'],
        groups: [
            {
                items: [
                    { title: 'Entrenamiento', href: '/fitness/gym' },
                    { title: 'Rutinas', href: '/fitness/routines' },
                    { title: 'Historial', href: '/fitness/history' },
                    { title: 'Nutrición', href: '/fitness/nutrition' },
                    { title: 'Suplementos', href: '/fitness/supplements' },
                    { title: 'Compras', href: '/fitness/groceries' },
                ],
            },
        ],
    },
    {
        key: 'personal',
        title: 'Proyectos',
        icon: FolderKanban,
        href: '/personal/projects',
        match: ['/personal'],
        groups: [
            {
                items: [
                    { title: 'Proyectos', href: '/personal/projects' },
                    { title: 'Tareas', href: '/personal/tasks' },
                ],
            },
        ],
    },
    {
        key: 'ai',
        title: 'IA',
        icon: Brain,
        href: '/ai/chat',
        match: ['/ai'],
        groups: [
            {
                items: [{ title: 'Chat', href: '/ai/chat' }],
            },
            {
                label: 'Asistentes',
                items: [
                    { title: 'Gym', href: '/ai/gym' },
                    { title: 'Nutrición', href: '/ai/nutrition' },
                    { title: 'Compras', href: '/ai/grocery' },
                    { title: 'Finanzas', href: '/ai/finance' },
                    { title: 'Freelance', href: '/ai/freelance' },
                    { title: 'Salud', href: '/ai/health' },
                    { title: 'Personas', href: '/ai/people' },
                ],
            },
            {
                label: 'Sistema',
                items: [
                    { title: 'Fuentes', href: '/ai/sources' },
                    { title: 'Memoria', href: '/ai/memory' },
                ],
            },
        ],
    },
    {
        key: 'finance',
        title: 'Finanzas',
        icon: Wallet,
        href: '/finance/dashboard',
        match: ['/finance'],
        groups: [
            {
                items: [
                    { title: 'Panel', href: '/finance/dashboard' },
                    { title: 'Compras', href: '/finance/purchases' },
                    { title: 'Ingresos', href: '/finance/incomes' },
                ],
            },
        ],
    },
    {
        key: 'freelance',
        title: 'Freelance',
        icon: Briefcase,
        href: freelance.dashboard().url,
        match: ['/freelance'],
        groups: [
            {
                items: [
                    { title: 'Panel', href: freelance.dashboard().url },
                    { title: 'Clientes', href: freelance.clients.index().url },
                    { title: 'Proyectos', href: freelance.projects.index().url },
                    { title: 'Cotizaciones', href: freelance.quotes.index().url },
                ],
            },
        ],
    },
    {
        key: 'health',
        title: 'Salud',
        icon: HeartPulse,
        href: '/health',
        match: ['/health'],
        groups: [
            {
                items: [
                    { title: 'Panel', href: '/health' },
                    { title: 'Condiciones', href: '/health/conditions' },
                    { title: 'Medicación', href: '/health/medications' },
                    { title: 'Mediciones', href: '/health/measurements' },
                    { title: 'Síntomas', href: '/health/symptoms' },
                    { title: 'Estudios', href: '/health/studies' },
                    { title: 'Citas', href: '/health/appointments' },
                    { title: 'Profesionales', href: '/health/professionals' },
                    { title: 'Chats', href: '/health/chats' },
                ],
            },
        ],
    },
    {
        key: 'people',
        title: 'Personas',
        icon: Contact,
        href: '/people',
        match: ['/people'],
        groups: [
            {
                items: [
                    { title: 'Personas', href: '/people' },
                    { title: 'Historial', href: '/people/timeline' },
                    { title: 'Calendario', href: '/people/calendar' },
                ],
            },
        ],
    },
    {
        key: 'more',
        title: 'Más',
        icon: LayoutGrid,
        href: inspiration.explore().url,
        match: ['/inspiration', '/agents', '/feed', '/storage', '/integrations'],
        groups: [
            {
                label: 'Inspiración',
                items: [
                    { title: 'Explorar', href: inspiration.explore().url },
                    { title: 'Ajustes de fuentes', href: inspiration.settings.index().url },
                ],
            },
            {
                label: 'Sistema',
                items: [
                    { title: 'Agentes', href: '/agents' },
                    { title: 'Feed', href: '/feed' },
                    { title: 'Archivos', href: '/storage' },
                    { title: 'Aprobaciones', href: '/integrations/approvals', badge: 'approvals' },
                ],
            },
        ],
    },
];

export function matchesHref(pathname: string, href: string): boolean {
    const cleanHref = href.split('?')[0];

    return (
        pathname === cleanHref ||
        pathname.startsWith(cleanHref === '/' ? '/' : `${cleanHref}/`)
    );
}

export function findActiveArea(pathname: string): NavArea | null {
    let active: NavArea | null = null;
    let activeLength = 0;

    for (const area of navAreas) {
        for (const prefix of area.match) {
            if (matchesHref(pathname, prefix) && prefix.length > activeLength) {
                active = area;
                activeLength = prefix.length;
            }
        }
    }

    return active;
}

export function findActiveItem(
    pathname: string,
    area: NavArea,
): NavAreaItem | null {
    let active: NavAreaItem | null = null;

    for (const group of area.groups) {
        for (const item of group.items) {
            if (
                matchesHref(pathname, item.href) &&
                (!active || item.href.length > active.href.length)
            ) {
                active = item;
            }
        }
    }

    return active;
}
```

- [ ] **Step 2: Verificar tipos**

Run: `docker exec megalomaniac-build npm run types`
Expected: exit 0 (o solo los errores preexistentes anotados en el setup).

- [ ] **Step 3: Verificar lint del archivo**

Run: `docker exec megalomaniac-build npx eslint resources/js/lib/navigation-areas.ts`
Expected: sin errores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/lib/navigation-areas.ts
git commit -m "feat(nav): mapa de áreas y helpers de navegación"
```

---

### Task 2: Rail de áreas en `AppSidebar`

**Files:**
- Modify (reescritura completa): `resources/js/components/app-sidebar.tsx`

**Interfaces:**
- Consumes: `navAreas`, `findActiveArea`, `findActiveItem` de `@/lib/navigation-areas`; `SharedData` de `@/types`.
- Produces: `export function AppSidebar()` con la misma firma pública que hoy (sin props), para que `main-layout.tsx` no cambie su uso.

- [ ] **Step 1: Leer el archivo actual completo** (`resources/js/components/app-sidebar.tsx`) y conservar de él: el efecto que cierra el sheet móvil al navegar (`setOpenMobile(false)` en `[url]`), el header con `AppLogo`, y `<NavUser />` en el footer.

- [ ] **Step 2: Reescribir el componente**

Borrar los arrays `mainNavItems`, `freelanceNavItems`, `financeNavItems`, `personalNavItems`, `peopleNavItems`, `aiModuleNavItems`, `healthNavItems`, la función `SidebarPinToggle` y todos los imports que queden sin uso (`Pin`, `PinOff`, `NavFooter`, `SidebarGroupLabel`, etc.). La estructura resultante:

```tsx
import { Link, usePage } from '@inertiajs/react';
import * as React from 'react';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { findActiveArea, findActiveItem, navAreas } from '@/lib/navigation-areas';
import type { SharedData } from '@/types';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { setOpenMobile } = useSidebar();
    const { props, url } = usePage<
        SharedData & { approvals_pending_count: number }
    >();
    const { approvals_pending_count } = props;
    const pathname = url.split('?')[0];
    const activeArea = findActiveArea(pathname);
    const activeItem = activeArea ? findActiveItem(pathname, activeArea) : null;

    React.useEffect(() => {
        setOpenMobile(false);
    }, [url, setOpenMobile]);

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            {/* header: logo + label "Áreas" */}
            {/* content: una fila por área */}
            {/* footer: NavUser */}
        </Sidebar>
    );
}
```

Requisitos del render:

- Header: igual al actual (logo + “Megalomaniac Pro”), y debajo una `<div className="px-2 pt-2">` con el texto “Áreas” en el mismo estilo que el antiguo label “Navegación” (`text-[10px] font-black uppercase tracking-widest text-muted-foreground group-data-[collapsible=icon]:hidden`). Eliminar el `SidebarPinToggle`.
- Content: un `SidebarGroup` con un `SidebarMenu` que mapea `navAreas`. Cada fila:
  - `SidebarMenuButton asChild tooltip={area.title} isActive={activeArea?.key === area.key}`
  - `Link href={area.href} prefetch` con `<area.icon className="h-4 w-4" />` y `<span>{area.title}</span>`.
- Debajo de la fila del área activa, solo cuando `activeArea?.key === area.key`:
  - `<SidebarMenuSub className="lg:hidden">` (visible en sheet móvil y rail de tablet; oculto en escritorio porque ahí manda la columna contextual).
  - Recorrer `area.groups`; si `group.label`, renderizar un `SidebarMenuSubItem` no interactivo con `<span className="px-2 text-[10px] font-bold uppercase tracking-wider text-muted-foreground/70">{group.label}</span>`.
  - Cada item: `SidebarMenuSubItem` + `SidebarMenuSubButton asChild isActive={activeItem?.href === item.href}` + `Link href={item.href} prefetch` con el título y, si `item.badge === 'approvals' && approvals_pending_count > 0`, el badge actual (`ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-black text-primary-foreground`).
- Footer: `<SidebarFooter><NavUser /></SidebarFooter>`. No renderizar `NavFooter`.

- [ ] **Step 3: Verificar tipos y lint**

```bash
docker exec megalomaniac-build npm run types
docker exec megalomaniac-build npx eslint resources/js/components/app-sidebar.tsx
```
Expected: sin errores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/app-sidebar.tsx
git commit -m "feat(nav): sidebar como selector de áreas"
```

---

### Task 3: Columna contextual e integración en `MainLayout`

**Files:**
- Create: `resources/js/components/sidebar-area-nav.tsx`
- Modify: `resources/js/layouts/main-layout.tsx`
- Modify: `resources/js/components/app-shell.tsx`

**Interfaces:**
- Consumes: `NavArea`, `findActiveItem` de `@/lib/navigation-areas`; `SharedData` de `@/types`; `useSidebar` de `@/components/ui/sidebar`.
- Produces: `export function SidebarAreaNav({ area }: { area: NavArea | null })` — devuelve el `<aside>` o `null`.

- [ ] **Step 1: Crear `sidebar-area-nav.tsx`**

```tsx
import { Link, usePage } from '@inertiajs/react';
import { findActiveItem } from '@/lib/navigation-areas';
import type { NavArea } from '@/lib/navigation-areas';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';

export function SidebarAreaNav({ area }: { area: NavArea | null }) {
    const { url } = usePage();
    const pathname = url.split('?')[0];
    const { approvals_pending_count } = usePage<
        SharedData & { approvals_pending_count: number }
    >().props;

    if (!area) {
        return null;
    }

    const activeItem = findActiveItem(pathname, area);

    return (
        <aside className="hidden w-48 shrink-0 flex-col gap-3 overflow-y-auto border-r border-border bg-background px-2 py-4 lg:flex">
            <p className="px-2 text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                {area.title}
            </p>
            {area.groups.map((group, index) => (
                <div key={group.label ?? index} className="flex flex-col gap-1">
                    {group.label && (
                        <span className="px-2 text-[10px] font-bold uppercase tracking-wider text-muted-foreground/70">
                            {group.label}
                        </span>
                    )}
                    {group.items.map((item) => {
                        const isActive = activeItem?.href === item.href;

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                prefetch
                                aria-current={isActive ? 'page' : undefined}
                                className={cn(
                                    'flex h-8 items-center gap-2 rounded-md px-2 text-sm transition-colors',
                                    isActive
                                        ? 'bg-accent font-medium text-foreground'
                                        : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                                )}
                            >
                                <span className="truncate">{item.title}</span>
                                {item.badge === 'approvals' &&
                                    approvals_pending_count > 0 && (
                                        <span className="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-black text-primary-foreground">
                                            {approvals_pending_count}
                                        </span>
                                    )}
                            </Link>
                        );
                    })}
                </div>
            ))}
        </aside>
    );
}
```

- [ ] **Step 2: Componer la columna en `MainLayout`**

En `resources/js/layouts/main-layout.tsx`:

- Imports nuevos: `useSidebar` de `@/components/ui/sidebar`, `SidebarAreaNav` de `@/components/sidebar-area-nav`, `findActiveArea` de `@/lib/navigation-areas`.
- Dentro del componente: `const pathname = url.split('?')[0];`, `const { state } = useSidebar();`, `const activeArea = findActiveArea(pathname);` y `const showAreaNav = state === 'expanded' && activeArea !== null;`.
- Reemplazar el bloque `<div className="flex flex-1 flex-col overflow-auto pb-24 md:pb-0">{children}</div>` por:

```tsx
<div className="flex min-h-0 flex-1 overflow-hidden">
    {showAreaNav && <SidebarAreaNav area={activeArea} />}
    <div className="flex min-w-0 flex-1 flex-col overflow-auto pb-24 md:pb-0">
        {children}
    </div>
</div>
```

- El header existente y el botón flotante de chat no cambian.

- [ ] **Step 3: Ajustar el ancho del rail en `AppShell`**

En `resources/js/components/app-shell.tsx`, en la rama `variant === 'sidebar'`, pasar el ancho del rail:

```tsx
return (
    <SidebarProvider
        defaultOpen={isOpen}
        style={{ '--sidebar-width': '13.5rem' } as React.CSSProperties}
    >
        {children}
    </SidebarProvider>
);
```

Importar el tipo: `import type { CSSProperties, ReactNode } from 'react';` y usar `as CSSProperties`.

- [ ] **Step 4: Verificar tipos, lint y build**

```bash
docker exec megalomaniac-build npm run types
docker exec megalomaniac-build npx eslint resources/js/components/sidebar-area-nav.tsx resources/js/layouts/main-layout.tsx resources/js/components/app-shell.tsx
docker exec megalomaniac-build npm run build
```
Expected: exit 0 en los tres; el build termina con “built in …”.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/sidebar-area-nav.tsx resources/js/layouts/main-layout.tsx resources/js/components/app-shell.tsx
git commit -m "feat(nav): subnavegación contextual del área activa"
```

---

### Task 4: Barra móvil, documentación y verificación de rutas

**Files:**
- Modify: `resources/js/components/mobile-bottom-nav.tsx`
- Modify: `AGENTS.md` (una línea de contexto)

**Interfaces:**
- Consumes: `dashboard` de `@/routes`; íconos `Brain`, `Dumbbell`, `FolderKanban`, `LayoutGrid` de `lucide-react`.
- Produces: `MobileBottomNav` sin cambios de firma.

- [ ] **Step 1: Actualizar `primaryItems` y `moreItems`**

Reemplazar las constantes actuales por:

```tsx
const primaryItems = [
    { title: 'Hoy', href: dashboard().url, icon: LayoutGrid, matches: ['/dashboard', '/today'] },
    { title: 'Gym', href: '/fitness/gym', icon: Dumbbell, matches: ['/fitness/gym'] },
    { title: 'Proyectos', href: '/personal/projects', icon: FolderKanban, matches: ['/personal'] },
    { title: 'IA', href: '/ai/chat', icon: Brain, matches: ['/ai'] },
];

const moreItems = [
    { title: 'Tareas', href: '/personal/tasks', matches: ['/personal/tasks'] },
    { title: 'Nutrición', href: '/fitness/nutrition', matches: ['/fitness/nutrition'] },
    { title: 'Suplementos', href: '/fitness/supplements', matches: ['/fitness/supplements'] },
    { title: 'Compras', href: '/fitness/groceries', matches: ['/fitness/groceries'] },
    { title: 'Finanzas', href: '/finance/dashboard', matches: ['/finance'] },
    { title: 'Freelance', href: '/freelance/dashboard', matches: ['/freelance'] },
    { title: 'Salud', href: '/health', matches: ['/health'] },
    { title: 'Personas', href: '/people', matches: ['/people'] },
    { title: 'Inspiración', href: '/inspiration', matches: ['/inspiration'] },
    { title: 'Agentes', href: '/agents', matches: ['/agents'] },
    { title: 'Feed', href: '/feed', matches: ['/feed'] },
    { title: 'Archivos', href: '/storage', matches: ['/storage'] },
    { title: 'Aprobaciones', href: '/integrations/approvals', matches: ['/integrations/approvals'] },
];
```

Adaptar el render: donde hoy se compara `url.startsWith(item.match)` usar `item.matches.some((prefix) => url.startsWith(prefix))`, tanto para `primaryItems`, `moreItems` como para `isMoreActive`. Añadir `max-h-[60vh] overflow-y-auto` al panel del `<details>`.

- [ ] **Step 2: Actualizar la línea de `AGENTS.md`**

Cambiar `layouts/main-layout.tsx sidebar 7 items` por `layouts/main-layout.tsx sidebar por áreas (9 áreas + subnavegación contextual)`.

- [ ] **Step 3: Comprobar que todos los hrefs estáticos existen como ruta GET**

```bash
docker exec megalomaniac-build sh -c 'php artisan route:list --json > /tmp/routes.json'
docker exec -i megalomaniac-build sh -c 'cat > /tmp/check-nav.mjs' <<'EOF'
import { readFileSync } from 'node:fs';
const routes = JSON.parse(readFileSync('/tmp/routes.json', 'utf8'));
const uris = new Set(routes.map((r) => '/' + r.uri.replace(/^\/+/, '')));
const src = readFileSync('/app/resources/js/lib/navigation-areas.ts', 'utf8');
const hrefs = [...src.matchAll(/href: '([^']+)'/g)].map((m) => m[1]);
const missing = hrefs.filter((href) => !uris.has(href));
if (missing.length) {
    console.error(missing);
    process.exit(1);
}
console.log(`OK: ${hrefs.length} hrefs resuelven`);
EOF
docker exec megalomaniac-build node /tmp/check-nav.mjs
```
Expected: `OK: <n> hrefs resuelven`.

- [ ] **Step 4: Verificar tipos, lint y build final**

```bash
docker exec megalomaniac-build npm run types
docker exec megalomaniac-build npx eslint resources/js/components/mobile-bottom-nav.tsx
docker exec megalomaniac-build npm run build
```
Expected: exit 0 en los tres.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/mobile-bottom-nav.tsx AGENTS.md
git commit -m "feat(nav): accesos diarios en barra móvil y docs"
```
