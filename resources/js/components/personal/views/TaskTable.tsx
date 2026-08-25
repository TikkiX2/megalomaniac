import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { router } from '@inertiajs/react';
import { ArrowUpDown, Pencil, Trash } from 'lucide-react';
import type { PersonalTask } from '@/types/personal';

interface Props {
    tasks: PersonalTask[];
    sortField?: string;
    sortDirection?: string;
    onTaskClick?: (task: PersonalTask) => void;
    onSort?: (field: string) => void;
}

const statusStyle: Record<string, string> = {
    'Pending': 'bg-amber-500/20 text-amber-600 border-amber-500/20',
    'To Do': 'bg-amber-500/20 text-amber-600 border-amber-500/20',
    'In Progress': 'bg-primary/20 text-primary border-primary/20',
    'En Progreso': 'bg-primary/20 text-primary border-primary/20',
    'Done': 'bg-emerald-500/20 text-emerald-600 border-emerald-500/20',
    'Completada': 'bg-emerald-500/20 text-emerald-600',
    'Completed': 'bg-emerald-500/20 text-emerald-600',
};

const priorityStyle: Record<string, string> = {
    Low: 'bg-muted text-muted-foreground',
    Normal: 'bg-sky-500/20 text-sky-600',
    High: 'bg-amber-500/20 text-amber-600',
    Urgent: 'bg-destructive/20 text-destructive',
};

export default function TaskTable({ tasks, sortField, sortDirection, onTaskClick, onSort }: Props) {
    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar tarea?')) router.delete(`/personal/tasks/${id}`, { preserveScroll: true });
    };

    const handleStatusToggle = (task: PersonalTask) => {
        const isDone = ['Done', 'Completada', 'Completed', 'done'].includes(task.status);
        router.patch(`/personal/tasks/${task.id}`, { status: isDone ? 'Pending' : 'Done' }, { preserveScroll: true });
    };

    if (tasks.length === 0) {
        return <div className="text-center py-12 text-muted-foreground italic border border-dashed border-border rounded-xl bg-card">No hay tareas. Crea la primera.</div>;
    }

    return (
        <div className="rounded-xl border border-border bg-card overflow-hidden">
            <Table>
                <TableHeader className="bg-muted/50">
                    <TableRow className="border-border hover:bg-transparent">
                        <TableHead className="w-10"></TableHead>
                        <TableHead className="text-[10px] font-black uppercase tracking-widest text-muted-foreground cursor-pointer" onClick={() => onSort?.('title')}>
                            <span className="flex items-center gap-1">Tarea <ArrowUpDown className="h-3 w-3" /></span>
                        </TableHead>
                        <TableHead className="text-[10px] font-black uppercase tracking-widest text-muted-foreground cursor-pointer" onClick={() => onSort?.('status')}>Estado</TableHead>
                        <TableHead className="text-[10px] font-black uppercase tracking-widest text-muted-foreground cursor-pointer" onClick={() => onSort?.('priority')}>Prioridad</TableHead>
                        <TableHead className="text-[10px] font-black uppercase tracking-widest text-muted-foreground cursor-pointer" onClick={() => onSort?.('due_date')}>Vencimiento</TableHead>
                        <TableHead className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Proyecto</TableHead>
                        <TableHead className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Tags</TableHead>
                        <TableHead className="w-20"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {tasks.map((task) => (
                        <TableRow key={task.id} className="border-border hover:bg-accent/50 cursor-pointer" onClick={() => onTaskClick?.(task)}>
                            <TableCell onClick={(e) => e.stopPropagation()}><Checkbox checked={['Done', 'Completada', 'Completed'].includes(task.status)} onCheckedChange={() => handleStatusToggle(task)} /></TableCell>
                            <TableCell>
                                <div className="flex flex-col">
                                    <span className={`font-semibold text-sm ${['Done', 'Completada', 'Completed'].includes(task.status) ? 'line-through text-muted-foreground' : ''}`}>{task.title}</span>
                                    {task.estimated_time && <span className="text-xs text-muted-foreground">{task.estimated_time} min</span>}
                                </div>
                            </TableCell>
                            <TableCell><Badge variant="outline" className={`text-[10px] font-black uppercase border ${statusStyle[task.status] || 'bg-muted'}`}>{task.status}</Badge></TableCell>
                            <TableCell>{task.priority ? <Badge variant="outline" className={`text-[10px] ${priorityStyle[task.priority] || ''}`}>{task.priority}</Badge> : <span className="text-muted-foreground text-xs">—</span>}</TableCell>
                            <TableCell className="text-xs">
                                {task.due_date ? (
                                    <span className={new Date(task.due_date) < new Date() && !['Done', 'Completada', 'Completed'].includes(task.status) ? 'text-destructive font-bold' : ''}>
                                        {new Date(task.due_date).toLocaleDateString()}
                                    </span>
                                ) : '—'}
                            </TableCell>
                            <TableCell>
                                {task.project ? (
                                    <Badge variant="secondary" className="text-[10px]" style={{ backgroundColor: (task.project as any).color ? `${(task.project as any).color}20` : undefined }}>
                                        {task.project.name}
                                    </Badge>
                                ) : <span className="text-xs text-muted-foreground">Sin proyecto</span>}
                            </TableCell>
                            <TableCell>
                                <div className="flex flex-wrap gap-1">
                                    {task.tags?.slice(0, 2).map((t) => <Badge key={t} variant="secondary" className="text-[9px] px-1">{t}</Badge>)}
                                    {task.tags && task.tags.length > 2 && <span className="text-[10px] text-muted-foreground">+{task.tags.length - 2}</span>}
                                </div>
                            </TableCell>
                            <TableCell onClick={(e) => e.stopPropagation()}>
                                <div className="flex gap-1">
                                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => onTaskClick?.(task)}><Pencil className="h-3 w-3" /></Button>
                                    <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive hover:text-destructive" onClick={() => handleDelete(task.id)}><Trash className="h-3 w-3" /></Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
