import { Brain, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';

interface ReasoningPanelProps {
    text: string;
    durationMs?: number | null;
    streaming?: boolean;
}

export function ReasoningPanel({ text, durationMs = null, streaming = false }: ReasoningPanelProps) {
    const [open, setOpen] = useState(streaming);

    if (text === '') return null;

    const label = streaming
        ? 'Pensando…'
        : durationMs ? `Pensó durante ${Math.max(1, Math.round(durationMs / 1000))}s` : 'Razonamiento';

    return (
        <Collapsible open={open} onOpenChange={setOpen} className="mb-2 rounded-xl border border-border bg-card/60">
            <CollapsibleTrigger className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-muted-foreground hover:text-foreground">
                <Brain className={cn('h-3.5 w-3.5 text-primary', streaming && 'animate-pulse motion-reduce:animate-none')} />
                <span>{label}</span>
                <ChevronDown className={cn('ml-auto h-3.5 w-3.5 transition-transform', open && 'rotate-180')} />
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div className="max-h-52 overflow-auto border-t border-border px-3 py-2 text-xs leading-relaxed whitespace-pre-wrap text-muted-foreground">
                    {text}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
