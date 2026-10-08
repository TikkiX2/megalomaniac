import { Link, usePage } from '@inertiajs/react';
import { Dumbbell, LayoutGrid, MoreHorizontal, Utensils, Wallet } from 'lucide-react';
import { dashboard } from '@/routes';

const primaryItems = [
    { title: 'Panel', href: dashboard().url, icon: LayoutGrid, match: '/dashboard' },
    { title: 'Gym', href: '/fitness/gym', icon: Dumbbell, match: '/fitness/gym' },
    { title: 'Nutrición', href: '/fitness/nutrition', icon: Utensils, match: '/fitness/nutrition' },
    { title: 'Finanzas', href: '/finance/dashboard', icon: Wallet, match: '/finance' },
];

const moreItems = [
    { title: 'Suplementos', href: '/fitness/supplements', match: '/fitness/supplements' },
    { title: 'Compras', href: '/fitness/groceries', match: '/fitness/groceries' },
    { title: 'Freelance', href: '/freelance/dashboard', match: '/freelance' },
    { title: 'Tareas', href: '/personal/tasks', match: '/personal' },
    { title: 'Chat IA', href: '/ai/chat', match: '/ai/chat' },
];

export function MobileBottomNav() {
    const { url } = usePage();
    const isMoreActive = moreItems.some((item) => url.startsWith(item.match));

    return (
        <nav
            aria-label="Navegación móvil"
            className="fixed inset-x-0 bottom-0 z-40 h-16 border-t border-border bg-card md:hidden"
            style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
        >
            <div className="grid h-full w-full max-w-full grid-cols-5 overflow-hidden">
                {primaryItems.map((item) => {
                    const isActive = url.startsWith(item.match);
                    return (
                        <Link
                            key={item.title}
                            href={item.href}
                            aria-label={item.title}
                            aria-current={isActive ? 'page' : undefined}
                            className={`flex min-h-[44px] min-w-[44px] flex-col items-center justify-center gap-1 text-[11px] font-semibold transition-colors ${
                                isActive ? 'text-primary' : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <item.icon className="h-5 w-5" aria-hidden />
                            <span className="leading-none">{item.title}</span>
                        </Link>
                    );
                })}
                <details className="group relative">
                    <summary
                        aria-label="Más"
                        className={`flex min-h-[44px] min-w-[44px] h-full cursor-pointer list-none flex-col items-center justify-center gap-1 text-[11px] font-semibold transition-colors [&::-webkit-details-marker]:hidden ${
                            isMoreActive ? 'text-primary' : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <MoreHorizontal className="h-5 w-5" aria-hidden />
                        <span className="leading-none">Más</span>
                    </summary>
                    <div className="absolute right-2 bottom-full mb-2 w-48 overflow-hidden rounded-xl border border-border bg-card shadow-lg">
                        {moreItems.map((item) => {
                            const isActive = url.startsWith(item.match);
                            return (
                                <Link
                                    key={item.title}
                                    href={item.href}
                                    aria-current={isActive ? 'page' : undefined}
                                    className={`flex min-h-[44px] items-center px-4 text-sm font-semibold transition-colors ${
                                        isActive ? 'text-primary' : 'text-muted-foreground hover:text-foreground'
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
