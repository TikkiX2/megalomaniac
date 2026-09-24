import { Link, router } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Pin, PinOff, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { ChatThread } from '@/types/chat';

interface ThreadItemProps {
    thread: ChatThread;
    active: boolean;
}

export function ThreadItem({ thread, active }: ThreadItemProps) {
    const [renaming, setRenaming] = useState(false);
    const [title, setTitle] = useState(thread.title);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const commitRename = () => {
        const trimmed = title.trim();

        setRenaming(false);

        if (trimmed === '' || trimmed === thread.title) {
            setTitle(thread.title);

            return;
        }

        router.patch(ChatController.update.url(thread.id), { title: trimmed }, { preserveScroll: true, preserveState: true });
    };

    const togglePinned = () => {
        router.patch(
            ChatController.update.url(thread.id),
            { pinned: !thread.is_pinned },
            { preserveScroll: true, preserveState: true },
        );
    };

    const destroy = () => {
        setConfirmOpen(false);
        router.delete(ChatController.destroy.url(thread.id), { preserveScroll: true });
    };

    if (renaming) {
        return (
            <Input
                autoFocus
                value={title}
                onChange={(event) => setTitle(event.target.value)}
                onBlur={commitRename}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') commitRename();
                    if (event.key === 'Escape') {
                        setTitle(thread.title);
                        setRenaming(false);
                    }
                }}
                className="h-8 border-border bg-card text-xs"
                aria-label="Renombrar hilo"
            />
        );
    }

    return (
        <div className={cn('group flex items-center gap-1 rounded-lg pr-1', active ? 'bg-primary/15' : 'hover:bg-muted/40')}>
            <Link
                href={ChatController.show(thread.id).url}
                className={cn(
                    'min-w-0 flex-1 truncate px-2 py-1.5 text-xs transition-colors',
                    active ? 'font-bold text-primary' : 'text-foreground',
                )}
            >
                {thread.is_pinned && <Pin className="mr-1 inline h-3 w-3 text-primary" />}
                {thread.title}
            </Link>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6 shrink-0 text-muted-foreground opacity-0 focus-visible:opacity-100 group-hover:opacity-100"
                        aria-label={`Acciones de ${thread.title}`}
                    >
                        <MoreHorizontal className="h-3.5 w-3.5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="border-border bg-card">
                    <DropdownMenuItem onSelect={() => setRenaming(true)}>
                        <Pencil className="mr-2 h-3.5 w-3.5" />
                        Renombrar
                    </DropdownMenuItem>
                    <DropdownMenuItem onSelect={togglePinned}>
                        {thread.is_pinned ? <PinOff className="mr-2 h-3.5 w-3.5" /> : <Pin className="mr-2 h-3.5 w-3.5" />}
                        {thread.is_pinned ? 'Desfijar' : 'Fijar'}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onSelect={() => setConfirmOpen(true)} className="text-destructive focus:text-destructive">
                        <Trash2 className="mr-2 h-3.5 w-3.5" />
                        Eliminar
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent className="border-border bg-card sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Eliminar hilo</DialogTitle>
                        <DialogDescription>
                            Se eliminarán “{thread.title}” y todos sus mensajes. No se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button variant="ghost" onClick={() => setConfirmOpen(false)}>
                            Cancelar
                        </Button>
                        <Button onClick={destroy} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Eliminar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
