import { cn } from '@/lib/utils';
import type { ConnectionStatusValue } from '@/types/integrations';

const styles: Record<ConnectionStatusValue, string> = {
    ok: 'bg-primary',
    error: 'bg-destructive',
    expired: 'bg-amber-500',
    unknown: 'bg-muted-foreground',
};

const labels: Record<ConnectionStatusValue, string> = {
    ok: 'Conectada',
    error: 'Error',
    expired: 'Vencida',
    unknown: 'Sin probar',
};

export default function StatusBadge({
    status,
    message,
}: {
    status: ConnectionStatusValue;
    message?: string | null;
}) {
    return (
        <span
            title={message ?? undefined}
            className="inline-flex items-center gap-1.5 rounded-full border border-border bg-background px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground"
        >
            <span className={cn('h-1.5 w-1.5 rounded-full', styles[status])} />
            {labels[status]}
        </span>
    );
}
