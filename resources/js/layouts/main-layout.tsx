import { Link, usePage } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import type { ReactNode } from 'react';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { MobileBottomNav } from '@/components/mobile-bottom-nav';
import { SidebarAreaNav } from '@/components/sidebar-area-nav';
import {
    SidebarInset,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import { findActiveArea } from '@/lib/navigation-areas';

interface MainLayoutProps {
    children: ReactNode;
}

const chatSurfacePrefixes = [
    '/ai/chat',
    '/ai/gym',
    '/ai/nutrition',
    '/ai/grocery',
    '/ai/finance',
    '/ai/freelance',
    '/ai/health',
    '/ai/people',
];

function isChatSurface(pathname: string): boolean {
    return chatSurfacePrefixes.some(
        (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`),
    );
}

function AreaAwareContent({
    pathname,
    children,
}: MainLayoutProps & { pathname: string }) {
    const { state } = useSidebar();
    const activeArea = findActiveArea(pathname);
    const showAreaNav =
        state === 'expanded' && activeArea !== null && !isChatSurface(pathname);

    return (
        <div className="flex h-[calc(100svh-3rem)] min-h-0 flex-1 overflow-hidden">
            {showAreaNav && <SidebarAreaNav area={activeArea} />}
            <div
                scroll-region="true"
                className="flex min-w-0 flex-1 flex-col overflow-auto pb-24 md:pb-0"
            >
                {children}
            </div>
        </div>
    );
}

export default function MainLayout({ children }: MainLayoutProps) {
    const { url } = usePage();
    const pathname = url.split('?')[0];
    const isChatRoute = url.startsWith('/ai/chat');

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <SidebarInset className="bg-background">
                <header className="sticky top-0 z-10 flex h-12 shrink-0 items-center gap-2 border-b border-border bg-background px-4">
                    <SidebarTrigger className="-ml-1" />
                    <div className="mx-2 h-4 w-px bg-border" />
                    <span className="hidden text-sm font-black tracking-tight text-muted-foreground sm:inline">
                        Megalomaniac Pro · Panel
                    </span>
                </header>
                <AreaAwareContent pathname={pathname}>
                    {children}
                </AreaAwareContent>
            </SidebarInset>
            <MobileBottomNav />

            {/* AI Chat Toggle */}
            {!isChatRoute && (
                <Link
                    href="/ai/chat"
                    className="fixed right-4 bottom-24 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-white shadow-lg shadow-primary/30 transition-all hover:scale-105 hover:bg-primary/90 active:scale-95 md:right-6 md:bottom-6"
                    aria-label="Abrir chat IA"
                >
                    <MessageSquare className="h-6 w-6" />
                </Link>
            )}
        </AppShell>
    );
}
