import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ThreadRail } from '@/components/ai/chat/ThreadRail';
import type { ChatThread } from '@/types/chat';

/**
 * Rail persistente de hilos: `MainLayout` aporta el shell y este layout la
 * columna fija del historial. `threads` y `thread` llegan como props de página
 * porque Inertia las pasa a cada layout del array.
 */
export default function ChatLayout({ children }: { children: ReactNode }) {
    const props = usePage().props as {
        threads?: ChatThread[];
        thread?: ChatThread | null;
    };
    const threads = props.threads ?? [];
    const activeThreadId = props.thread?.id ?? null;

    return (
        <div className="flex h-[calc(100dvh-3rem)]">
            <aside className="hidden w-72 shrink-0 border-r border-border lg:block">
                <ThreadRail threads={threads} activeThreadId={activeThreadId} />
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">{children}</div>
        </div>
    );
}
