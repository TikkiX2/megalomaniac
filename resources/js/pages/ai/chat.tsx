import { Head, Link, router } from '@inertiajs/react';
import { Bot, RotateCcw } from 'lucide-react';
import { useRef, useState } from 'react';
import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';
import { Composer } from '@/components/ai/chat/Composer';
import { ProviderNotice } from '@/components/ai/chat/ProviderNotice';
import { Button } from '@/components/ui/button';
import { useAttachmentUpload } from '@/hooks/use-attachment-upload';
import { useChatStream } from '@/hooks/use-chat-stream';
import ChatLayout from '@/layouts/chat-layout';
import type { AiChatState, ChatThread, ToolPolicy } from '@/types/chat';

interface ChatIndexProps {
    threads: ChatThread[];
    models: string[];
    agents: { key: string; name: string }[];
    toolGroups: { key: string; label: string }[];
    ai: AiChatState;
}

const SUGGESTIONS = [
    '¿Cómo va mi progreso de entrenamiento este mes?',
    'Resume mis gastos de la última semana',
    'Sugiere una cena alta en proteína con lo que tengo en casa',
    '¿Qué tareas tengo pendientes con fecha límite próxima?',
];

export default function ChatIndex({ threads, models, agents, toolGroups, ai }: ChatIndexProps) {
    const [model, setModel] = useState<string | null>(ai.defaultModel ?? models[0] ?? null);
    const [agent, setAgent] = useState('megalomaniac');
    const [toolsPolicy, setToolsPolicy] = useState<ToolPolicy>({ mode: 'auto', groups: [] });
    const [pendingMessage, setPendingMessage] = useState<string | null>(null);
    const [composerSeed, setComposerSeed] = useState('');
    const [draftToken, setDraftToken] = useState(0);

    const createdThreadRef = useRef<string | null>(null);
    const pendingMessageRef = useRef<string | null>(null);
    const lastErrorRef = useRef<string | null>(null);

    const upload = useAttachmentUpload(null);

    const stream = useChatStream({
        onThread: (threadId) => {
            createdThreadRef.current = threadId;
        },
        onError: (message, recoverable) => {
            if (recoverable || lastErrorRef.current === message) return;

            lastErrorRef.current = message;

            if (pendingMessageRef.current === null) return;

            setComposerSeed(pendingMessageRef.current);
            setDraftToken((token) => token + 1);
        },
        onComplete: () => {
            const threadId = createdThreadRef.current;

            upload.clearImages();

            if (threadId) {
                router.visit(ChatController.show.url(threadId), { replace: true });
            }
        },
        onPaused: () => {
            // The paused turn is persisted by the time the stream settles, so
            // the thread page can render its pending_approvals cards.
            const threadId = createdThreadRef.current;

            if (threadId) {
                router.visit(ChatController.show.url(threadId), { replace: true });
            }
        },
    });

    const paused = stream.status === 'awaiting_approval';

    const startSend = (message: string) => {
        const attachmentIds = upload.readyIds();

        pendingMessageRef.current = message;
        lastErrorRef.current = null;
        setPendingMessage(message);
        setComposerSeed('');
        stream.start(ChatController.send.url(), {
            message,
            model: model ?? undefined,
            agent: agent !== 'megalomaniac' ? agent : undefined,
            tools_policy:
                toolsPolicy.mode === 'manual' && toolsPolicy.groups.length > 0
                    ? toolsPolicy
                    : undefined,
            attachment_ids: attachmentIds.length > 0 ? attachmentIds : undefined,
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

    return (
        <ChatLayout threads={threads} activeThreadId={null}>
            <Head title="Chat IA" />

            <div className="min-h-0 flex-1 overflow-y-auto">
                <div className="mx-auto flex w-full max-w-2xl flex-col px-4 pb-10 pt-10 sm:pt-16">
                    <div className="mb-6 flex flex-col items-center text-center">
                        <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/15">
                            <Bot className="h-6 w-6 text-primary" />
                        </div>
                        <h1 className="text-2xl font-black tracking-tight text-foreground sm:text-3xl">
                            ¿Qué quieres saber?
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Tu asistente con acceso a entrenamientos, finanzas, nutrición y freelance.
                        </p>
                    </div>

                    {!ai.configured && (
                        <div className="mb-4">
                            <ProviderNotice />
                        </div>
                    )}

                    <Composer
                        key={draftToken}
                        large
                        autoFocus
                        initialValue={composerSeed}
                        models={models}
                        model={model}
                        onModelChange={setModel}
                        agents={agents}
                        agent={agent}
                        onAgentChange={setAgent}
                        toolGroups={toolGroups}
                        toolsPolicy={toolsPolicy}
                        onToolsPolicyChange={setToolsPolicy}
                        attachments={upload.attachments}
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
                        disabled={!ai.configured || paused}
                    />

                    {stream.error && (
                        <div className="mt-2 flex items-center justify-center gap-3" role="alert">
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

                    <div className="mt-4 flex flex-wrap justify-center gap-2">
                        {SUGGESTIONS.map((suggestion) => (
                            <button
                                key={suggestion}
                                type="button"
                                disabled={!ai.configured || stream.status === 'streaming' || paused}
                                onClick={() => submit(suggestion)}
                                className="rounded-full border border-border bg-card px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:border-primary/40 hover:text-foreground disabled:opacity-40"
                            >
                                {suggestion}
                            </button>
                        ))}
                    </div>

                    {pendingMessage !== null && stream.status !== 'idle' && (
                        <div className="mt-6 flex justify-end">
                            <div className="max-w-[85%] whitespace-pre-wrap break-words rounded-2xl rounded-tr-sm border border-primary/20 bg-primary/10 px-4 py-2.5 text-sm text-foreground">
                                {pendingMessage}
                            </div>
                        </div>
                    )}

                    {stream.status === 'streaming' && stream.text !== '' && (
                        <div className="mt-3 rounded-2xl border border-border bg-card p-4">
                            <p className="whitespace-pre-wrap text-sm text-foreground">{stream.text}</p>
                        </div>
                    )}

                    {threads.length > 0 && (
                        <div className="mt-10">
                            <p className="mb-2 text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                Recientes
                            </p>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {threads.slice(0, 6).map((thread) => (
                                    <Link
                                        key={thread.id}
                                        href={ChatController.show(thread.id).url}
                                        className="truncate rounded-xl border border-border bg-card px-3 py-2.5 text-sm text-foreground transition-colors hover:border-primary/40"
                                    >
                                        {thread.title}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </ChatLayout>
    );
}
