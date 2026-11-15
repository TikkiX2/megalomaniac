import { Link, usePage } from '@inertiajs/react';
import { Pin, PinOff } from 'lucide-react';
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
import {
    findActiveArea,
    findActiveItem,
    navAreas,
} from '@/lib/navigation-areas';
import type { NavArea, NavAreaItem } from '@/lib/navigation-areas';
import { dashboard } from '@/routes';
import type { SharedData } from '@/types';
import AppLogo from './app-logo';

interface AreaSubNavigationProps {
    area: NavArea;
    activeItem: NavAreaItem | null;
    approvalsPendingCount: number;
}

function AreaSubNavigation({
    area,
    activeItem,
    approvalsPendingCount,
}: AreaSubNavigationProps) {
    return (
        <SidebarMenuSub>
            {area.groups.map((group, groupIndex) => (
                <React.Fragment key={group.label ?? groupIndex}>
                    {group.label && (
                        <SidebarMenuSubItem>
                            <span className="px-2 text-[10px] font-bold tracking-wider text-muted-foreground/70 uppercase">
                                {group.label}
                            </span>
                        </SidebarMenuSubItem>
                    )}
                    {group.items.map((item) => (
                        <SidebarMenuSubItem key={item.href}>
                            <SidebarMenuSubButton
                                asChild
                                isActive={activeItem?.href === item.href}
                                aria-current={
                                    activeItem?.href === item.href
                                        ? 'page'
                                        : undefined
                                }
                            >
                                <Link href={item.href} prefetch>
                                    <span>{item.title}</span>
                                    {item.badge === 'approvals' &&
                                        approvalsPendingCount > 0 && (
                                            <span className="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-black text-primary-foreground">
                                                {approvalsPendingCount}
                                            </span>
                                        )}
                                </Link>
                            </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                    ))}
                </React.Fragment>
            ))}
        </SidebarMenuSub>
    );
}

function SidebarPinToggle() {
    const { state, setOpen, isMobile } = useSidebar();
    const [isPinned, setIsPinned] = React.useState(() => {
        if (typeof window === 'undefined') {
            return true;
        }

        return localStorage.getItem('sidebar-pinned') !== 'false';
    });

    React.useEffect(() => {
        localStorage.setItem('sidebar-pinned', String(isPinned));
    }, [isPinned]);

    // Hover logic: when not pinned and collapsed, expand on hover
    const handleMouseEnter = React.useCallback(() => {
        if (!isPinned && state === 'collapsed' && !isMobile) {
            setOpen(true);
        }
    }, [isPinned, state, isMobile, setOpen]);
    const handleMouseLeave = React.useCallback(() => {
        if (!isPinned && state === 'expanded' && !isMobile) {
            setOpen(false);
        }
    }, [isPinned, state, isMobile, setOpen]);

    // Attach to sidebar element via data attribute
    React.useEffect(() => {
        const sidebar = document.querySelector('[data-sidebar="sidebar"]');

        if (!sidebar) {
            return;
        }

        sidebar.addEventListener('mouseenter', handleMouseEnter);
        sidebar.addEventListener('mouseleave', handleMouseLeave);

        return () => {
            sidebar.removeEventListener('mouseenter', handleMouseEnter);
            sidebar.removeEventListener('mouseleave', handleMouseLeave);
        };
    }, [handleMouseEnter, handleMouseLeave]);

    if (isMobile) {
        return null;
    }

    return (
        <button
            onClick={() => {
                const next = !isPinned;
                setIsPinned(next);

                if (next) {
                    setOpen(true);
                } else {
                    setOpen(false);
                }
            }}
            className="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-hidden"
            title={isPinned ? 'Desfijar sidebar' : 'Fijar sidebar'}
            aria-label={isPinned ? 'Sidebar fijado' : 'Sidebar flotante'}
            aria-pressed={isPinned}
        >
            {isPinned ? (
                <Pin className="h-4 w-4 text-primary" />
            ) : (
                <PinOff className="h-4 w-4" />
            )}
        </button>
    );
}

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
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <div className="flex items-center justify-between px-2 pt-2 group-data-[collapsible=icon]:justify-center">
                    <span className="text-[10px] font-black tracking-widest text-muted-foreground uppercase group-data-[collapsible=icon]:hidden">
                        Áreas
                    </span>
                    <SidebarPinToggle />
                </div>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup>
                    <SidebarMenu>
                        {navAreas.map((area) => {
                            const isAreaActive = activeArea?.key === area.key;

                            return (
                                <SidebarMenuItem key={area.key}>
                                    <SidebarMenuButton
                                        asChild
                                        tooltip={area.title}
                                        isActive={isAreaActive}
                                        aria-current={
                                            isAreaActive ? 'page' : undefined
                                        }
                                    >
                                        <Link href={area.href} prefetch>
                                            <area.icon className="h-4 w-4" />
                                            <span>{area.title}</span>
                                        </Link>
                                    </SidebarMenuButton>

                                    {isAreaActive && (
                                        <AreaSubNavigation
                                            area={area}
                                            activeItem={activeItem}
                                            approvalsPendingCount={
                                                approvals_pending_count
                                            }
                                        />
                                    )}
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
