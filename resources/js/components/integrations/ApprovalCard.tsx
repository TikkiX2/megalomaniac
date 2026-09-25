import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { ApprovalRow } from '@/types/integrations';

const accessLabels: Record<ApprovalRow['access'], string> = {
    read: 'Lectura',
    write: 'Escritura',
    destructive: 'Destructiva',
};

const accessStyles: Record<ApprovalRow['access'], string> = {
    read: 'border-border text-muted-foreground',
    write: 'border-amber-500/40 text-amber-500',
    destructive: 'border-destructive/40 text-destructive',
};

function relative(value: string | null): string {
    if (!value) {
        return '';
    }

    const diffMinutes = Math.round((new Date(value).getTime() - Date.now()) / 60000);
    const future = diffMinutes >= 0;
    const minutes = Math.abs(diffMinutes);

    if (minutes < 1) {
        return 'justo ahora';
    }

    const prefix = future ? 'en' : 'hace';

    if (minutes < 60) {
        return `${prefix} ${minutes} min`;
    }

    if (minutes < 1440) {
        return `${prefix} ${Math.round(minutes / 60)} h`;
    }

    return `${prefix} ${Math.round(minutes / 1440)} días`;
}

export default function ApprovalCard({
    approval,
    pending = true,
}: {
    approval: ApprovalRow;
    pending?: boolean;
}) {
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);

    const decide = (decision: 'approve' | 'reject') => {
        setProcessing(true);
        router.post(
            `/integrations/approvals/${approval.id}/${decision}`,
            { note: note || null },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <div className="space-y-3 rounded-xl border border-border bg-card p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="space-y-1">
                    <p className="text-sm font-bold text-foreground">{approval.summary}</p>
                    <p className="text-xs text-muted-foreground">
                        {approval.connection_name} · {approval.action_key}
                    </p>
                </div>
                <span
                    className={`rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest ${accessStyles[approval.access]}`}
                >
                    {accessLabels[approval.access]}
                </span>
            </div>

            {approval.rationale && (
                <p className="rounded-lg bg-background/60 p-2 text-xs italic text-muted-foreground">
                    “{approval.rationale}”
                </p>
            )}

            <div className="overflow-hidden rounded-lg border border-border">
                <table className="w-full text-left text-xs">
                    <tbody>
                        {Object.entries(approval.params).map(([key, value]) => (
                            <tr key={key} className="border-b border-border last:border-0">
                                <td className="w-1/3 bg-background/40 px-2 py-1 font-mono text-muted-foreground">
                                    {key}
                                </td>
                                <td className="px-2 py-1 font-mono text-foreground break-all">
                                    {typeof value === 'string'
                                        ? value === '[redacted]'
                                            ? '•••'
                                            : value
                                        : JSON.stringify(value)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {pending ? (
                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        placeholder="Nota (opcional)"
                        className="h-8 max-w-xs bg-background border-border text-xs"
                    />
                    <Button
                        size="sm"
                        className="bg-primary font-bold"
                        disabled={processing}
                        onClick={() => decide('approve')}
                    >
                        Aprobar
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        className="text-destructive"
                        disabled={processing}
                        onClick={() => decide('reject')}
                    >
                        Rechazar
                    </Button>
                    {approval.expires_at && (
                        <span className="text-[11px] text-muted-foreground">
                            expira {relative(approval.expires_at)}
                        </span>
                    )}
                </div>
            ) : (
                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <span className="font-bold uppercase tracking-widest">
                        {approval.status}
                    </span>
                    {approval.decision_note && <span>· {approval.decision_note}</span>}
                    {approval.decided_at && <span>· {relative(approval.decided_at)}</span>}
                </div>
            )}
        </div>
    );
}
