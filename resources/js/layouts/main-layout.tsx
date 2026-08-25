import { useState } from 'react';
import type { ReactNode } from 'react';
import { MessageSquare } from 'lucide-react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { SidebarInset, SidebarTrigger } from '@/components/ui/sidebar';
import { ChatPanel } from '@/components/ai/ChatPanel';

interface MainLayoutProps {
    children: ReactNode;
}

export default function MainLayout({ children }: MainLayoutProps) {
    const [chatOpen, setChatOpen] = useState(false);

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

            {/* AI Chat Toggle */}
            <button
                onClick={() => setChatOpen((prev) => !prev)}
                className="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-white shadow-lg shadow-primary/30 transition-all hover:bg-primary/90 hover:scale-105 active:scale-95"
                aria-label="Abrir chat IA"
            >
                <MessageSquare className="h-6 w-6" />
            </button>

            <ChatPanel open={chatOpen} onClose={() => setChatOpen(false)} />
        </AppShell>
    );
}
