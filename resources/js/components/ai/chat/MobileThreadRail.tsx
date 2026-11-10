import { History } from 'lucide-react';
import { ThreadRail } from '@/components/ai/chat/ThreadRail';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import type { ChatThread } from '@/types/chat';

interface MobileThreadRailProps {
    threads: ChatThread[];
    activeThreadId: string | null;
}

/**
 * Historial de hilos en móvil: el rail vive en una hoja lateral porque la
 * columna de escritorio (`lg:block` del `ChatLayout`) no cabe en pantalla.
 */
export function MobileThreadRail({
    threads,
    activeThreadId,
}: MobileThreadRailProps) {
    return (
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
            <SheetContent
                side="left"
                className="w-72 border-border bg-background p-0"
            >
                <SheetTitle className="sr-only">Historial de hilos</SheetTitle>
                <ThreadRail threads={threads} activeThreadId={activeThreadId} />
            </SheetContent>
        </Sheet>
    );
}
