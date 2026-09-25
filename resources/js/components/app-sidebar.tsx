import { Link, usePage } from '@inertiajs/react';
import { Bot, Brain, Briefcase, CheckSquare, CreditCard, Dumbbell, FileText, FolderKanban, LayoutGrid, Newspaper, Pill, Pin, PinOff, ShieldCheck, ShoppingCart, Users, Utensils, Wallet } from 'lucide-react';
import * as React from 'react';
import { NavFooter } from '@/components/nav-footer';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenuSub,
    SidebarMenuSubItem,
    SidebarMenuSubButton,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import freelance from '@/routes/freelance';
import type { NavItem, SharedData } from '@/types';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    { title: 'Panel', href: dashboard(), icon: LayoutGrid },
    { title: 'Entrenamiento', href: '/fitness/gym', icon: Dumbbell },
    { title: 'Nutrición', href: '/fitness/nutrition', icon: Utensils },
    { title: 'Suplementos', href: '/fitness/supplements', icon: Pill },
    { title: 'Compras', href: '/fitness/groceries', icon: ShoppingCart },
];

const freelanceNavItems: NavItem[] = [
    { title: 'Clientes', href: freelance.clients.index().url, icon: Users },
    { title: 'Proyectos', href: freelance.projects.index().url, icon: Briefcase },
    { title: 'Cotizaciones', href: freelance.quotes.index().url, icon: FileText },
];

const financeNavItems: NavItem[] = [
    { title: 'Finanzas', href: '/finance/dashboard', icon: Wallet },
    { title: 'Compras', href: '/finance/purchases', icon: ShoppingCart },
    { title: 'Ingresos', href: '/finance/incomes', icon: CreditCard },
];

const personalNavItems: NavItem[] = [
    { title: 'Tareas', href: '/personal/tasks', icon: CheckSquare },
    { title: 'Proyectos', href: '/personal/projects', icon: FolderKanban },
];

function SidebarPinToggle() {
    const { state, setOpen } = useSidebar();
    const [isPinned, setIsPinned] = React.useState(() => {
        if (typeof window === 'undefined') return true;
        return localStorage.getItem('sidebar-pinned') !== 'false';
    });

    React.useEffect(() => {
        localStorage.setItem('sidebar-pinned', String(isPinned));
    }, [isPinned]);

    // Hover logic: when not pinned and collapsed, expand on hover
    const { isMobile } = useSidebar();
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
        if (!sidebar) return;
        sidebar.addEventListener('mouseenter', handleMouseEnter);
        sidebar.addEventListener('mouseleave', handleMouseLeave);
        return () => {
            sidebar.removeEventListener('mouseenter', handleMouseEnter);
            sidebar.removeEventListener('mouseleave', handleMouseLeave);
        };
    }, [handleMouseEnter, handleMouseLeave]);

    return (
        <button
            onClick={() => {
                const next = !isPinned;
                setIsPinned(next);
                if (next) setOpen(true);
                else setOpen(false);
            }}
            className="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
            title={isPinned ? 'Desfijar sidebar' : 'Fijar sidebar'}
            aria-label={isPinned ? 'Sidebar fijado' : 'Sidebar flotante'}
        >
            {isPinned ? <Pin className="h-4 w-4 text-primary" /> : <PinOff className="h-4 w-4" />}
        </button>
    );
}

export function AppSidebar() {
    const { state } = useSidebar();
    const isCollapsed = state === 'collapsed';
    const { approvals_pending_count } = usePage<
        SharedData & { approvals_pending_count: number }
    >().props;
    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                                {!isCollapsed && <span className="ml-2 truncate font-semibold">Megalomaniac Pro</span>}
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <div className="flex items-center justify-between px-2 pt-2">
                    <span className="text-[10px] font-black uppercase tracking-widest text-muted-foreground group-data-[collapsible=icon]:hidden">Navegación</span>
                    <SidebarPinToggle />
                </div>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupLabel className="group-data-[collapsible=icon]:hidden">Principal</SidebarGroupLabel>
                    <SidebarMenu>
                        {mainNavItems.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton asChild tooltip={item.title} isActive={window.location.pathname === item.href}>
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon className="h-4 w-4" />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>

                <SidebarGroup>
                    <SidebarGroupLabel className="group-data-[collapsible=icon]:hidden">Freelance</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Freelance Panel">
                                <Link href={freelance.dashboard().url} prefetch>
                                    <Briefcase className="h-4 w-4" />
                                    <span>Panel Freelance</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuSub>
                            {freelanceNavItems.map((item) => (
                                <SidebarMenuSubItem key={item.title}>
                                    <SidebarMenuSubButton asChild>
                                        <Link href={item.href} prefetch>
                                            {item.icon && <item.icon className="h-4 w-4" />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            ))}
                        </SidebarMenuSub>
                    </SidebarMenu>
                </SidebarGroup>

                <SidebarGroup>
                    <SidebarGroupLabel className="group-data-[collapsible=icon]:hidden">Finanzas</SidebarGroupLabel>
                    <SidebarMenu>
                        {financeNavItems.map((item) => (
                            <SidebarMenuItem key={item.title + item.href}>
                                <SidebarMenuButton asChild tooltip={item.title}>
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon className="h-4 w-4" />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>

                <SidebarGroup>
                    <SidebarGroupLabel className="group-data-[collapsible=icon]:hidden">Personal</SidebarGroupLabel>
                    <SidebarMenu>
                        {personalNavItems.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton asChild tooltip={item.title} isActive={window.location.pathname === item.href}>
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon className="h-4 w-4" />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>

                <SidebarGroup>
                    <SidebarGroupLabel className="group-data-[collapsible=icon]:hidden">Asistente IA</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Asistente IA" isActive={window.location.pathname.startsWith('/ai/chat')}>
                                <Link href="/ai/chat" prefetch>
                                    <Brain className="h-4 w-4" />
                                    <span>Chat IA</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Aprobaciones" isActive={window.location.pathname.startsWith('/integrations/approvals')}>
                                <Link href="/integrations/approvals" prefetch>
                                    <ShieldCheck className="h-4 w-4" />
                                    <span>Aprobaciones</span>
                                    {approvals_pending_count > 0 && (
                                        <span className="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-black text-primary-foreground">
                                            {approvals_pending_count}
                                        </span>
                                    )}
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Agentes" isActive={window.location.pathname.startsWith('/agents')}>
                                <Link href="/agents" prefetch>
                                    <Bot className="h-4 w-4" />
                                    <span>Agentes</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Feed" isActive={window.location.pathname.startsWith('/feed')}>
                                <Link href="/feed" prefetch>
                                    <Newspaper className="h-4 w-4" />
                                    <span>Feed</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={[]} className="hidden" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
