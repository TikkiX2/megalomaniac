import { Loader2, Sparkles, Trash2 } from 'lucide-react';
import { useState } from 'react';
import YooptaEditor from '@/components/freelance/YooptaEditor';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { csrfHeaders } from '@/lib/csrf';
import type { BoardColumn, YooptaBlock } from '@/types/personal';

export interface DetailTask {
    id: number;
    title: string;
    description?: YooptaBlock[] | null;
    status: string;
    priority?: string | null;
    due_date?: string | null;
    start_date?: string | null;
    estimated_time?: number | null;
    tags?: string[] | null;
    responsible?: string | null;
    area?: string | null;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    task: DetailTask | null;
    columns: BoardColumn[];
    variant: 'personal' | 'freelance';
    updateUrl: (taskId: number) => string;
    deleteUrl?: (taskId: number) => string;
    onSaved: (task: DetailTask) => void;
    onDeleted?: (taskId: number) => void;
    extraFields?: React.ReactNode;
}

const PRIORITIES = [
    { value: 'Low', label: 'Baja' },
    { value: 'Normal', label: 'Normal' },
    { value: 'High', label: 'Alta' },
    { value: 'Urgent', label: 'Urgente' },
];

function hasContent(blocks: YooptaBlock[] | null | undefined): boolean {
    if (!Array.isArray(blocks)) {
        return false;
    }

    return blocks.some((block) =>
        Array.isArray(block.children)
            ? block.children.some((child) => String(child.text ?? '').trim() !== '')
            : false,
    );
}

function toDateInput(value?: string | null): string {
    if (!value) {
        return '';
    }

    const match = String(value).match(/^\d{4}-\d{2}-\d{2}/);

    return match ? match[0] : '';
}

export default function TaskDetailDialog({
    open,
    onOpenChange,
    task,
    columns,
    variant,
    updateUrl,
    deleteUrl,
    onSaved,
    onDeleted,
    extraFields,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="bg-card border-border text-foreground sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="text-foreground">Detalle de tarea</DialogTitle>
                    <DialogDescription className="sr-only">
                        Edita la tarea y guarda los cambios.
                    </DialogDescription>
                </DialogHeader>

                {task && (
                    <TaskDetailForm
                        key={task.id}
                        task={task}
                        columns={columns}
                        variant={variant}
                        updateUrl={updateUrl}
                        deleteUrl={deleteUrl}
                        onSaved={(updated) => {
                            onSaved(updated);
                            onOpenChange(false);
                        }}
                        onDeleted={onDeleted}
                        onCancel={() => onOpenChange(false)}
                        extraFields={extraFields}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function TaskDetailForm({
    task,
    columns,
    variant,
    updateUrl,
    deleteUrl,
    onSaved,
    onDeleted,
    onCancel,
    extraFields,
}: {
    task: DetailTask;
    columns: BoardColumn[];
    variant: 'personal' | 'freelance';
    updateUrl: (taskId: number) => string;
    deleteUrl?: (taskId: number) => string;
    onSaved: (task: DetailTask) => void;
    onDeleted?: (taskId: number) => void;
    onCancel: () => void;
    extraFields?: React.ReactNode;
}) {
    const isPersonal = variant === 'personal';

    const [title, setTitle] = useState(task.title);
    const [status, setStatus] = useState(task.status);
    const [priority, setPriority] = useState(task.priority ?? 'Normal');
    const [dueDate, setDueDate] = useState(toDateInput(task.due_date));
    const [startDate, setStartDate] = useState(toDateInput(task.start_date));
    const [estimatedTime, setEstimatedTime] = useState(
        task.estimated_time === null || task.estimated_time === undefined ? '' : String(task.estimated_time),
    );
    const [tags, setTags] = useState((task.tags ?? []).join(', '));
    const [responsible, setResponsible] = useState(task.responsible ?? '');
    const [area, setArea] = useState(task.area ?? '');

    const [description, setDescription] = useState<YooptaBlock[] | null>(task.description ?? null);
    const [editorKey, setEditorKey] = useState(0);

    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);

    const [aiPrompt, setAiPrompt] = useState('');
    const [aiLoading, setAiLoading] = useState(false);
    const [aiError, setAiError] = useState<string | null>(null);
    const [aiConfigured, setAiConfigured] = useState(true);

    const columnOptions = columns.some((column) => column.key === status)
        ? columns
        : [...columns, { id: -1, user_id: 0, project_id: null, key: status, label: status, color: 'slate', sort_order: 999, is_done: false }];

    const handleGenerate = async () => {
        if (!aiPrompt.trim()) {
            return;
        }

        if (hasContent(description) && !confirm('¿Reemplazar la descripción actual con la generada por IA?')) {
            return;
        }

        setAiLoading(true);
        setAiError(null);

        try {
            const response = await fetch('/ai/generate-task-description', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({ prompt: aiPrompt, title }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'No se pudo generar la descripción.');
            }

            if (!data.description) {
                if (data.message === 'AI not configured.') {
                    setAiConfigured(false);
                }
                setAiError(data.message || 'No se pudo generar la descripción.');
                return;
            }

            setDescription(data.description);
            setEditorKey((key) => key + 1);
        } catch {
            setAiError('No se pudo generar la descripción. Intentá de nuevo.');
        } finally {
            setAiLoading(false);
        }
    };

    const handleSave = async () => {
        setSaving(true);
        setSaveError(null);

        const payload: Record<string, unknown> = {
            title: title.trim(),
            description,
            priority,
            due_date: dueDate || null,
        };

        if (columns.some((column) => column.key === status)) {
            payload.status = status;
        }

        if (isPersonal) {
            payload.start_date = startDate || null;
            payload.estimated_time = estimatedTime === '' ? null : Number(estimatedTime);
            payload.tags = tags
                .split(',')
                .map((tag) => tag.trim())
                .filter(Boolean);
        } else {
            payload.responsible = responsible || null;
            payload.area = area || null;
        }

        try {
            const response = await fetch(updateUrl(task.id), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...csrfHeaders(),
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'No se pudo guardar la tarea.');
            }

            onSaved({ ...task, ...payload, ...(data.task ?? {}) } as unknown as DetailTask);
        } catch {
            setSaveError('No se pudo guardar la tarea. Revisá los datos e intentá de nuevo.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deleteUrl || !onDeleted) {
            return;
        }

        if (!confirm('¿Eliminar esta tarea?')) {
            return;
        }

        setSaving(true);
        setSaveError(null);

        try {
            const response = await fetch(deleteUrl(task.id), {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    ...csrfHeaders(),
                },
            });

            if (!response.ok) {
                throw new Error('delete failed');
            }

            onDeleted(task.id);
            onCancel();
        } catch {
            setSaveError('No se pudo eliminar la tarea.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col gap-2">
                <Label htmlFor="detail-title" className="text-muted-foreground">Título</Label>
                <Input
                    id="detail-title"
                    value={title}
                    onChange={(event) => setTitle(event.target.value)}
                    className="bg-background border-border"
                />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="flex flex-col gap-2">
                    <Label className="text-muted-foreground">Columna</Label>
                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger className="bg-background border-border">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {columnOptions.map((column) => (
                                <SelectItem key={column.key} value={column.key}>
                                    {column.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex flex-col gap-2">
                    <Label className="text-muted-foreground">Prioridad</Label>
                    <Select value={priority} onValueChange={setPriority}>
                        <SelectTrigger className="bg-background border-border">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {PRIORITIES.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="detail-due-date" className="text-muted-foreground">Vencimiento</Label>
                    <Input
                        id="detail-due-date"
                        type="date"
                        value={dueDate}
                        onChange={(event) => setDueDate(event.target.value)}
                        className="bg-background border-border"
                    />
                </div>
            </div>

            {isPersonal ? (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="detail-start-date" className="text-muted-foreground">Inicio</Label>
                        <Input
                            id="detail-start-date"
                            type="date"
                            value={startDate}
                            onChange={(event) => setStartDate(event.target.value)}
                            className="bg-background border-border"
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="detail-estimated" className="text-muted-foreground">Tiempo est. (min)</Label>
                        <Input
                            id="detail-estimated"
                            type="number"
                            min={0}
                            value={estimatedTime}
                            onChange={(event) => setEstimatedTime(event.target.value)}
                            className="bg-background border-border"
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="detail-tags" className="text-muted-foreground">Tags</Label>
                        <Input
                            id="detail-tags"
                            value={tags}
                            onChange={(event) => setTags(event.target.value)}
                            placeholder="casa, trabajo"
                            className="bg-background border-border"
                        />
                    </div>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="detail-responsible" className="text-muted-foreground">Responsable</Label>
                        <Input
                            id="detail-responsible"
                            value={responsible}
                            onChange={(event) => setResponsible(event.target.value)}
                            className="bg-background border-border"
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="detail-area" className="text-muted-foreground">Área</Label>
                        <Input
                            id="detail-area"
                            value={area}
                            onChange={(event) => setArea(event.target.value)}
                            className="bg-background border-border"
                        />
                    </div>
                </div>
            )}

            <div className="flex flex-col gap-2">
                <Label className="text-muted-foreground">Descripción</Label>
                <YooptaEditor key={editorKey} value={description} onChange={setDescription} className="min-h-[160px]" />
            </div>

            <div className="flex flex-col gap-3 rounded-lg border border-border bg-muted/20 p-3">
                <div className="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <Sparkles className="h-4 w-4 text-primary" /> Descripción con IA
                </div>

                {aiConfigured ? (
                    <>
                        <Textarea
                            value={aiPrompt}
                            onChange={(event) => setAiPrompt(event.target.value)}
                            placeholder="Ej. Detallá alcance, dependencias y criterios de aceptación"
                            rows={2}
                            className="bg-background border-border"
                        />
                        <div className="flex items-center gap-3">
                            <Button
                                type="button"
                                size="sm"
                                onClick={handleGenerate}
                                disabled={aiLoading || !aiPrompt.trim()}
                                className="bg-primary text-primary-foreground font-bold"
                            >
                                {aiLoading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Sparkles className="mr-2 h-4 w-4" />}
                                {aiLoading ? 'Generando…' : 'Generar'}
                            </Button>
                            {aiError && <p className="text-xs text-destructive">{aiError}</p>}
                        </div>
                    </>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        La IA no está configurada.{' '}
                        <a href="/settings/ai" className="text-primary underline">
                            Configurá tu provider
                        </a>{' '}
                        para usar esta función.
                    </p>
                )}
            </div>

            {extraFields}

            {saveError && <p className="text-sm text-destructive">{saveError}</p>}

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                {deleteUrl && onDeleted ? (
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={handleDelete}
                        disabled={saving}
                        className="text-destructive hover:text-destructive hover:bg-destructive/10 font-bold"
                    >
                        <Trash2 className="mr-2 h-4 w-4" /> Eliminar
                    </Button>
                ) : (
                    <span />
                )}

                <div className="flex gap-2">
                    <Button type="button" variant="outline" onClick={onCancel} disabled={saving} className="border-border">
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        onClick={handleSave}
                        disabled={saving || !title.trim()}
                        className="bg-primary text-primary-foreground font-black"
                    >
                        {saving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                        {saving ? 'Guardando…' : 'Guardar'}
                    </Button>
                </div>
            </div>
        </div>
    );
}
