import { ChevronDown, MessageCircleQuestion, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { DecideApproval, PendingApproval } from '@/types/chat';

const ACTION_LABELS: Record<string, string> = {
    create_project: 'Crear un proyecto',
    create_workout: 'Crear un entrenamiento',
    log_set: 'Registrar una serie',
    log_meal: 'Registrar una comida',
    add_purchase: 'Añadir una compra',
    add_income: 'Añadir un ingreso',
    add_debt: 'Añadir una deuda',
    create_task: 'Crear una tarea',
    complete_task: 'Completar una tarea',
    update_task: 'Actualizar una tarea',
    log_supplement: 'Registrar un suplemento',
    add_grocery_item: 'Añadir un producto a la compra',
};

const HUMAN_LABELS: Record<string, string> = {
    ActionTool: 'Modificar tus datos',
};

function approvalTitle(approval: PendingApproval): string {
    if (approval.tool === 'ActionTool') {
        const action = typeof approval.arguments.action === 'string' ? approval.arguments.action : '';

        return ACTION_LABELS[action] ?? HUMAN_LABELS.ActionTool ?? approval.tool;
    }

    return HUMAN_LABELS[approval.tool] ?? approval.tool;
}

function questionText(approval: PendingApproval): string {
    return approval.reason?.trim() || 'Necesito una decisión tuya.';
}

function approvalOptions(approval: PendingApproval): string[] {
    const options = approval.arguments.options;

    return Array.isArray(options) ? options.filter((option): option is string => typeof option === 'string') : [];
}

function validateJson(value: string): string | null {
    if (value.trim() === '') return 'El JSON no puede estar vacío.';

    try {
        const parsed: unknown = JSON.parse(value);

        if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
            return 'Debe ser un objeto JSON.';
        }

        return null;
    } catch {
        return 'JSON inválido.';
    }
}

interface ApprovalCardProps {
    approval: PendingApproval;
    disabled?: boolean;
    onDecide: DecideApproval;
}

function QuestionCard({ approval, disabled = false, onDecide }: ApprovalCardProps) {
    const [answer, setAnswer] = useState('');
    const options = approvalOptions(approval);
    const trimmed = answer.trim();

    return (
        <div className="rounded-xl border border-border bg-card/70 p-3">
            <div className="flex items-start gap-2">
                <MessageCircleQuestion className="mt-0.5 h-4 w-4 shrink-0 text-primary" />

                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-foreground">{questionText(approval)}</p>

                    {options.length > 0 && (
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            {options.map((option) => (
                                <button
                                    key={option}
                                    type="button"
                                    disabled={disabled}
                                    onClick={() => setAnswer(option)}
                                    className="rounded-full border border-border bg-background px-2.5 py-1 text-xs text-muted-foreground transition-colors hover:border-primary/40 hover:text-foreground focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50"
                                >
                                    {option}
                                </button>
                            ))}
                        </div>
                    )}

                    <Textarea
                        value={answer}
                        rows={2}
                        disabled={disabled}
                        placeholder="Escribe tu respuesta…"
                        aria-label="Respuesta"
                        onChange={(event) => setAnswer(event.target.value)}
                        className="mt-2 min-h-16 resize-none bg-background"
                    />

                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            disabled={disabled || trimmed === ''}
                            onClick={() => onDecide(approval.id, 'reject', { result: trimmed })}
                        >
                            Responder
                        </Button>

                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            disabled={disabled}
                            title="Cierra el turno sin enviar respuesta"
                            onClick={() => onDecide(approval.id, 'reject')}
                            className="text-muted-foreground hover:text-foreground"
                        >
                            Saltar
                        </Button>

                        <span className="text-[10px] text-muted-foreground">Saltar cierra el turno sin respuesta.</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

function ApprovalRequestCard({ approval, disabled = false, onDecide }: ApprovalCardProps) {
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState('');
    const [draftError, setDraftError] = useState<string | null>(null);
    const [denyReason, setDenyReason] = useState('');

    const reason = approval.reason?.trim();

    const openEditor = () => {
        setDraft(JSON.stringify(approval.arguments, null, 2));
        setDraftError(null);
        setEditing(true);
    };

    const changeDraft = (value: string) => {
        setDraft(value);
        setDraftError(validateJson(value));
    };

    const submitEdit = () => {
        const error = validateJson(draft);

        setDraftError(error);

        if (error !== null) return;

        onDecide(approval.id, 'edit', { arguments: JSON.parse(draft) as Record<string, unknown> });
    };

    const deny = () => {
        const result = denyReason.trim();

        onDecide(approval.id, 'reject', result === '' ? undefined : { result });
    };

    return (
        <div className="rounded-xl border border-border bg-card/70 p-3">
            <div className="flex items-start gap-2">
                <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0 text-primary" />

                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-foreground">{approvalTitle(approval)}</p>

                    {reason !== undefined && reason !== '' && (
                        <p className="mt-0.5 text-xs text-muted-foreground">{reason}</p>
                    )}

                    <Collapsible open={detailsOpen} onOpenChange={setDetailsOpen} className="mt-2">
                        <CollapsibleTrigger className="flex items-center gap-1 rounded-md text-xs text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none">
                            <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', detailsOpen && 'rotate-180')} />
                            {detailsOpen ? 'Ocultar argumentos' : 'Ver argumentos'}
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                            <pre className="mt-1 max-h-40 max-w-full overflow-auto rounded-lg border border-border bg-background p-2 text-xs break-words whitespace-pre-wrap text-muted-foreground">
                                {JSON.stringify(approval.arguments, null, 2)}
                            </pre>
                        </CollapsibleContent>
                    </Collapsible>

                    {editing ? (
                        <div className="mt-2">
                            <Textarea
                                value={draft}
                                rows={6}
                                disabled={disabled}
                                aria-label="Argumentos en JSON"
                                aria-invalid={draftError !== null}
                                onChange={(event) => changeDraft(event.target.value)}
                                className="min-h-32 resize-y bg-background font-mono text-xs"
                            />

                            {draftError !== null && (
                                <p className="mt-1 text-xs text-destructive" role="alert">
                                    {draftError}
                                </p>
                            )}

                            <div className="mt-2 flex flex-wrap gap-2">
                                <Button type="button" size="sm" disabled={disabled || draftError !== null} onClick={submitEdit}>
                                    Guardar y aprobar
                                </Button>

                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    disabled={disabled}
                                    onClick={() => {
                                        setEditing(false);
                                        setDraftError(null);
                                    }}
                                    className="text-muted-foreground hover:text-foreground"
                                >
                                    Cancelar
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <>
                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                <Button type="button" size="sm" disabled={disabled} onClick={() => onDecide(approval.id, 'approve')}>
                                    Aprobar
                                </Button>

                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={disabled}
                                    onClick={openEditor}
                                    className="border-border bg-card text-foreground hover:bg-muted"
                                >
                                    Editar
                                </Button>

                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    disabled={disabled}
                                    onClick={deny}
                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                >
                                    Denegar
                                </Button>
                            </div>

                            <Input
                                value={denyReason}
                                disabled={disabled}
                                maxLength={500}
                                aria-label="Motivo del rechazo"
                                placeholder="Motivo del rechazo (opcional)"
                                onChange={(event) => setDenyReason(event.target.value)}
                                className="mt-2 h-8 bg-background text-xs"
                            />
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

export function ApprovalCard(props: ApprovalCardProps) {
    return props.approval.kind === 'question' ? <QuestionCard {...props} /> : <ApprovalRequestCard {...props} />;
}

interface ApprovalCardListProps {
    approvals: PendingApproval[];
    disabled?: boolean;
    onDecide: DecideApproval;
    onApproveAll?: (ids: string[]) => void;
}

export function ApprovalCardList({ approvals, disabled = false, onDecide, onApproveAll }: ApprovalCardListProps) {
    if (approvals.length === 0) return null;

    // Questions must never be batch-approved: approving them would execute the
    // tool instead of asking the user.
    const approvable = approvals.filter((approval) => approval.kind === 'approval');

    return (
        <section aria-live="polite" aria-label="Aprobaciones pendientes" className="mt-3 space-y-2">
            {approvable.length > 1 && onApproveAll !== undefined && (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                        {approvable.length} acciones pendientes
                    </p>

                    <Button
                        type="button"
                        size="sm"
                        disabled={disabled}
                        onClick={() => onApproveAll(approvable.map((approval) => approval.id))}
                        className="h-7 px-2.5 text-xs"
                    >
                        Aprobar todo
                    </Button>
                </div>
            )}

            {approvals.map((approval) => (
                <ApprovalCard key={approval.id} approval={approval} disabled={disabled} onDecide={onDecide} />
            ))}
        </section>
    );
}
