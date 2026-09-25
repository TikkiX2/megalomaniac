import { Head, router } from '@inertiajs/react';
import { MoreHorizontal, Pin, PinOff, RotateCcw, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';
import { Composer } from '@/components/ai/chat/Composer';
import { MessageList } from '@/components/ai/chat/MessageList';
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
import { useChatStream } from '@/hooks/use-chat-stream';
import ChatLayout from '@/layouts/chat-layout';
import type { AiChatState, ChatMessage, ChatThread } from '@/types/chat';

interface ChatThreadProps {
    thread: ChatThread;
    messages: ChatMessage[];
    threads: ChatThread[];
    models: string[];
    ai: AiChatState;
}

export default function ChatThread({ thread, messages, threads, models, ai }: ChatThreadProps) {
    const [model, setModel] = useState<string | null>(thread.model ?? ai.defaultModel ?? models[0] ?? null);
    const [renaming, setRenaming] = useState(false);
    const [title, setTitle] = useState(thread.title);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [pendingMessage, setPendingMessage] = useState<string | null>(null);
    const [composerSeed, setComposerSeed] = useState('');
    const [draftToken, setDraftToken] = useState(0);

    const pendingMessageRef = useRef<string | null>(null);
    const lastErrorRef = useRef<string | null>(null);

    const stream = useChatStream({
        onError: (message, recoverable) => {
            if (recoverable || lastErrorRef.current === message) return;

            lastErrorRef.current = message;

            if (pendingMessageRef.current === null) return;

            setComposerSeed(pendingMessageRef.current);
            setDraftToken((token) => token + 1);
        },
        onComplete: () => {
            router.reload({
                only: ['threads', 'messages'],
                onSuccess: () => {
                    stream.reset();
                    pendingMessageRef.current = null;
                    setPendingMessage(null);
                },
            });
        },
    });

    const startSend = (message: string) => {
        pendingMessageRef.current = message;
        lastErrorRef.current = null;
        setPendingMessage(message);
        setComposerSeed('');
        stream.start(ChatController.send.url(), {
            message,
            thread_id: thread.id,
            model: model ?? undefined,
        });
    };

    const submit = (message: string) => startSend(message);

    const retry = () => {
        const message = pendingMessageRef.current;

        if (message === null) return;

        setComposerSeed('');
        setDraftToken((token) => token + 1);
        startSend(message);
    };

    const clearPending = () => {
        pendingMessageRef.current = null;
        setPendingMessage(null);
    };

    const regenerate = () => {
        clearPending();
        stream.start(ChatController.regenerate.url(thread.id), {});
    };

    const edit = (messageId: string, content: string) => {
        clearPending();
        stream.start(ChatController.edit.url(thread.id), { message_id: messageId, content });
    };

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

    return (
        <ChatLayout
            threads={threads}
            activeThreadId={thread.id}
            header={
                <>
                    {renaming ? (
                        <Input
                            autoFocus
                            maxLength={120}
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
                            className="h-8 max-w-sm border-border bg-card text-sm"
                            aria-label="Renombrar hilo"
                        />
                    ) : (
                        <button
                            type="button"
                            onClick={() => setRenaming(true)}
                            className="min-w-0 truncate text-sm font-bold text-foreground hover:text-primary"
                            title={thread.title}
                        >
                            {thread.title}
                        </button>
                    )}

                    {thread.is_pinned && <Pin className="h-3.5 w-3.5 shrink-0 text-primary" />}

                    <div className="ml-auto flex items-center gap-1">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8 text-muted-foreground hover:text-foreground"
                                    aria-label="Acciones del hilo"
                                >
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="border-border bg-card">
                                <DropdownMenuItem onSelect={() => setRenaming(true)}>Renombrar</DropdownMenuItem>
                                <DropdownMenuItem onSelect={togglePinned}>
                                    {thread.is_pinned ? <PinOff className="mr-2 h-3.5 w-3.5" /> : <Pin className="mr-2 h-3.5 w-3.5" />}
                                    {thread.is_pinned ? 'Desfijar' : 'Fijar'}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={() => setConfirmOpen(true)}
                                    className="text-destructive focus:text-destructive"
                                >
                                    <Trash2 className="mr-2 h-3.5 w-3.5" />
                                    Eliminar
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </>
            }
        >
            <Head title={thread.title} />

            <MessageList
                messages={messages}
                liveText={stream.text}
                liveCitations={stream.citations}
                liveTools={stream.tools}
                streaming={stream.status === 'streaming'}
                onRegenerate={regenerate}
                onEdit={edit}
                pendingUser={pendingMessage}
            />

            {stream.error && (
                <div className="mx-auto flex w-full max-w-3xl items-center justify-between gap-3 px-4" role="alert">
                    <p className="text-xs text-destructive">{stream.error}</p>

                    {pendingMessage !== null && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={retry}
                            className="h-7 shrink-0 border-border bg-card text-xs text-foreground hover:bg-muted"
                        >
                            <RotateCcw className="mr-1.5 h-3 w-3" />
                            Reintentar
                        </Button>
                    )}
                </div>
            )}

            <div className="shrink-0 border-t border-border bg-background/80 px-4 py-3 backdrop-blur">
                <div className="mx-auto w-full max-w-3xl">
                    <Composer
                        key={draftToken}
                        initialValue={composerSeed}
                        models={models}
                        model={model}
                        onModelChange={setModel}
                        onSubmit={submit}
                        onStop={stream.stop}
                        streaming={stream.status === 'streaming'}
                        disabled={!ai.configured}
                    />
                    <p className="mt-1.5 text-center text-[10px] text-muted-foreground">
                        La IA puede cometer errores. Verifica la información importante.
                    </p>
                </div>
            </div>

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
                        <Button
                            onClick={() => router.delete(ChatController.destroy.url(thread.id))}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            Eliminar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </ChatLayout>
    );
}
