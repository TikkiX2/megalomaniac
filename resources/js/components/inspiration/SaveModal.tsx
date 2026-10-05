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
import {
    sourceLabel,
    type BoardOption,
    type InspirationItem,
    type ProjectOption,
} from './shared';

interface SaveModalProps {
    item: InspirationItem | null;
    boards: BoardOption[];
    projects: ProjectOption[];
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

/**
 * One selectable destination. Inbox is always offered (created lazily by the
 * backend on first save); each personal project resolves either to its existing
 * moodboard (`moodboard_id`) or, when it has none yet, to a `project_id` that
 * makes the backend create it lazily.
 */
interface SaveOption {
    key: string;
    label: string;
    project: string | null;
    count: number | null;
    hint: string | null;
    group: 'base' | 'projects' | 'boards';
    payload: { moodboard_id?: number; project_id?: number };
}

function inboxKey(): string {
    return 'inbox';
}

function boardKey(boardId: number): string {
    return `board:${boardId}`;
}

function projectKey(projectId: number): string {
    return `project:${projectId}`;
}

function flattenErrors(errors: Record<string, string | string[]>): string[] {
    return Object.values(errors).flatMap((value) => (Array.isArray(value) ? value : [value]));
}

function sortByName<T extends { name: string }>(items: T[]): T[] {
    return [...items].sort((a, b) => a.name.localeCompare(b.name));
}

/**
 * Build the ordered destination list: Inbox first, then one entry per personal
 * project, then any board not tied to a listed project (defensive: a board can
 * outlive the project query). A project with no board yet is offered as a lazy
 * target.
 */
function buildOptions(boards: BoardOption[], projects: ProjectOption[]): SaveOption[] {
    const inboxBoard = boards.find((board) => board.project_id === null) ?? null;
    const options: SaveOption[] = [
        {
            key: inboxKey(),
            label: inboxBoard?.name ?? 'Inbox',
            project: null,
            count: inboxBoard?.count ?? 0,
            hint: 'Guardado rápido',
            group: 'base',
            payload: {},
        },
    ];

    const projectIds = new Set(projects.map((project) => project.id));

    for (const project of sortByName(projects)) {
        const board = boards.find((candidate) => candidate.project_id === project.id);

        options.push(
            board
                ? {
                      key: boardKey(board.id),
                      label: board.name,
                      project: project.name,
                      count: board.count,
                      hint: null,
                      group: 'projects',
                      payload: { moodboard_id: board.id },
                  }
                : {
                      key: projectKey(project.id),
                      label: project.name,
                      project: null,
                      count: null,
                      hint: 'Se crea al guardar',
                      group: 'projects',
                      payload: { project_id: project.id },
                  },
        );
    }

    for (const board of sortByName(boards)) {
        if (board.project_id === null || projectIds.has(board.project_id)) {
            continue;
        }

        options.push({
            key: boardKey(board.id),
            label: board.name,
            project: board.project_name,
            count: board.count,
            hint: null,
            group: 'boards',
            payload: { moodboard_id: board.id },
        });
    }

    return options;
}

export default function SaveModal({
    item,
    boards,
    projects,
    open,
    onOpenChange,
    onSaved,
}: SaveModalProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-border bg-card sm:max-w-md">
                {item && (
                    <SaveForm
                        key={`${item.source}:${item.source_id}`}
                        item={item}
                        boards={boards}
                        projects={projects}
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
    projects,
    onSaved,
    onClose,
}: {
    item: InspirationItem;
    boards: BoardOption[];
    projects: ProjectOption[];
    onSaved: () => void;
    onClose: () => void;
}) {
    const options = useMemo(() => buildOptions(boards, projects), [boards, projects]);
    const [target, setTarget] = useState<string>(() => options[0]?.key ?? inboxKey());
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [conflict, setConflict] = useState<Conflict | null>(null);
    const [errors, setErrors] = useState<string[]>([]);

    const selected = options.find((option) => option.key === target) ?? options[0];

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
                    ...selected?.payload,
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
                    {options.map((option, index) => (
                        <div key={option.key}>
                            {index > 0 && options[index - 1].group !== option.group && (
                                <p className="px-1 pt-2 pb-1 text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                    {option.group === 'projects' ? 'Proyectos' : 'Otros moodboards'}
                                </p>
                            )}
                            <OptionButton
                                option={option}
                                selected={option.key === selected?.key}
                                onSelect={() => setTarget(option.key)}
                            />
                        </div>
                    ))}
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
                <Button type="button" onClick={submit} disabled={submitting || selected === undefined}>
                    {submitting && <Loader2 className="mr-1 h-4 w-4 animate-spin" />}
                    Guardar
                </Button>
            </DialogFooter>
        </>
    );
}

function OptionButton({
    option,
    selected,
    onSelect,
}: {
    option: SaveOption;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            className={`flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-sm transition-colors ${
                selected
                    ? 'border-primary bg-primary/10 text-foreground'
                    : 'border-border bg-background text-muted-foreground hover:border-primary/40'
            }`}
        >
            <span className="min-w-0 truncate font-medium">
                {option.label}
                {option.project && (
                    <span className="ml-1 text-xs text-muted-foreground">· {option.project}</span>
                )}
                {option.hint && (
                    <span className="ml-1 text-[10px] font-normal text-muted-foreground">
                        {option.hint}
                    </span>
                )}
            </span>
            {option.count !== null && (
                <span className="ml-2 shrink-0 text-[10px] tabular-nums text-muted-foreground">
                    {option.count}
                </span>
            )}
        </button>
    );
}
