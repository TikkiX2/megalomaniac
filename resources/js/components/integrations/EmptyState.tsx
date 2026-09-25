import type { ReactNode } from 'react';

export default function EmptyState({
    title,
    description,
    action,
}: {
    title: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-card/50 px-6 py-12 text-center">
            <p className="text-sm font-bold uppercase tracking-widest text-foreground">
                {title}
            </p>
            <p className="max-w-sm text-sm text-muted-foreground">{description}</p>
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
