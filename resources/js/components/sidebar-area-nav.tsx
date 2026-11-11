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
            <p className="px-2 text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                {area.title}
            </p>
            {area.groups.map((group, index) => (
                <div key={group.label ?? index} className="flex flex-col gap-1">
                    {group.label && (
                        <span className="px-2 text-[10px] font-bold tracking-wider text-muted-foreground/70 uppercase">
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
