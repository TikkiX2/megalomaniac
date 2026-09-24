import { CheckCircle2, Loader2, XCircle } from 'lucide-react';
import type { ToolActivity } from '@/types/chat';

interface StreamStatusProps {
    thinking: boolean;
    tools: ToolActivity[];
}

const TOOL_LABELS: Record<string, string> = {
    WorkoutQueryTool: 'Consultando entrenamientos',
    FinanceQueryTool: 'Consultando finanzas',
    NutritionQueryTool: 'Consultando nutrición',
    GroceryQueryTool: 'Consultando compras',
    ActionTool: 'Ejecutando acción',
};

export function StreamStatus({ thinking, tools }: StreamStatusProps) {
    if (!thinking && tools.length === 0) return null;

    return (
        <div className="flex flex-col gap-1.5" role="status" aria-live="polite">
            {thinking && (
                <span className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Loader2 className="h-3.5 w-3.5 animate-spin text-primary motion-reduce:animate-none" />
                    Pensando…
                </span>
            )}

            {tools.map((tool) => (
                <span key={tool.id} className="flex items-center gap-2 text-xs text-muted-foreground">
                    {tool.status === 'running' && (
                        <Loader2 className="h-3.5 w-3.5 animate-spin text-primary motion-reduce:animate-none" />
                    )}
                    {tool.status === 'done' && <CheckCircle2 className="h-3.5 w-3.5 text-primary" />}
                    {tool.status === 'failed' && <XCircle className="h-3.5 w-3.5 text-destructive" />}
                    {TOOL_LABELS[tool.name] ?? `Usando ${tool.name}`}
                </span>
            ))}
        </div>
    );
}
