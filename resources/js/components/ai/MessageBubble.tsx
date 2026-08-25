import { Bot, User } from 'lucide-react';
import { cn } from '@/lib/utils';

interface MessageBubbleProps {
    role: 'user' | 'assistant';
    content: string;
    isStreaming?: boolean;
}

export function MessageBubble({ role, content, isStreaming }: MessageBubbleProps) {
    const isUser = role === 'user';

    return (
        <div className={cn('flex gap-3', isUser && 'flex-row-reverse')}>
            <div
                className={cn(
                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                    isUser ? 'bg-primary/20 text-primary' : 'bg-[#3e2121] text-[#e8b4b4]'
                )}
            >
                {isUser ? <User className="h-4 w-4" /> : <Bot className="h-4 w-4" />}
            </div>
            <div
                className={cn(
                    'max-w-[80%] rounded-xl px-4 py-2.5 text-sm leading-relaxed',
                    isUser
                        ? 'bg-primary text-white rounded-tr-sm'
                        : 'bg-[#2b1a1a] text-[#e8b4b4] border border-[#3e2121] rounded-tl-sm'
                )}
            >
                <div className="whitespace-pre-wrap break-words">
                    {content}
                    {isStreaming && (
                        <span className="inline-block h-4 w-1.5 animate-pulse bg-primary/70 ml-0.5 align-text-bottom" />
                    )}
                </div>
            </div>
        </div>
    );
}
