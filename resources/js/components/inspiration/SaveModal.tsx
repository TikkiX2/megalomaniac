import { Link } from '@inertiajs/react';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { csrfHeaders } from '@/lib/csrf';
import inspiration from '@/routes/inspiration';
import { sourceLabel, type BoardOption, type InspirationItem } from './shared';

interface SaveModalProps {
    item: InspirationItem | null;
    boards: BoardOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Called on 201 so the parent can refresh `saved` + `boards`. */
    onSaved: () => void;
}

interface Conflict {
    message: string;
    id: number | null;
    name: string | null;
}

function flattenErrors(errors: Record<string, string | string[]>): string[] {
    return Object.values(errors).flatMap((value) => (Array.isArray(value) ? value : [value]));
}

/** Inbox first, then the rest ordered by project (or board) name. */
function orderBoards(boards: BoardOption[]): BoardOption[] {
    return [...boards].sort((a, b) => {
        const aInbox = a.name.toLowerCase() === 'inbox' ? 0 : 1;
        const bInbox = b.name.toLowerCase() === 'inbox' ? 0 : 1;

        if (aInbox !== bInbox) {
            return aInbox - bInbox;
        }

        return (a.project_name ?? a.name).localeCompare(b.project_name ?? b.name);
    });
}

/**
 * Save-to-moodboard dialog. Posts JSON directly (the endpoint is not an Inertia
 * response), so 409 conflicts can surface the existing board instead of
 * triggering Inertia's generic error modal.
 *
 * The form is keyed by `source:source_id`, so switching images (or reopening)
 * remounts it and no reset effect is needed.
 */
export default function SaveModal({ item, boards, open, onOpenChange, onSaved }: SaveModalProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-border bg-card sm:max-w-md">
                {item && (
                    <SaveForm
                        key={`${item.source}:${item.source_id}`}
                        item={item}
                        boards={boards}
                        onSaved={onSaved}
                        onClose={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function SaveForm({
    item,
    boards,
    onSaved,
    onClose,
}: {
    item: InspirationItem;
    boards: BoardOption[];
    onSaved: () => void;
    onClose: () => void;
}) {
    const ordered = useMemo(() => orderBoards(boards), [boards]);
    const [boardId, setBoardId] = useState<number | null>(() => ordered[0]?.id ?? null);
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [conflict, setConflict] = useState<Conflict | null>(null);
    const [errors, setErrors] = useState<string[]>([]);

    const submit = async () => {
        if (submitting) {
            return;
        }

        setSubmitting(true);
        setConflict(null);
        setErrors([]);

        try {
            const response = await fetch(inspiration.save().url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({
                    source: item.source,
                    source_id: item.source_id,
                    image_url: item.image_url,
                    page_url: item.page_url,
                    title: item.title ?? undefined,
                    author: item.author ?? undefined,
                    author_url: item.author_url ?? undefined,
                    thumbnail_url: item.thumbnail_url ?? undefined,
                    width: item.width ?? undefined,
                    height: item.height ?? undefined,
                    tags: item.tags?.length ? item.tags : undefined,
                    license: item.license ?? undefined,
                    maturity: item.maturity ?? undefined,
                    note: note.trim() || undefined,
                    moodboard_id: boardId ?? undefined,
                }),
            });

            if (response.status === 201) {
                onSaved();
                onClose();

                return;
            }

            if (response.status === 409) {
                const data = (await response.json()) as {
                    message?: string;
                    existing_moodboard_id?: number;
                    existing_moodboard_name?: string;
                };
                setConflict({
                    message: data.message ?? 'Esta imagen ya está guardada.',
                    id: data.existing_moodboard_id ?? null,
                    name: data.existing_moodboard_name ?? null,
                });

                return;
            }

            if (response.status === 422) {
                const data = (await response.json()) as { errors?: Record<string, string | string[]> };
                setErrors(flattenErrors(data.errors ?? {}));

                return;
            }

            setErrors(['No se pudo guardar la imagen. Intentá de nuevo.']);
        } catch {
            setErrors(['No se pudo conectar con el servidor.']);
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle className="text-foreground">Guardar en moodboard</DialogTitle>
                <DialogDescription className="text-muted-foreground">
                    {sourceLabel(item.source)} · {item.title ?? item.source_id}
                </DialogDescription>
            </DialogHeader>

            <div className="space-y-3">
                <div className="max-h-64 space-y-1 overflow-y-auto pr-1">
                    {ordered.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No hay moodboards todavía.</p>
                    ) : (
                        ordered.map((board) => (
                            <button
                                key={board.id}
                                type="button"
                                onClick={() => setBoardId(board.id)}
                                className={`flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-sm transition-colors ${
                                    boardId === board.id
                                        ? 'border-primary bg-primary/10 text-foreground'
                                        : 'border-border bg-background text-muted-foreground hover:border-primary/40'
                                }`}
                            >
                                <span className="truncate font-medium">
                                    {board.name}
                                    {board.project_name && (
                                        <span className="ml-1 text-xs text-muted-foreground">· {board.project_name}</span>
                                    )}
                                </span>
                                <span className="ml-2 shrink-0 text-[10px] tabular-nums text-muted-foreground">
                                    {board.count}
                                </span>
                            </button>
                        ))
                    )}
                </div>

                <Textarea
                    value={note}
                    onChange={(event) => setNote(event.target.value)}
                    placeholder="Nota (opcional)"
                    maxLength={5000}
                    className="border-border bg-background"
                />

                {conflict && (
                    <div className="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-200">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        <div>
                            <p>{conflict.message}</p>
                            {conflict.id !== null && (
                                <Link
                                    href={inspiration.moodboards.show(conflict.id)}
                                    className="mt-1 inline-block font-bold text-amber-100 underline underline-offset-2"
                                >
                                    Ver {conflict.name ?? 'moodboard'}
                                </Link>
                            )}
                        </div>
                    </div>
                )}

                {errors.length > 0 && (
                    <ul className="list-inside list-disc rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-xs text-destructive">
                        {errors.map((error) => (
                            <li key={error}>{error}</li>
                        ))}
                    </ul>
                )}
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onClose}>
                    Cancelar
                </Button>
                <Button type="button" onClick={submit} disabled={submitting || ordered.length === 0}>
                    {submitting && <Loader2 className="mr-1 h-4 w-4 animate-spin" />}
                    Guardar
                </Button>
            </DialogFooter>
        </>
    );
}
