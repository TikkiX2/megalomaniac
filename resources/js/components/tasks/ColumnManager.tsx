import { ArrowLeft, ArrowRight, Check, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { csrfHeaders } from '@/lib/csrf';
import type { BoardColumn } from '@/types/personal';

export const COLUMN_COLORS = ['amber', 'primary', 'emerald', 'sky', 'violet', 'slate'] as const;

const DOT_CLASSES: Record<string, string> = {
    amber: 'bg-amber-500',
    primary: 'bg-primary',
    emerald: 'bg-emerald-500',
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
    slate: 'bg-slate-500',
};

const ACCENT_CLASSES: Record<string, string> = {
    amber: 'border-amber-500/20',
    primary: 'border-primary/30',
    emerald: 'border-emerald-500/20',
    sky: 'border-sky-500/20',
    violet: 'border-violet-500/20',
    slate: 'border-slate-500/20',
};

export function columnDotClass(color: string): string {
    return DOT_CLASSES[color] ?? DOT_CLASSES.slate;
}

export function columnAccentClass(color: string): string {
    return ACCENT_CLASSES[color] ?? ACCENT_CLASSES.slate;
}

async function send(url: string, method: string, body?: unknown): Promise<Response> {
    return fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...csrfHeaders(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
}

export function AddColumnButton({
    projectId,
    onCreated,
}: {
    projectId: number | null;
    onCreated: (column: BoardColumn) => void;
}) {
    const [open, setOpen] = useState(false);
    const [label, setLabel] = useState('');
    const [color, setColor] = useState<string>('slate');
    const [isDone, setIsDone] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleCreate = async () => {
        setSaving(true);
        setError(null);

        try {
            const response = await send('/task-board-columns', 'POST', {
                label: label.trim(),
                color,
                is_done: isDone,
                project_id: projectId,
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'No se pudo crear la columna.');
            }

            onCreated(data.column);
            setOpen(false);
            setLabel('');
            setColor('slate');
            setIsDone(false);
        } catch {
            setError('No se pudo crear la columna.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="flex min-h-[120px] flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-card/40 text-muted-foreground transition-colors hover:border-primary/40 hover:text-foreground"
            >
                <Plus className="h-5 w-5" />
                <span className="text-xs font-bold uppercase tracking-widest">Añadir columna</span>
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="bg-card border-border text-foreground sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Nueva columna</DialogTitle>
                        <DialogDescription className="text-muted-foreground">
                            Se agrega al final del tablero.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-4">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="new-column-label" className="text-muted-foreground">Nombre</Label>
                            <Input
                                id="new-column-label"
                                value={label}
                                onChange={(event) => setLabel(event.target.value)}
                                placeholder="Ej. En Revisión"
                                className="bg-background border-border"
                            />
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label className="text-muted-foreground">Color</Label>
                            <div className="flex gap-2">
                                {COLUMN_COLORS.map((option) => (
                                    <button
                                        key={option}
                                        type="button"
                                        aria-label={`Color ${option}`}
                                        onClick={() => setColor(option)}
                                        className={`h-7 w-7 rounded-full ${columnDotClass(option)} ring-offset-2 ring-offset-card ${
                                            color === option ? 'ring-2 ring-primary' : ''
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>

                        <label className="flex items-center gap-2 text-sm text-foreground">
                            <input
                                type="checkbox"
                                checked={isDone}
                                onChange={(event) => setIsDone(event.target.checked)}
                                className="h-4 w-4 accent-primary"
                            />
                            Cuenta como completada
                        </label>

                        {error && <p className="text-sm text-destructive">{error}</p>}
                    </div>

                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => setOpen(false)} className="border-border">
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            onClick={handleCreate}
                            disabled={saving || !label.trim()}
                            className="bg-primary text-primary-foreground font-bold"
                        >
                            {saving ? 'Creando…' : 'Crear columna'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

export function ColumnMenu({
    column,
    columns,
    taskCount,
    onUpdated,
    onDeleted,
    onReordered,
}: {
    column: BoardColumn;
    columns: BoardColumn[];
    taskCount: number;
    onUpdated: (column: BoardColumn) => void;
    onDeleted: (columnId: number, destinationKey: string | null) => void;
    onReordered: (orderedIds: number[]) => void;
}) {
    const [renameOpen, setRenameOpen] = useState(false);
    const [label, setLabel] = useState(column.label);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [destination, setDestination] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const index = columns.findIndex((item) => item.id === column.id);
    const canMoveLeft = index > 0;
    const canMoveRight = index >= 0 && index < columns.length - 1;

    const patch = async (payload: Record<string, unknown>) => {
        setBusy(true);
        setError(null);

        try {
            const response = await send(`/task-board-columns/${column.id}`, 'PATCH', payload);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'No se pudo actualizar la columna.');
            }

            onUpdated(data.column);
        } catch {
            setError('No se pudo actualizar la columna.');
        } finally {
            setBusy(false);
        }
    };

    const move = async (direction: -1 | 1) => {
        const target = index + direction;
        if (target < 0 || target >= columns.length) {
            return;
        }

        const ordered = columns.map((item) => item.id);
        [ordered[index], ordered[target]] = [ordered[target], ordered[index]];

        setBusy(true);

        try {
            const response = await send('/task-board-columns/reorder', 'PATCH', { ordered_ids: ordered });

            if (!response.ok) {
                throw new Error('reorder failed');
            }

            onReordered(ordered);
        } catch {
            setError('No se pudo reordenar.');
        } finally {
            setBusy(false);
        }
    };

    const handleDelete = async () => {
        setBusy(true);
        setError(null);

        try {
            const response = await send(
                `/task-board-columns/${column.id}`,
                'DELETE',
                destination ? { move_to: destination } : {},
            );

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data.message || 'No se pudo eliminar la columna.');
            }

            onDeleted(column.id, destination || null);
            setDeleteOpen(false);
        } catch {
            setError('No se pudo eliminar la columna. Elegí una columna destino.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Opciones de ${column.label}`}
                        disabled={busy}
                        className="h-6 w-6 text-muted-foreground hover:bg-white/5 hover:text-foreground"
                    >
                        <MoreHorizontal className="h-3.5 w-3.5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                    <DropdownMenuLabel className="text-xs text-muted-foreground">{column.label}</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onClick={() => { setLabel(column.label); setRenameOpen(true); }}>
                        <Pencil className="mr-2 h-3.5 w-3.5" /> Renombrar
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => patch({ is_done: !column.is_done })}>
                        <Check className="mr-2 h-3.5 w-3.5" />
                        {column.is_done ? 'No cuenta como completada' : 'Cuenta como completada'}
                    </DropdownMenuItem>
                    <DropdownMenuItem disabled={!canMoveLeft} onClick={() => move(-1)}>
                        <ArrowLeft className="mr-2 h-3.5 w-3.5" /> Mover a la izquierda
                    </DropdownMenuItem>
                    <DropdownMenuItem disabled={!canMoveRight} onClick={() => move(1)}>
                        <ArrowRight className="mr-2 h-3.5 w-3.5" /> Mover a la derecha
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        className="text-destructive focus:text-destructive"
                        onClick={() => { setDestination(''); setDeleteOpen(true); }}
                    >
                        <Trash2 className="mr-2 h-3.5 w-3.5" /> Eliminar
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={renameOpen} onOpenChange={setRenameOpen}>
                <DialogContent className="bg-card border-border text-foreground sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Renombrar columna</DialogTitle>
                    </DialogHeader>
                    <Input
                        value={label}
                        onChange={(event) => setLabel(event.target.value)}
                        className="bg-background border-border"
                    />
                    {error && <p className="text-sm text-destructive">{error}</p>}
                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => setRenameOpen(false)} className="border-border">
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            disabled={busy || !label.trim()}
                            onClick={async () => {
                                await patch({ label: label.trim() });
                                setRenameOpen(false);
                            }}
                            className="bg-primary text-primary-foreground font-bold"
                        >
                            Guardar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent className="bg-card border-border text-foreground sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Eliminar «{column.label}»</DialogTitle>
                        <DialogDescription className="text-muted-foreground">
                            {taskCount > 0
                                ? `Las ${taskCount} tarea(s) de esta columna se moverán a la columna que elijas.`
                                : 'Esta columna no tiene tareas.'}
                        </DialogDescription>
                    </DialogHeader>

                    {taskCount > 0 && (
                        <div className="flex flex-col gap-2">
                            <Label className="text-muted-foreground">Columna destino</Label>
                            <Select value={destination} onValueChange={setDestination}>
                                <SelectTrigger className="bg-background border-border">
                                    <SelectValue placeholder="Elegir columna" />
                                </SelectTrigger>
                                <SelectContent>
                                    {columns
                                        .filter((item) => item.id !== column.id)
                                        .map((item) => (
                                            <SelectItem key={item.id} value={item.key}>
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {error && <p className="text-sm text-destructive">{error}</p>}

                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => setDeleteOpen(false)} className="border-border">
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={busy}
                            onClick={handleDelete}
                            className="bg-destructive text-white font-bold"
                        >
                            Eliminar columna
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
