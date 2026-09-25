import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Trash, Save } from 'lucide-react';
import { useState, useEffect } from 'react';
import YooptaEditor from '@/components/freelance/YooptaEditor';
import TaskProperties from '@/components/personal/TaskProperties';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import MainLayout from '@/layouts/main-layout';
import type { PersonalTask, PersonalProject } from '@/types/personal';

interface Props {
    task: PersonalTask;
    projects: PersonalProject[];
}

export default function PersonalTaskShow({ task, projects }: Props) {
    const [description, setDescription] = useState<any>(task.description);

    const { data, setData, patch, processing, errors } = useForm({
        title: task.title,
        status: task.status,
        priority: task.priority || 'Normal',
        project_id: task.project_id ? String(task.project_id) : 'none',
        due_date: task.due_date ? task.due_date.slice(0, 10) : '',
        start_date: task.start_date ? (task.start_date as string).slice(0, 10) : '',
        estimated_time: task.estimated_time ? String(task.estimated_time) : '',
        actual_time: task.actual_time ? String(task.actual_time) : '',
        tags: task.tags?.join(', ') ?? '',
    });

    // Sync description to form when editor changes (debounced save)
    useEffect(() => {
        // Auto-save description debounced
        const t = setTimeout(() => {
            if (JSON.stringify(description) !== JSON.stringify(task.description)) {
                router.patch(`/personal/tasks/${task.id}`, { description }, { preserveScroll: true, preserveState: true });
            }
        }, 1000);
        return () => clearTimeout(t);
    }, [description]);

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        patch(`/personal/tasks/${task.id}`, {
            preserveScroll: true,
            data: {
                title: data.title,
                status: data.status,
                priority: data.priority,
                project_id: data.project_id === 'none' ? null : Number(data.project_id),
                due_date: data.due_date || null,
                start_date: data.start_date || null,
                estimated_time: data.estimated_time ? Number(data.estimated_time) : null,
                actual_time: data.actual_time ? Number(data.actual_time) : null,
                tags: data.tags ? data.tags.split(',').map(t => t.trim()).filter(Boolean) : [],
                description,
            },
        } as any);
    };

    const handleDelete = () => {
        if (confirm('¿Eliminar tarea?')) {
            router.delete(`/personal/tasks/${task.id}`);
        }
    };

    return (
        <MainLayout>
            <Head title={task.title} />
            <div className="max-w-4xl mx-auto flex flex-col gap-6 p-4 md:p-6">
                <Button variant="ghost" asChild className="w-fit"><Link href="/personal/tasks"><ArrowLeft className="mr-2 h-4 w-4" />Volver a tareas</Link></Button>

                <form onSubmit={handleSave} className="flex flex-col gap-6">
                    <Card className="bg-card border-border">
                        <CardHeader>
                            <CardTitle className="text-sm uppercase tracking-widest font-black text-muted-foreground">Tarea</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div className="flex flex-col gap-2">
                                <Label>Título *</Label>
                                <Input value={data.title} onChange={e => setData('title', e.target.value)} className="bg-background border-border text-lg font-bold" required />
                                {errors.title && <p className="text-xs text-destructive">{errors.title}</p>}
                            </div>

                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div className="flex flex-col gap-2">
                                    <Label>Estado</Label>
                                    <Select value={data.status} onValueChange={v => setData('status', v)}>
                                        <SelectTrigger className="bg-background border-border"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Pending">Pendiente</SelectItem>
                                            <SelectItem value="In Progress">En Progreso</SelectItem>
                                            <SelectItem value="Done">Hecho</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label>Prioridad</Label>
                                    <Select value={data.priority} onValueChange={v => setData('priority', v)}>
                                        <SelectTrigger className="bg-background border-border"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Low">Baja</SelectItem>
                                            <SelectItem value="Normal">Normal</SelectItem>
                                            <SelectItem value="High">Alta</SelectItem>
                                            <SelectItem value="Urgent">Urgente</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label>Proyecto</Label>
                                    <Select value={data.project_id} onValueChange={v => setData('project_id', v)}>
                                        <SelectTrigger className="bg-background border-border"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">Sin proyecto</SelectItem>
                                            {projects.map(p => <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label>Vencimiento</Label>
                                    <Input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)} className="bg-background border-border" />
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <div className="flex flex-col gap-2"><Label>Inicio</Label><Input type="date" value={data.start_date} onChange={e => setData('start_date', e.target.value)} className="bg-background border-border" /></div>
                                <div className="flex flex-col gap-2"><Label>Est. (min)</Label><Input type="number" value={data.estimated_time} onChange={e => setData('estimated_time', e.target.value)} className="bg-background border-border" /></div>
                                <div className="flex flex-col gap-2"><Label>Real (min)</Label><Input type="number" value={data.actual_time} onChange={e => setData('actual_time', e.target.value)} className="bg-background border-border" /></div>
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label>Tags</Label>
                                <Input value={data.tags} onChange={e => setData('tags', e.target.value)} placeholder="casa, trabajo, urgente" className="bg-background border-border" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="bg-card border-border">
                        <CardHeader><CardTitle className="text-sm uppercase tracking-widest font-black text-muted-foreground">Descripción</CardTitle></CardHeader>
                        <CardContent>
                            <div className="min-h-48 rounded-lg border border-border bg-background p-2">
                                <YooptaEditor value={description} onChange={setDescription} />
                            </div>
                            <p className="text-xs text-muted-foreground mt-2">Se guarda automáticamente al editar.</p>
                        </CardContent>
                    </Card>

                    <Card className="bg-card border-border">
                        <CardHeader><CardTitle className="text-sm uppercase tracking-widest font-black text-muted-foreground">Propiedades Dinámicas</CardTitle></CardHeader>
                        <CardContent>
                            <TaskProperties task={task} />
                        </CardContent>
                    </Card>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing} className="flex-1 bg-primary font-bold"><Save className="mr-2 h-4 w-4" />Guardar</Button>
                        <Button type="button" variant="destructive" onClick={handleDelete}><Trash className="mr-2 h-4 w-4" />Eliminar</Button>
                        <Button type="button" variant="outline" asChild><Link href="/personal/tasks">Cancelar</Link></Button>
                    </div>

                    <div className="text-xs text-muted-foreground text-center">
                        Creada: {new Date(task.created_at).toLocaleString()} · Actualizada: {new Date(task.updated_at).toLocaleString()}
                    </div>
                </form>
            </div>
        </MainLayout>
    );
}
