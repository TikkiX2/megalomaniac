import { ArrowDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AssistantMessage } from '@/components/ai/chat/AssistantMessage';
import { UserMessage } from '@/components/ai/chat/UserMessage';
import { Button } from '@/components/ui/button';
import type { ChatMessage, Citation, ToolActivity } from '@/types/chat';

interface MessageListProps {
    messages: ChatMessage[];
    liveText: string;
    liveCitations: Citation[];
    liveTools: ToolActivity[];
    streaming: boolean;
    onRegenerate: () => void;
    onEdit: (messageId: string, content: string) => void;
}

export function MessageList({
    messages,
    liveText,
    liveCitations,
    liveTools,
    streaming,
    onRegenerate,
    onEdit,
}: MessageListProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [pinned, setPinned] = useState(true);

    const scrollToBottom = (behavior: ScrollBehavior = 'auto') => {
        const container = containerRef.current;

        if (!container) return;

        container.scrollTo({ top: container.scrollHeight, behavior });
    };

    useEffect(() => {
        if (pinned) scrollToBottom();
    }, [messages, liveText, streaming, pinned]);

    const handleScroll = () => {
        const container = containerRef.current;

        if (!container) return;

        const distance = container.scrollHeight - container.scrollTop - container.clientHeight;
        setPinned(distance < 80);
    };

    const lastAssistantId = [...messages].reverse().find((message) => message.role === 'assistant')?.id ?? null;
    const showLive = streaming || liveText !== '';

    return (
        <div className="relative min-h-0 flex-1">
            <div ref={containerRef} onScroll={handleScroll} className="h-full overflow-y-auto">
                <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6">
                    {messages.map((message) =>
                        message.role === 'user' ? (
                            <UserMessage key={message.id} message={message} onEdit={onEdit} disabled={streaming} />
                        ) : (
                            <AssistantMessage
                                key={message.id}
                                content={message.content}
                                citations={message.citations}
                                onRegenerate={message.id === lastAssistantId ? onRegenerate : undefined}
                                disabled={streaming}
                            />
                        ),
                    )}

                    {showLive && (
                        <AssistantMessage
                            content={liveText}
                            citations={liveCitations}
                            tools={liveTools}
                            streaming={streaming}
                        />
                    )}
                </div>
            </div>

            {!pinned && (
                <Button
                    type="button"
                    size="icon"
                    onClick={() => scrollToBottom('smooth')}
                    aria-label="Ir al final"
                    className="absolute right-4 bottom-4 h-8 w-8 rounded-full border border-border bg-card text-foreground shadow-lg hover:bg-muted"
                >
                    <ArrowDown className="h-4 w-4" />
                </Button>
            )}
        </div>
    );
}
