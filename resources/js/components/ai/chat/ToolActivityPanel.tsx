import { CheckCircle2, ChevronDown, Loader2, XCircle } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { groupTools } from '@/lib/chat-tools';
import { cn } from '@/lib/utils';
import type { ToolActivity } from '@/types/chat';

interface ToolActivityPanelProps {
    tools: ToolActivity[];
}

export function ToolActivityPanel({ tools }: ToolActivityPanelProps) {
    const [userOpen, setUserOpen] = useState<boolean | null>(null);

    const groups = groupTools(tools);
    const runningGroup = [...groups].reverse().find((group) => group.running);
    const total = tools.length;
    const failedTotal = groups.reduce(
        (sum, group) => sum + group.failedCount,
        0,
    );
    const allDone = total > 0 && runningGroup === undefined;
    const open = userOpen ?? !allDone;

    if (total === 0) return null;

    const header =
        runningGroup !== undefined
            ? `${runningGroup.label}${
                  runningGroup.count > 1 ? ` ×${runningGroup.count}` : ''
              }`
            : `Usó ${total} ${total === 1 ? 'herramienta' : 'herramientas'}`;

    return (
        <Collapsible
            open={open}
            onOpenChange={setUserOpen}
            className="mb-2 rounded-xl border border-border bg-card/60"
        >
            <CollapsibleTrigger className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-muted-foreground hover:text-foreground">
                {runningGroup !== undefined ? (
                    <Loader2 className="h-3.5 w-3.5 animate-spin text-primary motion-reduce:animate-none" />
                ) : failedTotal > 0 ? (
                    <XCircle className="h-3.5 w-3.5 text-destructive" />
                ) : (
                    <CheckCircle2 className="h-3.5 w-3.5 text-primary" />
                )}

                <span aria-live="polite">{header}</span>

                {runningGroup !== undefined && groups.length > 1 && (
                    <span>· {groups.length} herramientas</span>
                )}

                {runningGroup === undefined && failedTotal > 0 && (
                    <span className="text-destructive">
                        · {failedTotal}{' '}
                        {failedTotal === 1 ? 'falló' : 'fallaron'}
                    </span>
                )}

                <ChevronDown
                    className={cn(
                        'ml-auto h-3.5 w-3.5 transition-transform',
                        open && 'rotate-180',
                    )}
                />
            </CollapsibleTrigger>

            <CollapsibleContent>
                <div className="flex flex-col gap-1.5 border-t border-border px-3 py-2">
                    {groups.map((group) => (
                        <span
                            key={group.name}
                            className="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            {group.running ? (
                                <Loader2 className="h-3.5 w-3.5 animate-spin text-primary motion-reduce:animate-none" />
                            ) : group.failedCount > 0 ? (
                                <XCircle className="h-3.5 w-3.5 text-destructive" />
                            ) : (
                                <CheckCircle2 className="h-3.5 w-3.5 text-primary" />
                            )}

                            {group.label}

                            {group.count > 1 && (
                                <span className="text-foreground/70">
                                    ×{group.count}
                                </span>
                            )}

                            {group.failedCount > 0 && (
                                <span className="text-destructive">
                                    · {group.failedCount}{' '}
                                    {group.failedCount === 1
                                        ? 'falló'
                                        : 'fallaron'}
                                </span>
                            )}
                        </span>
                    ))}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
