import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    useDroppable,
    useSensor,
    useSensors,
    type DragStartEvent,
    type DragEndEvent,
} from '@dnd-kit/core';
import {
    SortableContext,
    verticalListSortingStrategy,
    useSortable,
    arrayMove,
    sortableKeyboardCoordinates,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router, useForm } from '@inertiajs/react';
import {
    Plus,
    MoreHorizontal,
    Calendar,
    User,
    Clock,
    ChevronLeft,
    ChevronRight,
    AlertTriangle,
    GripVertical,
} from 'lucide-react';
import React, { useRef, useState } from 'react';
import { store, destroy, move } from '@/actions/App/Http/Controllers/Freelance/ProjectTaskController';
import { update as updateTaskRoute } from '@/actions/App/Http/Controllers/Freelance/ProjectTaskController';
import { boardCollisionDetection } from '@/components/tasks/collisionDetection';
import { AddColumnButton, ColumnMenu, columnAccentClass, columnDotClass } from '@/components/tasks/ColumnManager';
import TaskDetailDialog from '@/components/tasks/TaskDetailDialog';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
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
import { Textarea } from '@/components/ui/textarea';
import { csrfHeaders } from '@/lib/csrf';
import type { BoardColumn, YooptaBlock } from '@/types/personal';

interface Task {
    id: number;
    title: string;
    description?: YooptaBlock[] | null;
    status: string;
    priority: string;
    due_date?: string | null;
    responsible?: string | null;
    tags?: string[];
    area?: string | null;
    is_done?: boolean;
}

interface TaskBoardProps {
    project: { id: number; name?: string | null };
    tasks: Task[];
    columns: BoardColumn[];
}

const UNKNOWN_COLUMN: BoardColumn = {
    id: -1,
    user_id: 0,
    project_id: null,
    key: '__unknown',
    label: 'Sin columna',
    color: 'slate',
    sort_order: 999,
    is_done: false,
};

const LEGACY_ALIASES: Record<string, number> = {
    Pending: 0,
    'To Do': 0,
    Todo: 0,
    pendiente: 0,
    Pendiente: 0,
    'In Progress': 1,
    in_progress: 1,
    'En Progreso': 1,
    'en_progreso': 1,
    Review: 1,
    Done: 2,
    Completada: 2,
    Completed: 2,
    done: 2,
    completada: 2,
};

function resolveColumnKey(status: string, columns: BoardColumn[]): string {
    if (columns.some((column) => column.key === status)) {
        return status;
    }

    const index = LEGACY_ALIASES[status];

    if (index !== undefined && columns[index]) {
        return columns[index].key;
    }

    return UNKNOWN_COLUMN.key;
}

function isOverdue(task: Task): boolean {
    if (!task.due_date) return false;

    const due = new Date(task.due_date);
    if (isNaN(due.getTime())) return false;

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    due.setHours(0, 0, 0, 0);

    return due < today && !task.is_done;
}

function formatDueDate(due?: string | null): string {
    if (!due) return 'Sin fecha';
    const d = new Date(due);
    if (isNaN(d.getTime())) return 'Sin fecha';
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function TaskBoard({ project, tasks, columns }: TaskBoardProps) {
    const safeTasks = Array.isArray(tasks) ? tasks : [];
    const safeColumns = Array.isArray(columns) ? columns : [];

    const [open, setOpen] = useState(false);
    const [activeTask, setActiveTask] = useState<Task | null>(null);
    const [selectedTask, setSelectedTask] = useState<Task | null>(null);
    const [boardTasks, setBoardTasks] = useState<Task[]>(safeTasks);
    const [syncedTasks, setSyncedTasks] = useState(tasks);
    const [boardColumns, setBoardColumns] = useState<BoardColumn[]>(safeColumns);
    const [syncedColumns, setSyncedColumns] = useState(columns);

    const moveQueue = useRef<Promise<void>>(Promise.resolve());
    const moveFailed = useRef(false);
    const draggingRef = useRef(false);

    if (tasks !== syncedTasks) {
        setSyncedTasks(tasks);
        setBoardTasks(safeTasks);
    }

    if (columns !== syncedColumns) {
        setSyncedColumns(columns);
        setBoardColumns(safeColumns);
    }

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const { data, setData, post, processing, errors, reset, transform } = useForm({
        title: '',
        description: '',
        status: safeColumns[0]?.key ?? 'To Do',
        priority: 'Normal' as string,
        due_date: '',
    });

    const hasUnknown = boardTasks.some((task) => resolveColumnKey(task.status, boardColumns) === UNKNOWN_COLUMN.key);
    const renderedColumns = hasUnknown ? [...boardColumns, UNKNOWN_COLUMN] : boardColumns;

    const grouped: Record<string, Task[]> = {};
    renderedColumns.forEach((column) => {
        grouped[column.key] = [];
    });
    boardTasks.forEach((task) => {
        grouped[resolveColumnKey(task.status, boardColumns)].push(task);
    });

    const applyOptimisticMove = (taskId: number, targetKey: string, orderedIds: number[]) => {
        setBoardTasks((prev) => {
            const updated = prev.map((task) => (task.id === taskId ? { ...task, status: targetKey } : task));
            const ordered = new Set(orderedIds);
            const others = updated.filter((task) => !ordered.has(task.id));
            const moved = orderedIds
                .map((id) => updated.find((task) => task.id === id))
                .filter((task): task is Task => Boolean(task));
            return [...others, ...moved];
        });
    };

    const persistMove = (taskId: number, status: string, orderedIds: number[]) => {
        const chain = moveQueue.current
            .then(async () => {
                const response = await fetch(move.url(taskId), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        ...csrfHeaders(),
                    },
                    body: JSON.stringify({ status, ordered_ids: orderedIds }),
                });

                if (!response.ok) {
                    throw new Error(`No se pudo mover la tarea (${response.status})`);
                }
            })
            .catch((error) => {
                console.error(error);
                moveFailed.current = true;
            })
            .then(() => {
                if (moveQueue.current === chain && moveFailed.current) {
                    moveFailed.current = false;
                    router.reload();
                }
            });

        moveQueue.current = chain;
    };

    const moveTask = (task: Task, targetKey: string, orderedIds: number[]) => {
        applyOptimisticMove(task.id, targetKey, orderedIds);
        persistMove(task.id, targetKey, orderedIds);
    };

    const handleStatusChange = (task: Task, newStatus: string) => {
        const targetIds = (grouped[newStatus] ?? []).filter((item) => item.id !== task.id).map((item) => item.id);
        moveTask(task, newStatus, [...targetIds, task.id]);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar esta tarea?')) {
            router.delete(destroy.url(id), { preserveScroll: true });
        }
    };

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
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

    const handleDragStart = (event: DragStartEvent) => {
        draggingRef.current = true;
        const task = boardTasks.find((item) => String(item.id) === String(event.active.id));
        if (task) setActiveTask(task);
    };

    const handleDragEnd = (event: DragEndEvent) => {
        setTimeout(() => { draggingRef.current = false; }, 0);

        const { active, over } = event;
        setActiveTask(null);
        if (!over) return;

        const activeId = String(active.id);
        const overId = String(over.id);
        const task = boardTasks.find((item) => String(item.id) === activeId);
        if (!task) return;

        const activeCol = resolveColumnKey(task.status, boardColumns);

        let overCol: string;
        if (renderedColumns.some((column) => column.key === overId)) {
            overCol = overId;
        } else {
            const overTask = boardTasks.find((item) => String(item.id) === overId);
            overCol = overTask ? resolveColumnKey(overTask.status, boardColumns) : activeCol;
        }

        if (overCol === UNKNOWN_COLUMN.key) {
            return;
        }

        const colIds = grouped[overCol].map((item) => String(item.id));

        if (activeCol === overCol) {
            const oldIndex = colIds.indexOf(activeId);
            const newIndex = colIds.indexOf(overId);
            if (oldIndex === -1 || oldIndex === newIndex) return;
            moveTask(task, overCol, arrayMove(colIds, oldIndex, newIndex).map(Number));
        } else {
            const overIndex = renderedColumns.some((column) => column.key === overId) ? colIds.length : colIds.indexOf(overId);
            const newTarget = [...colIds];
            newTarget.splice(overIndex < 0 ? newTarget.length : overIndex, 0, activeId);
            moveTask(task, overCol, newTarget.map(Number));
        }
    };

    const handleColumnCreated = (column: BoardColumn) => setBoardColumns((prev) => [...prev, column]);
    const handleColumnUpdated = (column: BoardColumn) =>
        setBoardColumns((prev) => prev.map((item) => (item.id === column.id ? column : item)));
    const handleColumnReordered = (orderedIds: number[]) =>
        setBoardColumns((prev) => orderedIds.map((id) => prev.find((item) => item.id === id)).filter((item): item is BoardColumn => Boolean(item)));
    const handleColumnDeleted = (columnId: number, destinationKey: string | null) => {
        const column = boardColumns.find((item) => item.id === columnId);
        if (!column) return;

        const destination = destinationKey ? boardColumns.find((item) => item.key === destinationKey) : null;

        setBoardColumns((prev) => prev.filter((item) => item.id !== columnId));
        setBoardTasks((prev) =>
            prev.map((task) =>
                task.status === column.key
                    ? { ...task, status: destination?.key ?? task.status, is_done: destination?.is_done ?? task.is_done }
                    : task,
            ),
        );
    };

    const totalOverdue = boardTasks.filter(isOverdue).length;

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
                <div className="flex items-center gap-2">
                    <AddColumnButton projectId={project.id} onCreated={handleColumnCreated} />
                    <Button
                        size="sm"
                        className="bg-primary text-primary-foreground hover:bg-primary/90 shadow-[0_0_15px_rgba(239,68,68,0.3)] font-bold"
                        onClick={() => setOpen(true)}
                    >
                        <Plus className="h-4 w-4" /> Nueva Tarea
                    </Button>
                </div>
            </div>

            {/* Dialog creación */}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="bg-[#1C0F0F] border-[#3E2121] text-white sm:max-w-[480px]">
                    <DialogHeader>
                        <DialogTitle className="text-white">Nueva Tarea</DialogTitle>
                        <DialogDescription className="text-[#E8B4B4]">
                            Crea una tarea para <span className="text-white font-bold">{project?.name ?? 'este proyecto'}</span>.
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
                                        {boardColumns.map((column) => (
                                            <SelectItem key={column.key} value={column.key}>
                                                {column.label}
                                            </SelectItem>
                                        ))}
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

            {/* Kanban grid */}
            <DndContext sensors={sensors} collisionDetection={boardCollisionDetection(renderedColumns.map((column) => column.key))} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
                <div className="flex gap-4 overflow-x-auto pb-4">
                    {renderedColumns.map((col) => (
                        <DroppableColumn
                            key={col.key}
                            col={col}
                            columns={boardColumns}
                            tasks={grouped[col.key] ?? []}
                            draggingRef={draggingRef}
                            onStatusChange={handleStatusChange}
                            onDelete={handleDelete}
                            onOpen={setSelectedTask}
                            onColumnUpdated={handleColumnUpdated}
                            onColumnDeleted={handleColumnDeleted}
                            onColumnReordered={handleColumnReordered}
                        />
                    ))}
                </div>

                <DragOverlay>
                    {activeTask ? <TaskOverlay task={activeTask} /> : null}
                </DragOverlay>
            </DndContext>

            {/* Leyenda overdue */}
            {totalOverdue > 0 && (
                <p className="text-[11px] text-muted-foreground flex items-center gap-1.5">
                    <span className="h-2 w-2 rounded-full bg-destructive inline-block" /> Vencida = fecha límite superada y no completada.
                </p>
            )}

            <TaskDetailDialog
                open={!!selectedTask}
                onOpenChange={(isOpen) => !isOpen && setSelectedTask(null)}
                task={selectedTask}
                columns={boardColumns}
                variant="freelance"
                updateUrl={(taskId) => updateTaskRoute.url(taskId)}
                deleteUrl={(taskId) => destroy.url(taskId)}
                onSaved={(updated) => {
                    setBoardTasks((prev) => prev.map((task) => (task.id === updated.id ? ({ ...task, ...updated } as unknown as Task) : task)));
                    setSelectedTask((prev) => (prev && prev.id === updated.id ? ({ ...prev, ...updated } as unknown as Task) : prev));
                }}
                onDeleted={(taskId) => {
                    setBoardTasks((prev) => prev.filter((task) => task.id !== taskId));
                    setSelectedTask(null);
                }}
            />
        </div>
    );
}

function DroppableColumn({
    col,
    columns,
    tasks,
    draggingRef,
    onStatusChange,
    onDelete,
    onOpen,
    onColumnUpdated,
    onColumnDeleted,
    onColumnReordered,
}: {
    col: BoardColumn;
    columns: BoardColumn[];
    tasks: Task[];
    draggingRef: { current: boolean };
    onStatusChange: (task: Task, newStatus: string) => void;
    onDelete: (id: number) => void;
    onOpen: (task: Task) => void;
    onColumnUpdated: (column: BoardColumn) => void;
    onColumnDeleted: (columnId: number, destinationKey: string | null) => void;
    onColumnReordered: (orderedIds: number[]) => void;
}) {
    const { setNodeRef, isOver } = useDroppable({ id: col.key });
    const taskIds = tasks.map((task) => String(task.id));
    const isUnknown = col.key === UNKNOWN_COLUMN.key;
    const overdueInCol = tasks.filter(isOverdue).length;

    return (
        <div
            ref={setNodeRef}
            className={`flex w-[320px] shrink-0 flex-col rounded-xl border overflow-hidden transition-colors ${
                isOver ? 'border-primary/50 bg-primary/5' : 'border-[#3E2121] bg-[#1C0F0F]'
            }`}
        >
            {/* Col header */}
            <div className={`flex items-center justify-between px-4 py-3 bg-[#2B1A1A] border-b border-[#3E2121] ${columnAccentClass(col.color)} border-l-2`}>
                <div className="flex items-center gap-2.5 min-w-0">
                    <span className={`h-2.5 w-2.5 shrink-0 rounded-full ${columnDotClass(col.color)} shadow-[0_0_8px_currentColor]`} />
                    <h3 className="truncate text-[11px] font-black uppercase tracking-widest text-white">{col.label}</h3>
                    {overdueInCol > 0 && !col.is_done && (
                        <span className="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider bg-destructive text-white px-1.5 py-0.5 rounded-full">
                            <Clock className="h-3 w-3" /> {overdueInCol}
                        </span>
                    )}
                </div>
                <div className="flex items-center gap-1">
                    <span className="inline-flex items-center justify-center min-w-[28px] h-6 px-2 rounded-full bg-[#3E2121] border border-[#3E2121] text-xs font-black text-[#E8B4B4]">
                        {tasks.length}
                    </span>
                    {!isUnknown && (
                        <ColumnMenu
                            column={col}
                            columns={columns}
                            taskCount={tasks.length}
                            onUpdated={onColumnUpdated}
                            onDeleted={onColumnDeleted}
                            onReordered={onColumnReordered}
                        />
                    )}
                </div>
            </div>

            {/* Col body */}
            <div className="flex flex-col gap-3 p-3 min-h-[420px] bg-[#1C0F0F]">
                <SortableContext items={taskIds} strategy={verticalListSortingStrategy}>
                    {tasks.map((task) => (
                        <TaskCard
                            key={task.id}
                            task={task}
                            columns={columns}
                            draggingRef={draggingRef}
                            onStatusChange={onStatusChange}
                            onDelete={() => onDelete(task.id)}
                            onOpen={() => onOpen(task)}
                            isOverdue={isOverdue(task)}
                        />
                    ))}
                </SortableContext>
                {tasks.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-10 px-4 rounded-lg border border-dashed border-[#3E2121] bg-[#2B1A1A]/40">
                        <p className="text-xs font-medium text-muted-foreground italic">Sin tareas</p>
                        <p className="text-[10px] text-muted-foreground/60 mt-1">Arrastra o mueve con el menú</p>
                    </div>
                )}
            </div>
        </div>
    );
}

function TaskCard({
    task,
    columns,
    draggingRef,
    onStatusChange,
    onDelete,
    onOpen,
    isOverdue,
}: {
    task: Task;
    columns: BoardColumn[];
    draggingRef: { current: boolean };
    onStatusChange: (task: Task, newStatus: string) => void;
    onDelete: () => void;
    onOpen: () => void;
    isOverdue: boolean;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: String(task.id),
        data: { type: 'task', task },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.4 : 1,
    };

    const priorityMap: Record<string, string> = {
        High: 'text-[#EF4444] bg-[#EF4444]/10 border-[#EF4444]/20',
        Alta: 'text-[#EF4444] bg-[#EF4444]/10 border-[#EF4444]/20',
        Urgent: 'text-purple-300 bg-purple-500/10 border-purple-500/20',
        Urgente: 'text-purple-300 bg-purple-500/10 border-purple-500/20',
        Normal: 'text-[#E8B4B4] bg-[#3E2121] border-[#3E2121]',
        Low: 'text-muted-foreground bg-[#1C0F0F] border-[#3E2121]',
        Baja: 'text-muted-foreground bg-[#1C0F0F] border-[#3E2121]',
    };

    const currentIdx = columns.findIndex((column) => column.key === resolveColumnKey(task.status, columns));
    const prevCol = currentIdx > 0 ? columns[currentIdx - 1] : null;
    const nextCol = currentIdx >= 0 && currentIdx < columns.length - 1 ? columns[currentIdx + 1] : null;

    return (
        <Card
            ref={setNodeRef}
            style={style}
            className="bg-[#2B1A1A] border-[#3E2121] shadow-sm hover:shadow-[0_4px_20px_rgba(239,68,68,0.08)] hover:border-primary/20 transition-all group cursor-grab active:cursor-grabbing"
            {...attributes}
            {...listeners}
            onClick={() => {
                if (!draggingRef.current) {
                    onOpen();
                }
            }}
        >
            <CardContent className="p-3 space-y-3">
                {/* Title row with grip + dropdown */}
                <div className="flex justify-between items-start gap-2">
                    <div className="flex items-start gap-2 flex-1 min-w-0">
                        <span aria-hidden="true" className="mt-0.5 shrink-0 text-muted-foreground/60">
                            <GripVertical className="h-3.5 w-3.5" />
                        </span>
                        <h4 className="font-semibold text-sm leading-tight text-white line-clamp-2">{task.title}</h4>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7 shrink-0 text-muted-foreground hover:text-white hover:bg-white/5 opacity-60 group-hover:opacity-100 transition-opacity"
                                aria-label="Mover tarea"
                                onPointerDown={(e) => e.stopPropagation()}
                                onKeyDown={(e) => e.stopPropagation()}
                                onClick={(e) => e.stopPropagation()}
                            >
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="bg-[#2B1A1A] border-[#3E2121] text-white min-w-[180px]">
                            {columns
                                .filter((column) => column.key !== resolveColumnKey(task.status, columns))
                                .map((column) => (
                                    <DropdownMenuItem
                                        key={column.key}
                                        onClick={() => onStatusChange(task, column.key)}
                                        className="focus:bg-white/5 focus:text-white cursor-pointer"
                                    >
                                        <span className={`h-2 w-2 rounded-full ${columnDotClass(column.color)} mr-2`} />
                                        Mover a {column.label}
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
                <div className="flex flex-wrap gap-1.5 items-center pl-5">
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
                <div className="flex items-center justify-between text-[11px] text-muted-foreground pl-5">
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

                {/* Quick move buttons */}
                <div className="flex items-center gap-1.5 pt-1 pl-5">
                    {prevCol ? (
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-7 flex-1 bg-[#1C0F0F] border-[#3E2121] text-[#E8B4B4] hover:bg-white/5 hover:text-white hover:border-[#3E2121] text-[11px] font-bold"
                            onClick={(e) => { e.stopPropagation(); onStatusChange(task, prevCol.key); }}
                            onPointerDown={(e) => e.stopPropagation()}
                            onKeyDown={(e) => e.stopPropagation()}
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
                            onClick={(e) => { e.stopPropagation(); onStatusChange(task, nextCol.key); }}
                            onPointerDown={(e) => e.stopPropagation()}
                            onKeyDown={(e) => e.stopPropagation()}
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

function TaskOverlay({ task }: { task: Task }) {
    const priorityMap: Record<string, string> = {
        High: 'text-[#EF4444] bg-[#EF4444]/10 border-[#EF4444]/20',
        Alta: 'text-[#EF4444] bg-[#EF4444]/10 border-[#EF4444]/20',
        Urgent: 'text-purple-300 bg-purple-500/10 border-purple-500/20',
        Normal: 'text-[#E8B4B4] bg-[#3E2121] border-[#3E2121]',
    };

    return (
        <Card className="bg-[#2B1A1A] border-primary/50 shadow-2xl rotate-2 opacity-90 w-[280px]">
            <CardContent className="p-3 space-y-2">
                <div className="flex items-center gap-2">
                    <GripVertical className="h-3.5 w-3.5 text-primary" />
                    <p className="font-semibold text-sm leading-tight text-white">{task.title}</p>
                </div>
                <div className="flex gap-1.5 flex-wrap pl-5">
                    {task.priority && (
                        <Badge variant="outline" className={`text-[10px] font-black uppercase tracking-wider px-1.5 py-0 h-5 border ${priorityMap[task.priority] ?? 'text-[#E8B4B4] bg-[#3E2121] border-[#3E2121]'}`}>
                            {task.priority}
                        </Badge>
                    )}
                    {task.tags?.slice(0, 2).map((tag) => (
                        <Badge key={tag} variant="secondary" className="bg-[#1C0F0F] border border-[#3E2121] text-[#E8B4B4] text-[10px] px-1.5 h-5 font-medium">
                            {tag}
                        </Badge>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
