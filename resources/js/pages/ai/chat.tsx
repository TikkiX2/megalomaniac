import { Head, Link } from '@inertiajs/react';
import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';
import MainLayout from '@/layouts/main-layout';
import type { AiChatState, ChatThread } from '@/types/chat';

interface ChatIndexProps {
    threads: ChatThread[];
    models: string[];
    ai: AiChatState;
}

export default function ChatIndex({ threads, ai }: ChatIndexProps) {
    return (
        <MainLayout>
            <Head title="Chat IA" />
            <div className="mx-auto w-full max-w-3xl p-6">
                <h1 className="text-2xl font-black tracking-tight text-foreground">Chat IA</h1>

                {!ai.configured && (
                    <p className="mt-2 text-sm text-muted-foreground">
                        Configura tu proveedor de IA en{' '}
                        <Link href="/settings/ai" className="text-primary hover:underline">
                            Settings → IA
                        </Link>
                        .
                    </p>
                )}

                <ul className="mt-6 space-y-1">
                    {threads.map((thread) => (
                        <li key={thread.id}>
                            <Link
                                href={ChatController.show(thread.id).url}
                                className="block truncate rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground transition-colors hover:border-primary/40"
                            >
                                {thread.title}
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </MainLayout>
    );
}
