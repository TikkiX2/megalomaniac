import { Badge } from '@/components/ui/badge';
import type { PersonalTask } from '@/types/personal';

interface Props {
    tasks: PersonalTask[];
    onTaskClick?: (task: PersonalTask) => void;
}

export default function TaskTimeline({ tasks, onTaskClick }: Props) {
    const tasksWithDates = tasks.filter(t => t.start_date || t.due_date);
    const tasksWithoutDates = tasks.filter(t => !t.start_date && !t.due_date);

    if (tasksWithDates.length === 0) {
        return (
            <div className="flex flex-col gap-4">
                <div className="rounded-xl border border-dashed border-border bg-card p-8 text-center">
                    <p className="text-muted-foreground italic">Agrega fechas de inicio y vencimiento para ver la línea de tiempo.</p>
                    <p className="text-xs text-muted-foreground mt-2">Mostrando {tasks.length} tareas sin fechas abajo.</p>
                </div>
                {tasksWithoutDates.length > 0 && (
                    <div className="grid gap-2 md:grid-cols-2">
                        {tasksWithoutDates.map(t => (
                            <button key={t.id} onClick={() => onTaskClick?.(t)} className="text-left rounded-lg border border-border bg-card p-3 hover:bg-accent">
                                <p className="font-semibold text-sm">{t.title}</p>
                                <Badge variant="outline" className="text-[9px] mt-1">{t.status}</Badge>
                            </button>
                        ))}
                    </div>
                )}
            </div>
        );
    }

    // Compute timeline range
    const dates = tasksWithDates.flatMap(t => [t.start_date, t.due_date].filter(Boolean) as string[]).map(d => new Date(d).getTime());
    const min = Math.min(...dates);
    const max = Math.max(...dates);
    const range = max - min || 86400000;
    const todayPos = ((Date.now() - min) / range) * 100;

    return (
        <div className="flex flex-col gap-4 bg-card border border-border rounded-xl p-4 overflow-auto">
            <div className="flex justify-between text-xs text-muted-foreground">
                <span>{new Date(min).toLocaleDateString()}</span>
                <span>Hoy</span>
                <span>{new Date(max).toLocaleDateString()}</span>
            </div>

            <div className="relative flex flex-col gap-2 min-w-[600px]">
                {/* Today line */}
                {todayPos >= 0 && todayPos <= 100 && (
                    <div className="absolute top-0 bottom-0 w-0.5 bg-destructive/50 z-10" style={{ left: `${todayPos}%` }} />
                )}

                {tasksWithDates.map(task => {
                    const start = task.start_date ? new Date(task.start_date).getTime() : (task.due_date ? new Date(task.due_date).getTime() : min);
                    const end = task.due_date ? new Date(task.due_date).getTime() : start;
                    const left = ((start - min) / range) * 100;
                    const width = Math.max(2, ((end - start) / range) * 100);
                    const isDone = ['Done', 'Completada', 'Completed'].includes(task.status);

                    return (
                        <div key={task.id} className="flex items-center gap-2 h-8">
                            <span className="w-40 truncate text-xs font-medium shrink-0 text-right pr-2">{task.title}</span>
                            <div className="flex-1 relative h-6 bg-muted rounded">
                                <button
                                    onClick={() => onTaskClick?.(task)}
                                    className={`absolute h-6 rounded text-[10px] font-bold flex items-center px-2 truncate text-white ${isDone ? 'bg-emerald-500' : 'bg-primary'} hover:opacity-80`}
                                    style={{ left: `${left}%`, width: `${width}%`, minWidth: '40px' }}
                                    title={`${task.title} — ${task.status}`}
                                >
                                    {task.status}
                                </button>
                            </div>
                        </div>
                    );
                })}
            </div>

            {tasksWithoutDates.length > 0 && (
                <div className="border-t border-border pt-4">
                    <p className="text-xs font-black uppercase tracking-widest text-muted-foreground mb-2">Sin fechas ({tasksWithoutDates.length})</p>
                    <div className="flex flex-wrap gap-2">
                        {tasksWithoutDates.map(t => (
                            <button key={t.id} onClick={() => onTaskClick?.(t)} className="text-xs rounded border border-border bg-muted px-2 py-1 hover:bg-accent">{t.title}</button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
