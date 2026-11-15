import { Link, usePage } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import type { ReactNode } from 'react';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { MobileBottomNav } from '@/components/mobile-bottom-nav';
import { SidebarInset, SidebarTrigger } from '@/components/ui/sidebar';

interface MainLayoutProps {
    children: ReactNode;
}

export default function MainLayout({ children }: MainLayoutProps) {
    const { url } = usePage();
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
                <div className="flex flex-1 flex-col overflow-auto pb-24 md:pb-0">
                    {children}
                </div>
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
