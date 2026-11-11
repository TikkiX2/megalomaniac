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
        <SidebarMenuSub className="lg:hidden">
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
                <div className="px-2 pt-2 group-data-[collapsible=icon]:hidden">
                    <span className="text-[10px] font-black tracking-widest text-muted-foreground uppercase group-data-[collapsible=icon]:hidden">
                        Áreas
                    </span>
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
