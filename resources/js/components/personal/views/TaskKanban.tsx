import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { router } from '@inertiajs/react';
import {
    DndContext,
    DragOverlay,
    closestCorners,
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
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Calendar, ChevronLeft, ChevronRight, Clock, GripVertical, Trash } from 'lucide-react';
import type { PersonalTask } from '@/types/personal';

const columns = [
    { id: 'To Do', title: 'Por Hacer', statuses: ['Pending', 'To Do', 'pendiente', 'Pendiente'], color: 'bg-amber-500/15 text-amber-400 border-amber-500/30' },
    { id: 'In Progress', title: 'En Progreso', statuses: ['In Progress', 'En Progreso', 'in_progress', 'En progreso'], color: 'bg-primary/15 text-primary border-primary/30' },
    { id: 'Done', title: 'Hecho', statuses: ['Done', 'Completada', 'Completed', 'done', 'completada'], color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' },
];

function normalizeStatus(status: string): string {
    for (const col of columns) {
        if (col.statuses.includes(status)) return col.id;
    }
    return 'To Do';
}

function toBackendStatus(colId: string): string {
    return colId === 'To Do' ? 'Pending' : colId === 'In Progress' ? 'In Progress' : 'Done';
}

interface Props {
    tasks: PersonalTask[];
    onTaskClick?: (task: PersonalTask) => void;
}

function SortableTaskCard({ task, onTaskClick, onDelete, onMove, colId }: {
    task: PersonalTask;
    onTaskClick?: (task: PersonalTask) => void;
    onDelete: (task: PersonalTask) => void;
    onMove: (task: PersonalTask, newStatus: string) => void;
    colId: string;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: String(task.id),
        data: { type: 'task', task, colId },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.4 : 1,
    };

    return (
        <Card ref={setNodeRef} style={style} className="bg-background border-border hover:border-primary/30 transition-colors group">
            <CardContent className="p-3 flex flex-col gap-2">
                <div className="flex items-start gap-2">
                    <button className="mt-0.5 shrink-0 cursor-grab active:cursor-grabbing text-muted-foreground hover:text-foreground" {...attributes} {...listeners}>
                        <GripVertical className="h-3.5 w-3.5" />
                    </button>
                    <p className="font-semibold text-sm leading-tight flex-1 cursor-pointer" onClick={() => onTaskClick?.(task)}>{task.title}</p>
                    <Button variant="ghost" size="icon" className="h-6 w-6 shrink-0 opacity-0 group-hover:opacity-100" onClick={() => onDelete(task)}>
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
                        {task.tags.slice(0, 3).map(t => <Badge key={t} variant="secondary" className="text-[8px] px-1">{t}</Badge>)}
                    </div>
                )}
                <div className="flex gap-1 pt-1">
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-6 text-xs flex-1"
                        disabled={normalizeStatus(task.status) === 'To Do'}
                        onClick={() => onMove(task, toBackendStatus(columns[Math.max(0, columns.findIndex(c => c.id === normalizeStatus(task.status)) - 1)].id))}
                    >
                        <ChevronLeft className="h-3 w-3 mr-1" />Atrás
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-6 text-xs flex-1"
                        disabled={normalizeStatus(task.status) === 'Done'}
                        onClick={() => onMove(task, toBackendStatus(columns[Math.min(columns.length - 1, columns.findIndex(c => c.id === normalizeStatus(task.status)) + 1)].id))}
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

export default function TaskKanban({ tasks, onTaskClick }: Props) {
    const [activeTask, setActiveTask] = useState<PersonalTask | null>(null);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor),
    );

    const grouped: Record<string, PersonalTask[]> = { 'To Do': [], 'In Progress': [], 'Done': [] };
    tasks.forEach(t => {
        const col = normalizeStatus(t.status);
        grouped[col].push(t);
    });

    const handleDragStart = (event: DragStartEvent) => {
        const { active } = event;
        const task = tasks.find(t => String(t.id) === String(active.id));
        if (task) setActiveTask(task);
    };

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;
        setActiveTask(null);

        if (!over) return;

        const activeId = String(active.id);
        const overId = String(over.id);

        const activeTask = tasks.find(t => String(t.id) === activeId);
        if (!activeTask) return;

        const activeCol = normalizeStatus(activeTask.status);

        let overCol: string;
        if (['To Do', 'In Progress', 'Done'].includes(overId)) {
            overCol = overId;
        } else {
            const overTask = tasks.find(t => String(t.id) === overId);
            overCol = overTask ? normalizeStatus(overTask.status) : activeCol;
        }

        if (activeCol === overCol) return;

        const newStatus = toBackendStatus(overCol);
        const newIndex = grouped[overCol].length;

        router.patch(`/personal/tasks/${activeTask.id}/move`, { status: newStatus, sort_order: newIndex }, { preserveScroll: true });
    };

    const handleDelete = (task: PersonalTask) => {
        if (confirm('¿Eliminar tarea?')) router.delete(`/personal/tasks/${task.id}`, { preserveScroll: true });
    };

    const handleMove = (task: PersonalTask, newStatus: string) => {
        router.patch(`/personal/tasks/${task.id}/move`, { status: newStatus }, { preserveScroll: true });
    };

    return (
        <DndContext sensors={sensors} collisionDetection={closestCorners} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {columns.map(col => (
                    <DroppableColumn key={col.id} col={col} tasks={grouped[col.id]} onTaskClick={onTaskClick} onDelete={handleDelete} onMove={handleMove} />
                ))}
            </div>
            <DragOverlay>
                {activeTask ? <TaskOverlay task={activeTask} /> : null}
            </DragOverlay>
        </DndContext>
    );
}

function DroppableColumn({ col, tasks, onTaskClick, onDelete, onMove }: {
    col: { id: string; title: string; color: string };
    tasks: PersonalTask[];
    onTaskClick?: (task: PersonalTask) => void;
    onDelete: (task: PersonalTask) => void;
    onMove: (task: PersonalTask, newStatus: string) => void;
}) {
    const { setNodeRef } = useDroppable({ id: col.id });
    const taskIds = tasks.map(t => String(t.id));

    return (
        <div ref={setNodeRef} className="flex flex-col gap-3 rounded-xl border border-border bg-card p-3 min-h-[400px]">
            <div className="flex items-center justify-between">
                <h3 className="text-xs font-black uppercase tracking-widest text-muted-foreground">{col.title}</h3>
                <Badge variant="secondary" className={`text-[10px] font-black border ${col.color}`}>{tasks.length}</Badge>
            </div>
            <div className="flex flex-col gap-2 flex-1">
                {tasks.length === 0 ? (
                    <div className="flex-1 flex items-center justify-center border border-dashed border-border rounded-lg py-8 text-xs text-muted-foreground italic">Vacío</div>
                ) : (
                    <SortableContext items={taskIds} strategy={verticalListSortingStrategy}>
                        {tasks.map(task => (
                            <SortableTaskCard
                                key={task.id}
                                task={task}
                                onTaskClick={onTaskClick}
                                onDelete={onDelete}
                                onMove={onMove}
                                colId={col.id}
                            />
                        ))}
                    </SortableContext>
                )}
            </div>
        </div>
    );
}


