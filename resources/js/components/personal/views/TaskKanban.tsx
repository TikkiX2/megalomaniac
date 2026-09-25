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
import { router } from '@inertiajs/react';
import { Calendar, Check, ChevronLeft, ChevronRight, Clock, GripVertical, Trash } from 'lucide-react';
import { useRef, useState } from 'react';
import { move as moveTaskRoute } from '@/actions/App/Http/Controllers/Personal/PersonalTaskController';
import { boardCollisionDetection } from '@/components/tasks/collisionDetection';
import { AddColumnButton, ColumnMenu, columnDotClass } from '@/components/tasks/ColumnManager';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { csrfHeaders } from '@/lib/csrf';
import type { BoardColumn, PersonalTask } from '@/types/personal';

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
    'En progreso': 1,
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

interface Props {
    tasks: PersonalTask[];
    columns: BoardColumn[];
    projectId: number | null;
    onTaskClick?: (task: PersonalTask) => void;
}

function SortableTaskCard({ task, onTaskClick, onDelete, onMove, columns, draggingRef }: {
    task: PersonalTask;
    onTaskClick?: (task: PersonalTask) => void;
    onDelete: (task: PersonalTask) => void;
    onMove: (task: PersonalTask, newStatus: string) => void;
    columns: BoardColumn[];
    draggingRef: { current: boolean };
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

    const currentKey = resolveColumnKey(task.status, columns);
    const currentIdx = columns.findIndex((column) => column.key === currentKey);
    const prevCol = currentIdx > 0 ? columns[currentIdx - 1] : null;
    const nextCol = currentIdx >= 0 && currentIdx < columns.length - 1 ? columns[currentIdx + 1] : null;

    return (
        <Card
            ref={setNodeRef}
            style={style}
            className="bg-background border-border hover:border-primary/30 transition-colors group cursor-grab active:cursor-grabbing"
            {...attributes}
            {...listeners}
            onClick={() => {
                if (!draggingRef.current) {
                    onTaskClick?.(task);
                }
            }}
        >
            <CardContent className="p-3 flex flex-col gap-2">
                <div className="flex items-start gap-2">
                    <span aria-hidden="true" className="mt-0.5 shrink-0 text-muted-foreground/60">
                        <GripVertical className="h-3.5 w-3.5" />
                    </span>
                    <p className="font-semibold text-sm leading-tight flex-1">{task.title}</p>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6 shrink-0 opacity-0 group-hover:opacity-100"
                        onClick={(event) => { event.stopPropagation(); onDelete(task); }}
                        onPointerDown={(event) => event.stopPropagation()}
                        onKeyDown={(event) => event.stopPropagation()}
                    >
                        <Trash className="h-3 w-3 text-destructive" />
                    </Button>
                </div>
                <div className="flex gap-1 flex-wrap">
                    {task.priority && <Badge variant="outline" className="text-[9px]">{task.priority}</Badge>}
                    {task.project && <Badge variant="secondary" className="text-[9px]">{task.project.name}</Badge>}
                </div>
                {(task.due_date || task.estimated_time) && (
                    <div className="flex items-center gap-3 text-xs text-muted-foreground">
                        {task.due_date && <span className="flex items-center gap-1"><Calendar className="h-3 w-3" />{new Date(task.due_date).toLocaleDateString()}</span>}
                        {task.estimated_time && <span className="flex items-center gap-1"><Clock className="h-3 w-3" />{task.estimated_time}m</span>}
                    </div>
                )}
                {task.tags && task.tags.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                        {task.tags.slice(0, 3).map((tag) => <Badge key={tag} variant="secondary" className="text-[8px] px-1">{tag}</Badge>)}
                    </div>
                )}
                <div className="flex gap-1 pt-1">
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-6 text-xs flex-1"
                        disabled={!prevCol}
                        onClick={(event) => { event.stopPropagation(); if (prevCol) onMove(task, prevCol.key); }}
                        onPointerDown={(event) => event.stopPropagation()}
                        onKeyDown={(event) => event.stopPropagation()}
                    >
                        <ChevronLeft className="h-3 w-3 mr-1" />Atrás
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-6 text-xs flex-1"
                        disabled={!nextCol}
                        onClick={(event) => { event.stopPropagation(); if (nextCol) onMove(task, nextCol.key); }}
                        onPointerDown={(event) => event.stopPropagation()}
                        onKeyDown={(event) => event.stopPropagation()}
                    >
                        Avanzar<ChevronRight className="h-3 w-3 ml-1" />
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function TaskOverlay({ task }: { task: PersonalTask }) {
    return (
        <Card className="bg-card border-primary/50 shadow-2xl rotate-2 opacity-90 w-[280px]">
            <CardContent className="p-3 flex flex-col gap-2">
                <div className="flex items-center gap-2">
                    <GripVertical className="h-3.5 w-3.5 text-primary" />
                    <p className="font-semibold text-sm leading-tight">{task.title}</p>
                </div>
                <div className="flex gap-1 flex-wrap">
                    {task.priority && <Badge variant="outline" className="text-[9px]">{task.priority}</Badge>}
                    {task.project && <Badge variant="secondary" className="text-[9px]">{task.project.name}</Badge>}
                </div>
            </CardContent>
        </Card>
    );
}

export default function TaskKanban({ tasks, columns, projectId, onTaskClick }: Props) {
    const safeTasks = Array.isArray(tasks) ? tasks : [];
    const safeColumns = Array.isArray(columns) ? columns : [];

    const [activeTask, setActiveTask] = useState<PersonalTask | null>(null);
    const [boardTasks, setBoardTasks] = useState<PersonalTask[]>(safeTasks);
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

    const hasUnknown = boardTasks.some((task) => resolveColumnKey(task.status, boardColumns) === UNKNOWN_COLUMN.key);
    const renderedColumns = hasUnknown ? [...boardColumns, UNKNOWN_COLUMN] : boardColumns;

    const grouped: Record<string, PersonalTask[]> = {};
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
                .filter((task): task is PersonalTask => Boolean(task));
            return [...others, ...moved];
        });
    };

    const persistMove = (taskId: number, status: string, orderedIds: number[]) => {
        const chain = moveQueue.current
            .then(async () => {
                const response = await fetch(moveTaskRoute.url(taskId), {
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

    const moveTask = (task: PersonalTask, targetKey: string, orderedIds: number[]) => {
        applyOptimisticMove(task.id, targetKey, orderedIds);
        persistMove(task.id, targetKey, orderedIds);
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

    const handleDelete = (task: PersonalTask) => {
        if (confirm('¿Eliminar tarea?')) router.delete(`/personal/tasks/${task.id}`, { preserveScroll: true });
    };

    const handleMove = (task: PersonalTask, newStatus: string) => {
        const targetIds = grouped[newStatus].filter((item) => item.id !== task.id).map((item) => item.id);
        moveTask(task, newStatus, [...targetIds, task.id]);
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

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between gap-3">
                <p className="text-xs text-muted-foreground">
                    {boardColumns.length} columnas · {boardTasks.length} tareas
                </p>
                <AddColumnButton projectId={projectId} onCreated={handleColumnCreated} />
            </div>

            <DndContext sensors={sensors} collisionDetection={boardCollisionDetection(renderedColumns.map((column) => column.key))} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
                <div className="flex gap-4 overflow-x-auto pb-4">
                    {renderedColumns.map((col) => (
                        <DroppableColumn
                            key={col.key}
                            col={col}
                            columns={boardColumns}
                            tasks={grouped[col.key] ?? []}
                            draggingRef={draggingRef}
                            onTaskClick={onTaskClick}
                            onDelete={handleDelete}
                            onMove={handleMove}
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
        </div>
    );
}

function DroppableColumn({
    col,
    columns,
    tasks,
    draggingRef,
    onTaskClick,
    onDelete,
    onMove,
    onColumnUpdated,
    onColumnDeleted,
    onColumnReordered,
}: {
    col: BoardColumn;
    columns: BoardColumn[];
    tasks: PersonalTask[];
    draggingRef: { current: boolean };
    onTaskClick?: (task: PersonalTask) => void;
    onDelete: (task: PersonalTask) => void;
    onMove: (task: PersonalTask, newStatus: string) => void;
    onColumnUpdated: (column: BoardColumn) => void;
    onColumnDeleted: (columnId: number, destinationKey: string | null) => void;
    onColumnReordered: (orderedIds: number[]) => void;
}) {
    const { setNodeRef, isOver } = useDroppable({ id: col.key });
    const taskIds = tasks.map((task) => String(task.id));
    const isUnknown = col.key === UNKNOWN_COLUMN.key;

    return (
        <div
            ref={setNodeRef}
            className={`flex w-[300px] shrink-0 flex-col gap-3 rounded-xl border bg-card p-3 min-h-[400px] transition-colors ${
                isOver ? 'border-primary/50 bg-primary/5' : 'border-border'
            }`}
        >
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2 min-w-0">
                    <span className={`h-2.5 w-2.5 shrink-0 rounded-full ${columnDotClass(col.color)}`} />
                    <h3 className="truncate text-xs font-black uppercase tracking-widest text-muted-foreground">{col.label}</h3>
                    {col.is_done && !isUnknown && <Check className="h-3 w-3 shrink-0 text-emerald-400" />}
                </div>
                <div className="flex items-center gap-1">
                    <Badge variant="secondary" className="text-[10px] font-black">{tasks.length}</Badge>
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

            <div className="flex flex-col gap-2 flex-1">
                <SortableContext items={taskIds} strategy={verticalListSortingStrategy}>
                    {tasks.map((task) => (
                        <SortableTaskCard
                            key={task.id}
                            task={task}
                            columns={columns}
                            draggingRef={draggingRef}
                            onTaskClick={onTaskClick}
                            onDelete={onDelete}
                            onMove={onMove}
                        />
                    ))}
                </SortableContext>
                {tasks.length === 0 && (
                    <div className="flex-1 flex items-center justify-center border border-dashed border-border rounded-lg py-8 text-xs text-muted-foreground italic">
                        Vacío
                    </div>
                )}
            </div>
        </div>
    );
}
