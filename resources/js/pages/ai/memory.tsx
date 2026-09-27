import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    BrainCircuit,
    CornerUpLeft,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import MainLayout from '@/layouts/main-layout';
import { cn } from '@/lib/utils';
import { destroy, promote, store, update } from '@/routes/ai/memory';
import type { SharedData } from '@/types';
import type {
    MemoryLimits,
    MemoryRow,
    MemoryScopeName,
    ThreadOption,
} from '@/types/memory';

interface MemoryPageProps {
    memories: MemoryRow[];
    threads: ThreadOption[];
    limits: MemoryLimits;
    selected_thread: string | null;
    flash?: { success?: string | null };
}

function SourceBadge({ source }: { source: MemoryRow['source'] }) {
    return (
        <Badge
            variant="outline"
            className="border-border text-[10px] text-muted-foreground"
        >
            {source === 'agent' ? 'Agente' : 'Usuario'}
        </Badge>
    );
}

function MemoryCard({
    memory,
    maxContent,
    promoteDisabled,
    onPromote,
    onDelete,
}: {
    memory: MemoryRow;
    maxContent: number;
    promoteDisabled: boolean;
    onPromote: (memory: MemoryRow) => void;
    onDelete: (memory: MemoryRow) => void;
}) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ content: memory.content });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.patch(update.url(memory.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    return (
        <Card className="border-border bg-card">
            <CardContent className="space-y-3 p-4">
                {editing ? (
                    <form onSubmit={submit} className="space-y-2">
                        <Textarea
                            autoFocus
                            rows={3}
                            value={form.data.content}
                            maxLength={maxContent}
                            onChange={(event) =>
                                form.setData('content', event.target.value)
                            }
                            className="bg-background text-sm"
                            aria-label="Editar memoria"
                        />
                        <div className="flex items-center justify-between">
                            <span className="text-[10px] text-muted-foreground">
                                {form.data.content.length}/{maxContent}
                            </span>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        form.setData('content', memory.content);
                                        setEditing(false);
                                    }}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={form.processing}
                                    className="bg-primary font-bold"
                                >
                                    Guardar
                                </Button>
                            </div>
                        </div>
                        <InputError message={form.errors.content} />
                    </form>
                ) : (
                    <>
                        <p className="text-sm whitespace-pre-wrap text-foreground">
                            {memory.content}
                        </p>
                        <div className="flex flex-wrap items-center gap-2 text-[10px] text-muted-foreground">
                            <SourceBadge source={memory.source} />
                            <span>
                                {memory.updated_at
                                    ? new Date(
                                          memory.updated_at,
                                      ).toLocaleString()
                                    : '—'}
                            </span>
                            <div className="ml-auto flex items-center gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 text-xs"
                                    onClick={() => {
                                        form.setData('content', memory.content);
                                        setEditing(true);
                                    }}
                                >
                                    <Pencil className="mr-1 h-3 w-3" />
                                    Editar
                                </Button>
                                {memory.scope === 'thread' && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 text-xs"
                                        disabled={promoteDisabled}
                                        onClick={() => onPromote(memory)}
                                    >
                                        <CornerUpLeft className="mr-1 h-3 w-3" />
                                        Pasar a general
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 text-xs text-destructive hover:text-destructive"
                                    onClick={() => onDelete(memory)}
                                >
                                    <Trash2 className="mr-1 h-3 w-3" />
                                    Borrar
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}

export default function MemoryPage() {
    const { memories, threads, limits, selected_thread, flash } = usePage<
        SharedData & MemoryPageProps
    >().props;

    const [scope, setScope] = useState<MemoryScopeName>(
        selected_thread ? 'thread' : 'global',
    );
    const [threadId, setThreadId] = useState<string | null>(
        selected_thread ?? threads[0]?.id ?? null,
    );
    const [search, setSearch] = useState('');
    const [deleteTarget, setDeleteTarget] = useState<MemoryRow | null>(null);
    const [mutatingId, setMutatingId] = useState<string | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [mutationError, setMutationError] = useState<string | null>(null);

    const createForm = useForm<{ content: string; thread_id: string | null }>({
        content: '',
        thread_id: null,
    });

    const globalRows = useMemo(
        () => memories.filter((memory) => memory.scope === 'global'),
        [memories],
    );
    const threadRows = useMemo(
        () =>
            memories.filter(
                (memory) =>
                    memory.scope === 'thread' && memory.thread_id === threadId,
            ),
        [memories, threadId],
    );

    const rows = (scope === 'global' ? globalRows : threadRows).filter(
        (memory) =>
            memory.content.toLowerCase().includes(search.trim().toLowerCase()),
    );

    const used = scope === 'global' ? globalRows.length : threadRows.length;
    const max = scope === 'global' ? limits.max_global : limits.max_thread;
    const atLimit = used >= max;

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();

        createForm.transform((data) => ({
            ...data,
            scope,
            thread_id: scope === 'thread' ? threadId : null,
        }));

        createForm.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => createForm.reset('content'),
        });
    };

    const promoteMemory = (memory: MemoryRow) => {
        router.post(
            promote.url(memory.id),
            {},
            {
                preserveScroll: true,
                onStart: () => {
                    setMutationError(null);
                    setMutatingId(memory.id);
                },
                onFinish: () => setMutatingId(null),
                onError: (errors) =>
                    setMutationError(
                        Object.values(errors)[0] ??
                            'No se pudo promover la memoria.',
                    ),
            },
        );
    };

    const confirmDelete = () => {
        if (deleteTarget === null) return;

        router.delete(destroy.url(deleteTarget.id), {
            preserveScroll: true,
            onStart: () => {
                setMutationError(null);
                setDeleting(true);
            },
            onFinish: () => setDeleting(false),
            onSuccess: () => setDeleteTarget(null),
            onError: (errors) =>
                setMutationError(
                    Object.values(errors)[0] ?? 'No se pudo borrar la memoria.',
                ),
        });
    };

    return (
        <MainLayout>
            <Head title="Memoria" />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 sm:p-6">
                <Heading
                    variant="small"
                    title="Memoria"
                    description="Lo que el asistente recuerda: hechos generales que valen en todos los hilos y detalles propios de cada conversación."
                />

                {flash?.success && (
                    <div className="rounded-xl border border-primary/30 bg-primary/10 p-3 text-sm text-primary">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-3">
                    <div className="flex rounded-lg border border-border bg-card p-1">
                        {(['global', 'thread'] as const).map((option) => (
                            <button
                                key={option}
                                type="button"
                                onClick={() => setScope(option)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                                    scope === option
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {option === 'global' ? 'General' : 'Por hilo'}
                            </button>
                        ))}
                    </div>

                    {scope === 'thread' && (
                        <Select
                            value={threadId ?? undefined}
                            onValueChange={(value) => setThreadId(value)}
                        >
                            <SelectTrigger className="w-64 border-border bg-card text-sm">
                                <SelectValue placeholder="Elegí un hilo" />
                            </SelectTrigger>
                            <SelectContent className="border-border bg-card">
                                {threads.map((thread) => (
                                    <SelectItem
                                        key={thread.id}
                                        value={thread.id}
                                    >
                                        {thread.title ?? 'Hilo sin título'}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    <span
                        className={cn(
                            'text-xs',
                            atLimit
                                ? 'text-destructive'
                                : 'text-muted-foreground',
                        )}
                    >
                        {used}/{max}
                    </span>

                    <div className="relative ml-auto">
                        <Search className="absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Buscar…"
                            className="h-8 w-48 border-border bg-card pl-7 text-sm"
                        />
                    </div>
                </div>

                <Card className="border-border bg-card">
                    <CardContent className="p-4">
                        <form onSubmit={submitCreate} className="space-y-2">
                            <Textarea
                                rows={2}
                                value={createForm.data.content}
                                maxLength={limits.max_content}
                                onChange={(event) =>
                                    createForm.setData(
                                        'content',
                                        event.target.value,
                                    )
                                }
                                placeholder={
                                    scope === 'global'
                                        ? 'Ej: prefiere entrenar a la mañana'
                                        : 'Ej: el objetivo de este hilo es un PR de press banca'
                                }
                                disabled={
                                    atLimit ||
                                    (scope === 'thread' && threadId === null)
                                }
                                className="bg-background text-sm"
                                aria-label="Nueva memoria"
                            />
                            <div className="flex items-center justify-between">
                                <span className="text-[10px] text-muted-foreground">
                                    {createForm.data.content.length}/
                                    {limits.max_content}
                                </span>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={
                                        atLimit ||
                                        createForm.processing ||
                                        (scope === 'thread' &&
                                            threadId === null)
                                    }
                                    className="bg-primary font-bold"
                                >
                                    <Plus className="mr-1 h-3.5 w-3.5" />
                                    Guardar memoria
                                </Button>
                            </div>
                            {atLimit && (
                                <p className="text-xs text-destructive">
                                    Límite alcanzado: borrá o promové memorias
                                    para guardar más.
                                </p>
                            )}
                            <InputError message={createForm.errors.content} />
                            <InputError message={createForm.errors.thread_id} />
                        </form>
                    </CardContent>
                </Card>

                {mutationError && (
                    <div
                        role="alert"
                        className="rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive"
                    >
                        {mutationError}
                    </div>
                )}

                {scope === 'thread' && threads.length === 0 ? (
                    <Card className="border-border bg-card">
                        <CardContent className="py-8 text-center text-sm text-muted-foreground">
                            Todavía no hay hilos de chat. La memoria por hilo se
                            activa cuando existe una conversación.
                        </CardContent>
                    </Card>
                ) : rows.length === 0 ? (
                    <Card className="border-border bg-card">
                        <CardContent className="flex flex-col items-center gap-2 py-8 text-center text-sm text-muted-foreground">
                            <BrainCircuit className="h-5 w-5" />
                            {search.trim() !== ''
                                ? 'Ninguna memoria coincide con la búsqueda.'
                                : scope === 'global'
                                  ? 'Todavía no hay memoria general. El asistente la va a ir guardando, o agregala vos.'
                                  : 'Este hilo todavía no tiene memoria propia.'}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {rows.map((memory) => (
                            <MemoryCard
                                key={memory.id}
                                memory={memory}
                                maxContent={limits.max_content}
                                promoteDisabled={
                                    mutatingId === memory.id || deleting
                                }
                                onPromote={promoteMemory}
                                onDelete={setDeleteTarget}
                            />
                        ))}
                    </div>
                )}
            </div>

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <DialogContent className="border-border bg-card sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Borrar memoria</DialogTitle>
                        <DialogDescription>
                            {deleteTarget?.content} — esta acción no se puede
                            deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    {mutationError && (
                        <p role="alert" className="text-xs text-destructive">
                            {mutationError}
                        </p>
                    )}
                    <DialogFooter className="gap-2">
                        <Button
                            variant="ghost"
                            disabled={deleting}
                            onClick={() => setDeleteTarget(null)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            disabled={deleting}
                            onClick={confirmDelete}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            <Trash2 className="mr-1 h-3.5 w-3.5" />
                            Borrar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
