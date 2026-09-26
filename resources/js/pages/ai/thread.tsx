import { Head, router } from '@inertiajs/react';
import { FileText, MoreHorizontal, Pin, PinOff, RotateCcw, Trash2, X } from 'lucide-react';
import { useRef, useState } from 'react';
import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';
import { attachmentStatusLabel, formatBytes } from '@/components/ai/chat/AttachmentChips';
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
import { useAttachmentUpload } from '@/hooks/use-attachment-upload';
import { useChatStream } from '@/hooks/use-chat-stream';
import ChatLayout from '@/layouts/chat-layout';
import { cn } from '@/lib/utils';
import type {
    AiChatState,
    ChatAttachment,
    ChatMessage,
    ChatThread,
    DecideApproval,
    SourceMode,
    ToolPolicy,
} from '@/types/chat';

interface ChatThreadProps {
    thread: ChatThread;
    messages: ChatMessage[];
    documents: ChatAttachment[];
    threads: ChatThread[];
    models: string[];
    toolGroups: { key: string; label: string }[];
    ai: AiChatState;
}

export default function ChatThread({ thread, messages, documents, threads, models, toolGroups, ai }: ChatThreadProps) {
    const [model, setModel] = useState<string | null>(thread.model ?? ai.defaultModel ?? models[0] ?? null);
    const [toolsPolicy, setToolsPolicy] = useState<ToolPolicy>(thread.tools_policy ?? { mode: 'auto', groups: [] });
    const [forceWeb, setForceWeb] = useState(false);
    const [sourceMode, setSourceMode] = useState<SourceMode>(thread.mode);
    const [renaming, setRenaming] = useState(false);
    const [title, setTitle] = useState(thread.title);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [pendingMessage, setPendingMessage] = useState<string | null>(null);
    const [composerSeed, setComposerSeed] = useState('');
    const [draftToken, setDraftToken] = useState(0);

    const pendingMessageRef = useRef<string | null>(null);
    const lastErrorRef = useRef<string | null>(null);

    const upload = useAttachmentUpload(thread.id, documents);

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
                    upload.clearImages();
                },
            });
        },
    });

    const startSend = (message: string) => {
        const attachmentIds = upload.readyImageIds();

        pendingMessageRef.current = message;
        lastErrorRef.current = null;
        setPendingMessage(message);
        setComposerSeed('');
        stream.start(ChatController.send.url(), {
            message,
            thread_id: thread.id,
            model: model ?? undefined,
            tools_policy:
                toolsPolicy.mode === 'manual' && toolsPolicy.groups.length > 0
                    ? toolsPolicy
                    : undefined,
            force_web: forceWeb ? true : undefined,
            attachment_ids: attachmentIds.length > 0 ? attachmentIds : undefined,
        });
        setForceWeb(false);
    };

    const changeSourceMode = (mode: SourceMode) => {
        if (mode === 'local' || mode === 'off') setForceWeb(false);

        setSourceMode(mode);
        router.patch(ChatController.update.url(thread.id), { mode }, { preserveScroll: true, preserveState: true });
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

    const decide: DecideApproval = (id, action, payload) => {
        stream.start(ChatController.approve.url(thread.id), {
            decisions: {
                [id]: { action, ...payload },
            },
        });
    };

    const approveAll = (ids: string[]) => {
        if (ids.length === 0) return;

        stream.start(ChatController.approve.url(thread.id), {
            decisions: Object.fromEntries(ids.map((id) => [id, { action: 'approve' }])),
        });
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

    const composerAttachments = upload.attachments.filter((attachment) => attachment.kind !== 'document');
    const threadDocuments = upload.attachments.filter((attachment) => attachment.kind === 'document');
    const hasPendingApprovals =
        stream.pendingApprovals.length > 0 ||
        messages.some((message) => message.role === 'assistant' && message.pending_approvals.length > 0);

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
                liveReasoning={stream.reasoning}
                reasoningMs={stream.reasoningMs}
                liveApprovals={stream.pendingApprovals}
                streaming={stream.status === 'streaming'}
                onRegenerate={regenerate}
                onEdit={edit}
                onDecide={decide}
                onApproveAll={approveAll}
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
                        toolGroups={toolGroups}
                        toolsPolicy={toolsPolicy}
                        onToolsPolicyChange={setToolsPolicy}
                        sourceMode={sourceMode}
                        forceWeb={forceWeb}
                        onSourceModeChange={changeSourceMode}
                        onForceWebChange={setForceWeb}
                        hasTavilyKey={ai.has_tavily_key}
                        model={model}
                        onModelChange={setModel}
                        attachments={composerAttachments}
                        onAddFiles={upload.addFiles}
                        onRemoveAttachment={upload.remove}
                        onRetryAttachment={upload.retry}
                        uploading={upload.uploading}
                        attachmentFailed={upload.hasFailed}
                        attachmentError={upload.error}
                        onDismissAttachmentError={upload.dismissError}
                        onSubmit={submit}
                        onStop={stream.stop}
                        streaming={stream.status === 'streaming'}
                        disabled={!ai.configured || hasPendingApprovals}
                    />
                    <p className="mt-1.5 text-center text-[10px] text-muted-foreground">
                        La IA puede cometer errores. Verifica la información importante.
                    </p>

                    {threadDocuments.length > 0 && (
                        <div className="mt-2 rounded-xl border border-border bg-card/60 p-2">
                            <p className="px-1 text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                Documentos del hilo
                            </p>
                            <ul className="mt-1 space-y-0.5">
                                {threadDocuments.map((document) => (
                                    <li key={document.id} className="flex items-center gap-2 rounded-lg px-1 py-1">
                                        <FileText
                                            className={cn(
                                                'h-3.5 w-3.5 shrink-0',
                                                document.status === 'failed' ? 'text-destructive' : 'text-muted-foreground',
                                            )}
                                        />
                                        <span className="min-w-0 flex-1 truncate text-xs text-foreground" title={document.name}>
                                            {document.name}
                                        </span>
                                        <span
                                            className={cn(
                                                'max-w-40 shrink-0 truncate text-[10px]',
                                                document.status === 'failed' ? 'text-destructive' : 'text-muted-foreground',
                                            )}
                                            title={attachmentStatusLabel(document)}
                                        >
                                            {attachmentStatusLabel(document)}
                                        </span>
                                        <span className="shrink-0 text-[10px] tabular-nums text-muted-foreground">
                                            {formatBytes(document.size)}
                                        </span>

                                        {document.status === 'failed' && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => void upload.retry(document.id)}
                                                aria-label={`Reintentar ${document.name}`}
                                                className="h-6 w-6 text-destructive hover:text-destructive"
                                            >
                                                <RotateCcw className="h-3 w-3" />
                                            </Button>
                                        )}

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => void upload.remove(document.id)}
                                            aria-label={`Eliminar ${document.name}`}
                                            className="h-6 w-6 text-muted-foreground hover:text-foreground"
                                        >
                                            <X className="h-3 w-3" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
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
