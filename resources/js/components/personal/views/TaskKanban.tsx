import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { router } from '@inertiajs/react';
import { Calendar, ChevronLeft, ChevronRight, Clock, Trash } from 'lucide-react';
import type { PersonalTask } from '@/types/personal';

const columns = [
    { id: 'To Do', title: 'Por Hacer', statuses: ['Pending', 'To Do', 'pendiente', 'Pendiente'] },
    { id: 'In Progress', title: 'En Progreso', statuses: ['In Progress', 'En Progreso', 'in_progress', 'En progreso'] },
    { id: 'Done', title: 'Hecho', statuses: ['Done', 'Completada', 'Completed', 'done', 'completada'] },
];

function normalizeStatus(status: string): string {
    for (const col of columns) {
        if (col.statuses.includes(status)) return col.id;
    }
    return 'To Do';
}

function nextStatus(current: string, dir: 1 | -1): string {
    const idx = columns.findIndex(c => c.id === normalizeStatus(current));
    const nextIdx = Math.max(0, Math.min(columns.length - 1, idx + dir));
    return columns[nextIdx].id === 'To Do' ? 'Pending' : columns[nextIdx].id === 'In Progress' ? 'In Progress' : 'Done';
}

interface Props {
    tasks: PersonalTask[];
    onTaskClick?: (task: PersonalTask) => void;
}

export default function TaskKanban({ tasks, onTaskClick }: Props) {
    const handleMove = (task: PersonalTask, newStatus: string) => {
        router.patch(`/personal/tasks/${task.id}/move`, { status: newStatus }, { preserveScroll: true });
    };

    const handleDelete = (task: PersonalTask) => {
        if (confirm('¿Eliminar tarea?')) router.delete(`/personal/tasks/${task.id}`, { preserveScroll: true });
    };

    const grouped: Record<string, PersonalTask[]> = { 'To Do': [], 'In Progress': [], 'Done': [] };
    tasks.forEach(t => {
        const col = normalizeStatus(t.status);
        grouped[col].push(t);
    });

    return (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {columns.map(col => (
                <div key={col.id} className="flex flex-col gap-3 rounded-xl border border-border bg-card p-3 min-h-[400px]">
                    <div className="flex items-center justify-between">
                        <h3 className="text-xs font-black uppercase tracking-widest text-muted-foreground">{col.title}</h3>
                        <Badge variant="secondary" className="text-[10px] font-black">{grouped[col.id].length}</Badge>
                    </div>
                    <div className="flex flex-col gap-2 flex-1">
                        {grouped[col.id].length === 0 ? (
                            <div className="flex-1 flex items-center justify-center border border-dashed border-border rounded-lg py-8 text-xs text-muted-foreground italic">Vacío</div>
                        ) : (
                            grouped[col.id].map(task => (
                                <Card key={task.id} className="bg-background border-border hover:border-primary/30 transition-colors cursor-pointer group">
                                    <CardContent className="p-3 flex flex-col gap-2">
                                        <div className="flex justify-between items-start gap-2">
                                            <p className="font-semibold text-sm leading-tight" onClick={() => onTaskClick?.(task)}>{task.title}</p>
                                            <Button variant="ghost" size="icon" className="h-6 w-6 shrink-0 opacity-0 group-hover:opacity-100" onClick={() => handleDelete(task)}>
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
                                                onClick={() => handleMove(task, nextStatus(task.status, -1))}
                                            >
                                                <ChevronLeft className="h-3 w-3 mr-1" />Atrás
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="h-6 text-xs flex-1"
                                                disabled={normalizeStatus(task.status) === 'Done'}
                                                onClick={() => handleMove(task, nextStatus(task.status, 1))}
                                            >
                                                Avanzar<ChevronRight className="h-3 w-3 ml-1" />
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}
