import { Link, usePage } from '@inertiajs/react';
import {
    Brain,
    Dumbbell,
    FolderKanban,
    LayoutGrid,
    MoreHorizontal,
} from 'lucide-react';
import * as React from 'react';
import { dashboard } from '@/routes';

const primaryItems = [
    {
        title: 'Hoy',
        href: dashboard().url,
        icon: LayoutGrid,
        matches: ['/dashboard', '/today'],
    },
    {
        title: 'Gym',
        href: '/fitness/gym',
        icon: Dumbbell,
        matches: ['/fitness'],
    },
    {
        title: 'Proyectos',
        href: '/personal/projects',
        icon: FolderKanban,
        matches: ['/personal'],
    },
    { title: 'IA', href: '/ai/chat', icon: Brain, matches: ['/ai'] },
];

const moreItems = [
    { title: 'Tareas', href: '/personal/tasks', matches: ['/personal/tasks'] },
    {
        title: 'Nutrición',
        href: '/fitness/nutrition',
        matches: ['/fitness/nutrition'],
    },
    {
        title: 'Suplementos',
        href: '/fitness/supplements',
        matches: ['/fitness/supplements'],
    },
    {
        title: 'Compras',
        href: '/fitness/groceries',
        matches: ['/fitness/groceries'],
    },
    { title: 'Finanzas', href: '/finance/dashboard', matches: ['/finance'] },
    {
        title: 'Freelance',
        href: '/freelance/dashboard',
        matches: ['/freelance'],
    },
    { title: 'Salud', href: '/health', matches: ['/health'] },
    { title: 'Personas', href: '/people', matches: ['/people'] },
    { title: 'Diario', href: '/diario', matches: ['/diario'] },
    { title: 'Inspiración', href: '/inspiration', matches: ['/inspiration'] },
    { title: 'Agentes', href: '/agents', matches: ['/agents'] },
    { title: 'Feed', href: '/feed', matches: ['/feed'] },
    { title: 'Archivos', href: '/storage', matches: ['/storage'] },
    {
        title: 'Aprobaciones',
        href: '/integrations/approvals',
        matches: ['/integrations/approvals'],
    },
];

export function MobileBottomNav() {
    const { url } = usePage();
    const detailsRef = React.useRef<HTMLDetailsElement>(null);
    const isMoreActive = moreItems.some((item) =>
        item.matches.some((prefix) => url.startsWith(prefix)),
    );

    // Close the "Más" menu on navigation (Inertia preserves DOM, so <details> would stay open).
    React.useEffect(() => {
        detailsRef.current?.removeAttribute('open');
    }, [url]);

    // Close on Escape / outside click (<details> has no native dismiss).
    React.useEffect(() => {
        const onKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                detailsRef.current?.removeAttribute('open');
            }
        };
        const onPointerDown = (e: PointerEvent) => {
            const el = detailsRef.current;
            if (
                el?.hasAttribute('open') &&
                e.target instanceof Node &&
                !el.contains(e.target)
            ) {
                el.removeAttribute('open');
            }
        };
        document.addEventListener('keydown', onKeyDown);
        document.addEventListener('pointerdown', onPointerDown);
        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('pointerdown', onPointerDown);
        };
    }, []);

    return (
        <nav
            aria-label="Navegación móvil"
            className="fixed inset-x-0 bottom-0 z-40 h-16 border-t border-border bg-card md:hidden"
            style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
        >
            <div className="grid h-full w-full max-w-full grid-cols-5 overflow-x-clip overflow-y-visible">
                {primaryItems.map((item) => {
                    const isActive = item.matches.some((prefix) =>
                        url.startsWith(prefix),
                    );
                    return (
                        <Link
                            key={item.title}
                            href={item.href}
                            aria-label={item.title}
                            aria-current={isActive ? 'page' : undefined}
                            className={`flex min-h-[44px] min-w-[44px] flex-col items-center justify-center gap-1 text-[11px] font-semibold transition-colors ${
                                isActive
                                    ? 'text-primary'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <item.icon className="h-5 w-5" aria-hidden />
                            <span className="leading-none">{item.title}</span>
                        </Link>
                    );
                })}
                <details ref={detailsRef} className="group relative">
                    <summary
                        aria-label="Más"
                        className={`flex h-full min-h-[44px] min-w-[44px] cursor-pointer list-none flex-col items-center justify-center gap-1 text-[11px] font-semibold transition-colors [&::-webkit-details-marker]:hidden ${
                            isMoreActive
                                ? 'text-primary'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <MoreHorizontal className="h-5 w-5" aria-hidden />
                        <span className="leading-none">Más</span>
                    </summary>
                    <div className="absolute right-2 bottom-full mb-2 max-h-[60vh] w-48 overflow-x-hidden overflow-y-auto rounded-xl border border-border bg-card shadow-lg">
                        {moreItems.map((item) => {
                            const isActive = item.matches.some((prefix) =>
                                url.startsWith(prefix),
                            );
                            return (
                                <Link
                                    key={item.title}
                                    href={item.href}
                                    aria-current={isActive ? 'page' : undefined}
                                    className={`flex min-h-[44px] items-center px-4 text-sm font-semibold transition-colors ${
                                        isActive
                                            ? 'text-primary'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    {item.title}
                                </Link>
                            );
                        })}
                    </div>
                </details>
            </div>
        </nav>
    );
}
