import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { router } from '@inertiajs/react';
import { Calendar, Trash } from 'lucide-react';
import type { PersonalTask } from '@/types/personal';

interface Props {
    tasks: PersonalTask[];
    groupBy?: string | null;
    onTaskClick?: (task: PersonalTask) => void;
}

export default function TaskList({ tasks, groupBy, onTaskClick }: Props) {
    const handleToggle = (task: PersonalTask) => {
        const isDone = ['Done', 'Completada', 'Completed'].includes(task.status);
        router.patch(`/personal/tasks/${task.id}`, { status: isDone ? 'Pending' : 'Done' }, { preserveScroll: true });
    };

    const grouped: Record<string, PersonalTask[]> = {};
    if (groupBy === 'project_id' || groupBy === 'project') {
        tasks.forEach(t => {
            const key = t.project?.name ?? 'Sin proyecto';
            grouped[key] = grouped[key] || [];
            grouped[key].push(t);
        });
    } else if (groupBy === 'status') {
        tasks.forEach(t => {
            const key = t.status || 'Sin estado';
            grouped[key] = grouped[key] || [];
            grouped[key].push(t);
        });
    } else {
        grouped['Todas'] = tasks;
    }

    if (tasks.length === 0) {
        return <div className="text-center py-12 text-muted-foreground italic border border-dashed border-border rounded-xl bg-card">No hay tareas.</div>;
    }

    return (
        <div className="flex flex-col gap-6">
            {Object.entries(grouped).map(([group, groupTasks]) => (
                <div key={group} className="flex flex-col gap-2">
                    <h3 className="text-xs font-black uppercase tracking-widest text-muted-foreground flex items-center gap-2">
                        {group} <Badge variant="secondary" className="text-[10px]">{groupTasks.length}</Badge>
                    </h3>
                    <div className="flex flex-col gap-1 bg-card border border-border rounded-xl overflow-hidden">
                        {groupTasks.map(task => (
                            <div key={task.id} className="flex items-center gap-3 p-3 hover:bg-accent border-b border-border last:border-0 group">
                                <Checkbox checked={['Done', 'Completada', 'Completed'].includes(task.status)} onCheckedChange={() => handleToggle(task)} />
                                <button onClick={() => onTaskClick?.(task)} className="flex-1 text-left">
                                    <p className={`text-sm font-medium ${['Done', 'Completada', 'Completed'].includes(task.status) ? 'line-through text-muted-foreground' : ''}`}>{task.title}</p>
                                    <div className="flex gap-2 mt-1 flex-wrap">
                                        {task.priority && <Badge variant="outline" className="text-[9px]">{task.priority}</Badge>}
                                        {task.due_date && <span className="text-xs text-muted-foreground flex items-center gap-1"><Calendar className="h-3 w-3" />{new Date(task.due_date).toLocaleDateString()}</span>}
                                        {task.project && <Badge variant="secondary" className="text-[9px]">{task.project.name}</Badge>}
                                    </div>
                                </button>
                                <Button variant="ghost" size="icon" className="h-7 w-7 opacity-0 group-hover:opacity-100 shrink-0" onClick={() => { if (confirm('¿Eliminar?')) router.delete(`/personal/tasks/${task.id}`, { preserveScroll: true }); }}>
                                    <Trash className="h-3 w-3 text-destructive" />
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
