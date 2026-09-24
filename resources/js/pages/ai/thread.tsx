import { Head } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import type { AiChatState, ChatMessage, ChatThread } from '@/types/chat';

interface ChatThreadProps {
    thread: ChatThread;
    messages: ChatMessage[];
    threads: ChatThread[];
    models: string[];
    ai: AiChatState;
}

export default function ChatThread({ thread, messages }: ChatThreadProps) {
    return (
        <MainLayout>
            <Head title={thread.title} />
            <div className="mx-auto w-full max-w-3xl p-6">
                <h1 className="truncate text-xl font-black tracking-tight text-foreground">{thread.title}</h1>

                <div className="mt-6 space-y-4">
                    {messages.map((message) => (
                        <div key={message.id} className="rounded-xl border border-border bg-card p-4">
                            <p className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                {message.role}
                            </p>
                            <p className="mt-1 whitespace-pre-wrap text-sm text-foreground">{message.content}</p>
                        </div>
                    ))}
                </div>
            </div>
        </MainLayout>
    );
}
