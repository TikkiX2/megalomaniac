import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

interface JournalSubnavItem {
    title: string;
    href: string;
    isActive: (path: string) => boolean;
}

const DAY_PATH = /^\/diario\/\d{4}-\d{2}-\d{2}$/;

const items: JournalSubnavItem[] = [
    {
        title: 'Hoy',
        href: '/diario',
        isActive: (path) => path === '/diario' || DAY_PATH.test(path),
    },
    {
        title: 'Calendario',
        href: '/diario/calendario',
        isActive: (path) => path === '/diario/calendario',
    },
    {
        title: 'Todas',
        href: '/diario/todas',
        isActive: (path) =>
            path === '/diario/todas' || path === '/diario/buscar',
    },
    {
        title: 'Repaso',
        href: '/diario/repaso',
        isActive: (path) => path === '/diario/repaso',
    },
];

/**
 * The four diary pages stay reachable on mobile, where the sidebar and the
 * desktop rail are both hidden.
 */
export default function JournalSubnav() {
    const { url } = usePage();
    const path = url.split('?')[0] ?? '';

    return (
        <nav
            aria-label="Secciones del diario"
            className="mb-4 flex gap-1 overflow-x-auto pb-1 lg:hidden"
        >
            {items.map((item) => {
                const isActive = item.isActive(path);

                return (
                    <Link
                        key={item.href}
                        href={item.href}
                        aria-current={isActive ? 'page' : undefined}
                        className={cn(
                            'inline-flex min-h-11 shrink-0 items-center rounded-md border px-3 text-sm font-semibold transition-colors outline-none focus-visible:ring-3 focus-visible:ring-primary/40',
                            isActive
                                ? 'border-primary/40 bg-primary/10 text-primary'
                                : 'border-border bg-card/40 text-muted-foreground hover:border-primary/40 hover:text-white/90',
                        )}
                    >
                        {item.title}
                    </Link>
                );
            })}
        </nav>
    );
}
