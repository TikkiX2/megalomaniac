import { History } from 'lucide-react';
import type { ReactNode } from 'react';
import { ThreadRail } from '@/components/ai/chat/ThreadRail';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import MainLayout from '@/layouts/main-layout';
import type { ChatThread } from '@/types/chat';

interface ChatLayoutProps {
    threads: ChatThread[];
    activeThreadId: string | null;
    header?: ReactNode;
    children: ReactNode;
}

export default function ChatLayout({ threads, activeThreadId, header, children }: ChatLayoutProps) {
    return (
        <MainLayout>
            <div className="flex h-[calc(100dvh-3rem)]">
                <aside className="hidden w-72 shrink-0 border-r border-border lg:block">
                    <ThreadRail threads={threads} activeThreadId={activeThreadId} />
                </aside>

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="flex h-14 shrink-0 items-center gap-2 border-b border-border px-3 sm:px-4">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="lg:hidden"
                                    aria-label="Abrir historial de hilos"
                                >
                                    <History className="h-4 w-4" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="w-72 border-border bg-background p-0">
                                <SheetTitle className="sr-only">Historial de hilos</SheetTitle>
                                <ThreadRail threads={threads} activeThreadId={activeThreadId} />
                            </SheetContent>
                        </Sheet>

                        <div className="flex min-w-0 flex-1 items-center gap-2">{header}</div>
                    </header>

                    <div className="flex min-h-0 flex-1 flex-col">{children}</div>
                </div>
            </div>
        </MainLayout>
    );
}
