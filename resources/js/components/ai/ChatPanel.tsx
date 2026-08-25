import { useCallback, useEffect, useRef, useState } from 'react';
import { X, Send, MessageSquare, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { MessageBubble } from '@/components/ai/MessageBubble';
import { cn } from '@/lib/utils';

interface Message {
    id: string;
    role: 'user' | 'assistant';
    content: string;
}

interface Conversation {
    id: string;
    title: string;
    updated_at: string;
}

interface ChatPanelProps {
    open: boolean;
    onClose: () => void;
}

export function ChatPanel({ open, onClose }: ChatPanelProps) {
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [conversations, setConversations] = useState<Conversation[]>([]);
    const [activeConversationId, setActiveConversationId] = useState<string | null>(null);
    const scrollRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const scrollToBottom = useCallback(() => {
        if (scrollRef.current) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
        }
    }, []);

    useEffect(() => {
        scrollToBottom();
    }, [messages, scrollToBottom]);

    useEffect(() => {
        if (open) {
            inputRef.current?.focus();
            fetchConversations();
        }
    }, [open]);

    const fetchConversations = async () => {
        try {
            const res = await fetch('/ai/conversations');
            if (res.ok) {
                setConversations(await res.json());
            }
        } catch {
            // silently fail
        }
    };

    const sendMessage = async () => {
        const text = input.trim();
        if (!text || loading) return;

        const userMsg: Message = {
            id: crypto.randomUUID(),
            role: 'user',
            content: text,
        };

        setMessages((prev) => [...prev, userMsg]);
        setInput('');
        setLoading(true);

        const assistantMsg: Message = {
            id: crypto.randomUUID(),
            role: 'assistant',
            content: '',
        };
        setMessages((prev) => [...prev, assistantMsg]);

        try {
            const body: Record<string, string> = { message: text };
            if (activeConversationId) {
                body.conversation_id = activeConversationId;
            }

            const res = await fetch('/ai/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((c) => c.startsWith('XSRF-TOKEN='))
                            ?.split('=')[1] ?? ''
                    ),
                },
                body: JSON.stringify(body),
            });

            if (!res.ok) {
                throw new Error('Chat request failed');
            }

            const reader = res.body?.getReader();
            if (!reader) throw new Error('No reader');

            const decoder = new TextDecoder();
            let accumulated = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                const chunk = decoder.decode(value, { stream: true });
                const lines = chunk.split('\n');

                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        const data = line.slice(6);
                        if (data === '[DONE]') break;

                        try {
                            const event = JSON.parse(data);
                            if (event.type === 'text.delta' && event.text) {
                                accumulated += event.text;
                                setMessages((prev) =>
                                    prev.map((m) =>
                                        m.id === assistantMsg.id
                                            ? { ...m, content: accumulated }
                                            : m
                                    )
                                );
                            }
                            if (event.type === 'meta' && event.conversationId) {
                                setActiveConversationId(event.conversationId);
                            }
                        } catch {
                            // skip non-JSON lines
                        }
                    }
                }
            }

            fetchConversations();
        } catch {
            setMessages((prev) =>
                prev.map((m) =>
                    m.id === assistantMsg.id
                        ? { ...m, content: 'Error al conectar con la IA. Inténtalo de nuevo.' }
                        : m
                )
            );
        } finally {
            setLoading(false);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    };

    const loadConversation = async (id: string) => {
        setActiveConversationId(id);
        setMessages([]);
    };

    if (!open) return null;

    return (
        <div className="fixed bottom-20 right-6 z-50 flex h-[500px] w-[400px] flex-col rounded-2xl border border-[#3e2121] bg-[#1c0f0f] shadow-2xl shadow-black/40 animate-in slide-in-from-bottom-5 fade-in duration-200">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-[#3e2121] px-4 py-3">
                <div className="flex items-center gap-2">
                    <div className="flex h-7 w-7 items-center justify-center rounded-full bg-primary/20">
                        <MessageSquare className="h-3.5 w-3.5 text-primary" />
                    </div>
                    <span className="text-sm font-bold text-white">Megalomaniac AI</span>
                </div>
                <Button variant="ghost" size="icon" onClick={onClose} className="h-7 w-7 text-muted-foreground hover:text-white">
                    <X className="h-4 w-4" />
                </Button>
            </div>

            {/* Conversations sidebar (collapsed) */}
            {conversations.length > 0 && (
                <div className="border-b border-[#3e2121] px-3 py-2">
                    <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
                        {conversations.slice(0, 5).map((c) => (
                            <button
                                key={c.id}
                                onClick={() => loadConversation(c.id)}
                                className={cn(
                                    'shrink-0 truncate rounded-lg px-3 py-1 text-xs font-medium transition-colors',
                                    activeConversationId === c.id
                                        ? 'bg-primary text-white'
                                        : 'bg-[#2b1a1a] text-[#e8b4b4] hover:bg-[#3e2121]'
                                )}
                            >
                                {c.title.slice(0, 20)}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {/* Messages */}
            <ScrollArea className="flex-1 px-4">
                <div ref={scrollRef} className="flex flex-col gap-4 py-4">
                    {messages.length === 0 && (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 mb-3">
                                <MessageSquare className="h-6 w-6 text-primary/60" />
                            </div>
                            <p className="text-sm font-medium text-muted-foreground">¿En qué puedo ayudarte?</p>
                            <p className="mt-1 text-xs text-muted-foreground/60">
                                Entrenamiento, nutrición, finanzas...
                            </p>
                        </div>
                    )}
                    {messages.map((msg) => (
                        <MessageBubble
                            key={msg.id}
                            role={msg.role}
                            content={msg.content}
                            isStreaming={loading && msg.role === 'assistant' && msg.content === ''}
                        />
                    ))}
                    {loading && messages[messages.length - 1]?.role === 'user' && (
                        <div className="flex gap-3">
                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#3e2121] text-[#e8b4b4]">
                                <Loader2 className="h-4 w-4 animate-spin" />
                            </div>
                            <div className="rounded-xl rounded-tl-sm bg-[#2b1a1a] border border-[#3e2121] px-4 py-3">
                                <div className="flex gap-1">
                                    <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-primary/40 [animation-delay:-0.3s]" />
                                    <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-primary/40 [animation-delay:-0.15s]" />
                                    <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-primary/40" />
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </ScrollArea>

            {/* Input */}
            <div className="border-t border-[#3e2121] p-3">
                <div className="flex gap-2">
                    <Input
                        ref={inputRef}
                        value={input}
                        onChange={(e) => setInput(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder="Escribe tu mensaje..."
                        disabled={loading}
                        className="bg-[#2b1a1a] border-[#3e2121] text-white placeholder:text-muted-foreground/50 focus-visible:ring-primary/30"
                    />
                    <Button
                        onClick={sendMessage}
                        disabled={!input.trim() || loading}
                        size="icon"
                        className="shrink-0 bg-primary hover:bg-primary/90"
                    >
                        <Send className="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
