import { Head, Link, router } from '@inertiajs/react';
import { Bot } from 'lucide-react';
import { useRef, useState } from 'react';
import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';
import { Composer } from '@/components/ai/chat/Composer';
import { ProviderNotice } from '@/components/ai/chat/ProviderNotice';
import { useChatStream } from '@/hooks/use-chat-stream';
import ChatLayout from '@/layouts/chat-layout';
import type { AiChatState, ChatThread } from '@/types/chat';

interface ChatIndexProps {
    threads: ChatThread[];
    models: string[];
    ai: AiChatState;
}

const SUGGESTIONS = [
    '¿Cómo va mi progreso de entrenamiento este mes?',
    'Resume mis gastos de la última semana',
    'Sugiere una cena alta en proteína con lo que tengo en casa',
    '¿Qué tareas tengo pendientes con fecha límite próxima?',
];

export default function ChatIndex({ threads, models, ai }: ChatIndexProps) {
    const [model, setModel] = useState<string | null>(ai.defaultModel ?? models[0] ?? null);
    const createdThreadRef = useRef<string | null>(null);

    const stream = useChatStream({
        onThread: (threadId) => {
            createdThreadRef.current = threadId;
        },
        onComplete: () => {
            const threadId = createdThreadRef.current;

            if (threadId) {
                router.visit(ChatController.show.url(threadId), { replace: true });
            }
        },
    });

    const submit = (message: string) => {
        stream.start(ChatController.send.url(), {
            message,
            model: model ?? undefined,
        });
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
                        large
                        autoFocus
                        models={models}
                        model={model}
                        onModelChange={setModel}
                        onSubmit={submit}
                        onStop={stream.stop}
                        streaming={stream.status === 'streaming'}
                        disabled={!ai.configured}
                    />

                    {stream.error && (
                        <p className="mt-2 text-center text-xs text-destructive" role="alert">
                            {stream.error}
                        </p>
                    )}

                    <div className="mt-4 flex flex-wrap justify-center gap-2">
                        {SUGGESTIONS.map((suggestion) => (
                            <button
                                key={suggestion}
                                type="button"
                                disabled={!ai.configured || stream.status === 'streaming'}
                                onClick={() => submit(suggestion)}
                                className="rounded-full border border-border bg-card px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:border-primary/40 hover:text-foreground disabled:opacity-40"
                            >
                                {suggestion}
                            </button>
                        ))}
                    </div>

                    {stream.status === 'streaming' && stream.text !== '' && (
                        <div className="mt-6 rounded-2xl border border-border bg-card p-4">
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
