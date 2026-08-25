import type { ReactNode } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { SidebarInset, SidebarTrigger } from '@/components/ui/sidebar';

interface MainLayoutProps {
    children: ReactNode;
}

export default function MainLayout({ children }: MainLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <SidebarInset className="bg-background">
                <header className="flex h-12 shrink-0 items-center gap-2 border-b border-border bg-background px-4 sticky top-0 z-10">
                    <SidebarTrigger className="-ml-1" />
                    <div className="h-4 w-px bg-border mx-2" />
                    <span className="text-sm font-black tracking-tight text-muted-foreground hidden sm:inline">Megalomaniac Pro · Panel</span>
                </header>
                <div className="flex flex-1 flex-col overflow-auto">
                    {children}
                </div>
            </SidebarInset>
        </AppShell>
    );
}
