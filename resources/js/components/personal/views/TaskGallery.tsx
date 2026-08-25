import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Calendar, Clock } from 'lucide-react';
import type { PersonalTask } from '@/types/personal';

interface Props {
    tasks: PersonalTask[];
    onTaskClick?: (task: PersonalTask) => void;
}

export default function TaskGallery({ tasks, onTaskClick }: Props) {
    if (tasks.length === 0) {
        return <div className="text-center py-12 text-muted-foreground italic border border-dashed border-border rounded-xl bg-card">No hay tareas para mostrar.</div>;
    }

    return (
        <div className="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {tasks.map(task => (
                <Card key={task.id} className="bg-card border-border overflow-hidden hover:border-primary/30 transition-colors cursor-pointer group" onClick={() => onTaskClick?.(task)}>
                    <div className="h-2 w-full" style={{ backgroundColor: (task.project as any)?.color || '#EF4444' }} />
                    <CardContent className="p-4 flex flex-col gap-3">
                        <div>
                            <h3 className="font-bold text-sm leading-tight line-clamp-2">{task.title}</h3>
                            {task.description && typeof task.description === 'string' && <p className="text-xs text-muted-foreground line-clamp-2 mt-1">{task.description.slice(0, 80)}</p>}
                        </div>
                        <div className="flex gap-1 flex-wrap">
                            <Badge variant="outline" className="text-[9px] font-black uppercase">{task.status}</Badge>
                            {task.priority && <Badge variant="secondary" className="text-[9px]">{task.priority}</Badge>}
                        </div>
                        {(task.due_date || task.estimated_time) && (
                            <div className="flex gap-3 text-xs text-muted-foreground">
                                {task.due_date && <span className="flex items-center gap-1"><Calendar className="h-3 w-3" />{new Date(task.due_date).toLocaleDateString()}</span>}
                                {task.estimated_time && <span className="flex items-center gap-1"><Clock className="h-3 w-3" />{task.estimated_time}m</span>}
                            </div>
                        )}
                        {task.project && <Badge variant="secondary" className="text-[10px] w-fit">{task.project.name}</Badge>}
                        {task.tags && task.tags.length > 0 && (
                            <div className="flex flex-wrap gap-1">
                                {task.tags.map(t => <Badge key={t} variant="outline" className="text-[8px]">{t}</Badge>)}
                            </div>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
