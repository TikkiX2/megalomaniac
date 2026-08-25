import React, { useState } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Plus,
    MoreHorizontal,
    Calendar,
    User,
    Clock,
    ChevronLeft,
    ChevronRight,
    AlertTriangle,
} from 'lucide-react';
import { router, useForm } from '@inertiajs/react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { store, update, destroy } from '@/actions/App/Http/Controllers/Freelance/ProjectTaskController';

interface Task {
    id: number;
    title: string;
    status: string;
    priority: string;
    due_date?: string | null;
    responsible?: string | null;
    tags?: string[];
    area?: string | null;
}

interface TaskBoardProps {
    project: any;
    tasks: Task[];
}

type ColumnDef = {
    key: string;
    label: string;
    dot: string;
    accent: string;
};

const COLUMNS: ColumnDef[] = [
    { key: 'To Do', label: 'Pendiente', dot: 'bg-amber-500', accent: 'border-amber-500/20' },
    { key: 'In Progress', label: 'En Progreso', dot: 'bg-primary', accent: 'border-primary/30' },
    { key: 'Done', label: 'Completada', dot: 'bg-emerald-500', accent: 'border-emerald-500/20' },
];

// Normaliza variantes históricas a las 3 claves canónicas
const STATUS_MAP: Record<string, string> = {
    // Pendiente
    'To Do': 'To Do',
    'Todo': 'To Do',
    'pending': 'To Do',
    'Pending': 'To Do',
    'Pendiente': 'To Do',
    'pendiente': 'To Do',
    // En Progreso
    'In Progress': 'In Progress',
    'in_progress': 'In Progress',
    'En Progreso': 'In Progress',
    'en_progreso': 'In Progress',
    'Review': 'In Progress',
    // Completada
    'Done': 'Done',
    'done': 'Done',
    'Completed': 'Done',
    'completed': 'Done',
    'Completada': 'Done',
    'completada': 'Done',
};

function normalizeStatus(raw: string): string {
    if (!raw) return 'To Do';
    return STATUS_MAP[raw] ?? raw;
}

function isOverdue(task: Task): boolean {
    if (!task.due_date) return false;
    if (normalizeStatus(task.status) === 'Done') return false;
    const due = new Date(task.due_date);
    if (isNaN(due.getTime())) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    due.setHours(0, 0, 0, 0);
    return due < today;
}

function formatDueDate(due?: string | null): string {
    if (!due) return 'Sin fecha';
    const d = new Date(due);
    if (isNaN(d.getTime())) return 'Sin fecha';
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function TaskBoard({ project, tasks }: TaskBoardProps) {
    const safeTasks = Array.isArray(tasks) ? tasks : [];

    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        title: '',
        description: '',
        status: 'To Do' as string,
        priority: 'Normal' as string,
        due_date: '',
    });

    const getTasksByStatus = (colKey: string) =>
        safeTasks.filter((t) => normalizeStatus(t.status) === colKey);

    const handleStatusChange = (task: Task, newStatus: string) => {
        router.patch(update.url(task.id), { status: newStatus }, { preserveScroll: true, preserveState: true });
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar esta tarea?')) {
            router.delete(destroy.url(id), { preserveScroll: true });
        }
    };

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        // Wayfinder store: POST /freelance/projects/{project}/tasks
        // Normaliza '' -> null para due_date/description antes de enviar (evita 422 nullable|date)
        transform((d) => ({
            ...d,
            title: d.title.trim(),
            description: d.description?.trim() ? d.description.trim() : null,
            due_date: d.due_date || null,
        }));
        post(store.url(project.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    const totalOverdue = safeTasks.filter(isOverdue).length;

    return (
        <div className="flex flex-col gap-5">
            {/* Header */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                    <h2 className="text-xl font-black tracking-tight text-white">Tablero Kanban</h2>
                    {totalOverdue > 0 && (
                        <Badge className="bg-destructive text-destructive-foreground border-destructive shadow-[0_0_10px_rgba(239,68,68,0.35)] gap-1">
                            <AlertTriangle className="h-3 w-3" />
                            {totalOverdue} vencida{totalOverdue > 1 ? 's' : ''}
                        </Badge>
                    )}
                </div>
                <Button
                    size="sm"
                    className="bg-primary text-primary-foreground hover:bg-primary/90 shadow-[0_0_15px_rgba(239,68,68,0.3)] font-bold"
                    onClick={() => setOpen(true)}
                >
                    <Plus className="h-4 w-4" /> Nueva Tarea
                </Button>
            </div>

            {/* Dialog creación — Wayfinder store */}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="bg-[#1C0F0F] border-[#3E2121] text-white sm:max-w-[480px]">
                    <DialogHeader>
                        <DialogTitle className="text-white">Nueva Tarea</DialogTitle>
                        <DialogDescription className="text-[#E8B4B4]">
                            Crea una tarea para <span className="text-white font-bold">{project?.name ?? 'este proyecto'}</span>. Se guardará en{' '}
                            <span className="font-mono text-xs bg-[#2B1A1A] border border-[#3E2121] px-1.5 py-0.5 rounded">POST /freelance/projects/{'{project}'}/tasks</span>
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleCreate} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="task-title" className="text-[#E8B4B4]">
                                Título *
                            </Label>
                            <Input
                                id="task-title"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                placeholder="Ej. Diseñar landing page"
                                className="bg-[#2B1A1A] border-[#3E2121] text-white placeholder:text-muted-foreground"
                                required
                            />
                            {errors.title && <p className="text-xs text-[#EF4444]">{errors.title}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="task-description" className="text-[#E8B4B4]">
                                Descripción
                            </Label>
                            <Textarea
                                id="task-description"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Detalles opcionales..."
                                className="bg-[#2B1A1A] border-[#3E2121] text-white placeholder:text-muted-foreground min-h-[80px]"
                                rows={3}
                            />
                            {errors.description && <p className="text-xs text-[#EF4444]">{errors.description}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label className="text-[#E8B4B4]">Estado *</Label>
                                <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                                    <SelectTrigger className="bg-[#2B1A1A] border-[#3E2121] text-white">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent className="bg-[#2B1A1A] border-[#3E2121] text-white">
                                        <SelectItem value="To Do">Pendiente</SelectItem>
                                        <SelectItem value="In Progress">En Progreso</SelectItem>
                                        <SelectItem value="Done">Completada</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.status && <p className="text-xs text-[#EF4444]">{errors.status}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label className="text-[#E8B4B4]">Prioridad</Label>
                                <Select value={data.priority} onValueChange={(v) => setData('priority', v)}>
                                    <SelectTrigger className="bg-[#2B1A1A] border-[#3E2121] text-white">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent className="bg-[#2B1A1A] border-[#3E2121] text-white">
                                        <SelectItem value="Low">Baja</SelectItem>
                                        <SelectItem value="Normal">Normal</SelectItem>
                                        <SelectItem value="High">Alta</SelectItem>
                                        <SelectItem value="Urgent">Urgente</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.priority && <p className="text-xs text-[#EF4444]">{errors.priority}</p>}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="task-due_date" className="text-[#E8B4B4]">
                                Fecha límite
                            </Label>
                            <Input
                                id="task-due_date"
                                type="date"
                                value={data.due_date}
                                onChange={(e) => setData('due_date', e.target.value)}
                                className="bg-[#2B1A1A] border-[#3E2121] text-white"
                            />
                            {errors.due_date && <p className="text-xs text-[#EF4444]">{errors.due_date}</p>}
                        </div>

                        <DialogFooter className="gap-2 sm:gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setOpen(false)}
                                className="text-[#E8B4B4] hover:bg-white/5 hover:text-white"
                                disabled={processing}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing || !data.title.trim()}
                                className="bg-primary text-primary-foreground hover:bg-primary/90 font-black shadow-[0_0_15px_rgba(239,68,68,0.3)]"
                            >
                                {processing ? 'Guardando…' : 'Crear tarea'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Kanban grid — 3 columnas rojizas */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pb-4">
                {COLUMNS.map((col) => {
                    const colTasks = getTasksByStatus(col.key);
                    const overdueInCol = colTasks.filter(isOverdue).length;
                    const isDoneCol = col.key === 'Done';
                    return (
                        <div
                            key={col.key}
                            className="flex flex-col rounded-xl border border-[#3E2121] bg-[#1C0F0F] overflow-hidden min-w-0"
                        >
                            {/* Col header */}
                            <div className={`flex items-center justify-between px-4 py-3 bg-[#2B1A1A] border-b border-[#3E2121] ${col.accent} border-l-2`}>
                                <div className="flex items-center gap-2.5">
                                    <span className={`h-2.5 w-2.5 rounded-full ${col.dot} shadow-[0_0_8px_currentColor]`} />
                                    <h3 className="text-[11px] font-black uppercase tracking-widest text-white">{col.label}</h3>
                                    {overdueInCol > 0 && !isDoneCol && (
                                        <span className="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider bg-destructive text-white px-1.5 py-0.5 rounded-full">
                                            <Clock className="h-3 w-3" /> {overdueInCol}
                                        </span>
                                    )}
                                </div>
                                <span className="inline-flex items-center justify-center min-w-[28px] h-6 px-2 rounded-full bg-[#3E2121] border border-[#3E2121] text-xs font-black text-[#E8B4B4]">
                                    {colTasks.length}
                                </span>
                            </div>

                            {/* Col body */}
                            <div className="flex flex-col gap-3 p-3 min-h-[420px] bg-[#1C0F0F]">
                                {colTasks.map((task) => (
                                    <TaskCard
                                        key={task.id}
                                        task={task}
                                        columns={COLUMNS}
                                        onStatusChange={handleStatusChange}
                                        onDelete={() => handleDelete(task.id)}
                                        isOverdue={isOverdue(task)}
                                    />
                                ))}
                                {colTasks.length === 0 && (
                                    <div className="flex flex-col items-center justify-center py-10 px-4 rounded-lg border border-dashed border-[#3E2121] bg-[#2B1A1A]/40">
                                        <p className="text-xs font-medium text-muted-foreground italic">Sin tareas</p>
                                        <p className="text-[10px] text-muted-foreground/60 mt-1">Arrastra o mueve con el menú</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Leyenda overdue */}
            {totalOverdue > 0 && (
                <p className="text-[11px] text-muted-foreground flex items-center gap-1.5">
                    <span className="h-2 w-2 rounded-full bg-destructive inline-block" /> Vencida = fecha límite superada y no completada.
                </p>
            )}
        </div>
    );
}

function TaskCard({
    task,
    columns,
    onStatusChange,
    onDelete,
    isOverdue,
}: {
    task: Task;
    columns: ColumnDef[];
    onStatusChange: (task: Task, newStatus: string) => void;
    onDelete: () => void;
    isOverdue: boolean;
}) {
    const priorityMap: Record<string, string> = {
        High: 'text-[#EF4444] bg-[#EF4444]/10 border-[#EF4444]/20',
        Alta: 'text-[#EF4444] bg-[#EF4444]/10 border-[#EF4444]/20',
        Urgent: 'text-purple-300 bg-purple-500/10 border-purple-500/20',
        Urgente: 'text-purple-300 bg-purple-500/10 border-purple-500/20',
        Normal: 'text-[#E8B4B4] bg-[#3E2121] border-[#3E2121]',
        Low: 'text-muted-foreground bg-[#1C0F0F] border-[#3E2121]',
        Baja: 'text-muted-foreground bg-[#1C0F0F] border-[#3E2121]',
    };

    const currentIdx = columns.findIndex((c) => c.key === normalizeStatus(task.status));
    const prevCol = currentIdx > 0 ? columns[currentIdx - 1] : null;
    const nextCol = currentIdx >= 0 && currentIdx < columns.length - 1 ? columns[currentIdx + 1] : null;

    return (
        <Card className="bg-[#2B1A1A] border-[#3E2121] shadow-sm hover:shadow-[0_4px_20px_rgba(239,68,68,0.08)] hover:border-primary/20 transition-all group">
            <CardContent className="p-3 space-y-3">
                {/* Title row */}
                <div className="flex justify-between items-start gap-2">
                    <h4 className="font-semibold text-sm leading-tight text-white line-clamp-2 flex-1">{task.title}</h4>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7 shrink-0 text-muted-foreground hover:text-white hover:bg-white/5 opacity-60 group-hover:opacity-100 transition-opacity"
                                aria-label="Mover tarea"
                            >
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="bg-[#2B1A1A] border-[#3E2121] text-white min-w-[180px]">
                            {columns
                                .filter((c) => c.key !== normalizeStatus(task.status))
                                .map((col) => (
                                    <DropdownMenuItem
                                        key={col.key}
                                        onClick={() => onStatusChange(task, col.key)}
                                        className="focus:bg-white/5 focus:text-white cursor-pointer"
                                    >
                                        <span className={`h-2 w-2 rounded-full ${col.dot} mr-2`} />
                                        Mover a {col.label}
                                    </DropdownMenuItem>
                                ))}
                            <DropdownMenuSeparator className="bg-[#3E2121]" />
                            <DropdownMenuItem
                                className="text-[#EF4444] focus:text-[#EF4444] focus:bg-[#EF4444]/10 cursor-pointer"
                                onClick={onDelete}
                            >
                                Eliminar
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {/* Badges: priority + overdue + tags */}
                <div className="flex flex-wrap gap-1.5 items-center">
                    {task.priority && (
                        <Badge
                            variant="outline"
                            className={`text-[10px] font-black uppercase tracking-wider px-1.5 py-0 h-5 border ${priorityMap[task.priority] ?? 'text-[#E8B4B4] bg-[#3E2121] border-[#3E2121]'}`}
                        >
                            {task.priority}
                        </Badge>
                    )}
                    {isOverdue && (
                        <Badge className="bg-destructive text-white border-destructive text-[10px] font-black uppercase tracking-wider h-5 gap-1 shadow-[0_0_8px_rgba(153,27,27,0.5)]">
                            <AlertTriangle className="h-3 w-3" /> Vencida
                        </Badge>
                    )}
                    {task.tags?.slice(0, 3).map((tag) => (
                        <Badge key={tag} variant="secondary" className="bg-[#1C0F0F] border border-[#3E2121] text-[#E8B4B4] text-[10px] px-1.5 h-5 font-medium">
                            {tag}
                        </Badge>
                    ))}
                    {task.tags && task.tags.length > 3 && (
                        <span className="text-[10px] text-muted-foreground">+{task.tags.length - 3}</span>
                    )}
                </div>

                {/* Meta row */}
                <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                    <span className={`flex items-center gap-1 ${isOverdue ? 'text-[#EF4444] font-bold' : ''}`}>
                        <Calendar className="h-3 w-3" />
                        {formatDueDate(task.due_date)}
                    </span>
                    {task.responsible && (
                        <span className="flex items-center gap-1 max-w-[110px] truncate">
                            <User className="h-3 w-3 shrink-0" />
                            <span className="truncate">{task.responsible}</span>
                        </span>
                    )}
                </div>

                {/* Quick move buttons — click para mover status vía router.patch */}
                <div className="flex items-center gap-1.5 pt-1">
                    {prevCol ? (
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-7 flex-1 bg-[#1C0F0F] border-[#3E2121] text-[#E8B4B4] hover:bg-white/5 hover:text-white hover:border-[#3E2121] text-[11px] font-bold"
                            onClick={() => onStatusChange(task, prevCol.key)}
                            aria-label={`Mover a ${prevCol.label}`}
                        >
                            <ChevronLeft className="h-3 w-3" /> {prevCol.label}
                        </Button>
                    ) : (
                        <span className="flex-1" />
                    )}
                    {nextCol ? (
                        <Button
                            size="sm"
                            className="h-7 flex-1 bg-primary text-primary-foreground hover:bg-primary/90 shadow-[0_0_10px_rgba(239,68,68,0.25)] text-[11px] font-black"
                            onClick={() => onStatusChange(task, nextCol.key)}
                            aria-label={`Mover a ${nextCol.label}`}
                        >
                            {nextCol.label} <ChevronRight className="h-3 w-3" />
                        </Button>
                    ) : (
                        <Badge variant="outline" className="flex-1 justify-center h-7 bg-emerald-500/10 border-emerald-500/20 text-emerald-400 text-[10px] font-black uppercase tracking-wider">
                            ✓ Completada
                        </Badge>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
