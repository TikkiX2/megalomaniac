import { Check, Copy, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Markdown } from '@/components/ai/chat/Markdown';
import { ReasoningPanel } from '@/components/ai/chat/ReasoningPanel';
import { SourcesPanel } from '@/components/ai/chat/SourcesPanel';
import { StreamStatus } from '@/components/ai/chat/StreamStatus';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Citation, ToolActivity } from '@/types/chat';

interface AssistantMessageProps {
    content: string;
    citations: Citation[];
    tools?: ToolActivity[];
    reasoning?: string;
    reasoningMs?: number | null;
    streaming?: boolean;
    disabled?: boolean;
    onRegenerate?: () => void;
}

export function AssistantMessage({
    content,
    citations,
    tools = [],
    reasoning,
    reasoningMs,
    streaming = false,
    disabled = false,
    onRegenerate,
}: AssistantMessageProps) {
    const [copied, setCopied] = useState(false);
    const [activeCitation, setActiveCitation] = useState<number | null>(null);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(content);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            // clipboard no disponible
        }
    };

    const thinking = streaming && content === '';

    return (
        <article className="flex gap-3" aria-live="polite">
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/15 text-[10px] font-black text-primary">
                IA
            </div>

            <div className="min-w-0 flex-1">
                <StreamStatus thinking={thinking} tools={tools} />

                <ReasoningPanel
                    key={thinking ? 'reasoning-streaming' : 'reasoning-settled'}
                    text={reasoning ?? ''}
                    durationMs={reasoningMs}
                    streaming={thinking}
                />

                <Markdown
                    content={content}
                    citations={citations}
                    onCitationClick={setActiveCitation}
                    className={cn(thinking && 'hidden')}
                />

                {streaming && content !== '' && (
                    <span className="ml-0.5 inline-block h-4 w-1.5 animate-pulse bg-primary/70 align-text-bottom motion-reduce:animate-none" />
                )}

                <SourcesPanel citations={citations} activeIndex={activeCitation} />

                {!streaming && content !== '' && (
                    <div className="mt-2 flex items-center gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={copy}
                            aria-label="Copiar respuesta"
                            className="h-7 w-7 text-muted-foreground hover:text-foreground"
                        >
                            {copied ? <Check className="h-3.5 w-3.5 text-primary" /> : <Copy className="h-3.5 w-3.5" />}
                        </Button>

                        {onRegenerate && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={onRegenerate}
                                disabled={disabled}
                                aria-label="Regenerar respuesta"
                                className="h-7 w-7 text-muted-foreground hover:text-foreground"
                            >
                                <RefreshCw className="h-3.5 w-3.5" />
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </article>
    );
}
