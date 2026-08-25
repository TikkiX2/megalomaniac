import MainLayout from '@/layouts/main-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft } from 'lucide-react';
import type { PersonalProject } from '@/types/personal';

interface Props {
    project?: PersonalProject;
}

export default function PersonalProjectForm({ project }: Props) {
    const isEdit = !!project;
    const { data, setData, post, put, processing, errors } = useForm({
        name: project?.name ?? '',
        status: project?.status ?? 'pending',
        color: project?.color ?? '#EF4444',
        icon: project?.icon ?? '',
        priority: project?.priority ?? 'Normal',
        start_date: project?.start_date ? project.start_date.slice(0, 10) : '',
        end_date: project?.end_date ? project.end_date.slice(0, 10) : '',
        deadline: project?.deadline ? (project.deadline as string).slice(0, 10) : '',
        budget: project?.budget ?? '',
        tags: project?.tags?.join(', ') ?? '',
        area: (project as any)?.area ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload: any = {
            ...data,
            tags: data.tags ? data.tags.split(',').map((t: string) => t.trim()).filter(Boolean) : [],
            budget: data.budget === '' ? null : data.budget,
            start_date: data.start_date || null,
            end_date: data.end_date || null,
            deadline: data.deadline || null,
        };
        if (isEdit) {
            put(`/personal/projects/${project!.id}`, { ...payload });
        } else {
            post('/personal/projects', payload);
        }
    };

    return (
        <MainLayout>
            <Head title={isEdit ? `Editar ${project?.name}` : 'Nuevo Proyecto'} />
            <div className="max-w-2xl mx-auto flex flex-col gap-6 p-4 md:p-6">
                <Button variant="ghost" asChild className="w-fit"><Link href="/personal/projects"><ArrowLeft className="mr-2 h-4 w-4" />Volver</Link></Button>
                <h1 className="text-2xl font-bold tracking-tight">{isEdit ? 'Editar Proyecto' : 'Nuevo Proyecto'}</h1>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4 bg-card border border-border rounded-xl p-6">
                    <div className="flex flex-col gap-2">
                        <Label>Nombre *</Label>
                        <Input value={data.name} onChange={e => setData('name', e.target.value)} className="bg-background border-border" required />
                        {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex flex-col gap-2">
                            <Label>Estado</Label>
                            <Select value={data.status} onValueChange={v => setData('status', v)}>
                                <SelectTrigger className="bg-background border-border"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pending">Pendiente</SelectItem>
                                    <SelectItem value="in_progress">En Progreso</SelectItem>
                                    <SelectItem value="completed">Completado</SelectItem>
                                    <SelectItem value="cancelled">Cancelado</SelectItem>
                                    <SelectItem value="maintenance">Mantenimiento</SelectItem>
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
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex flex-col gap-2">
                            <Label>Color</Label>
                            <Input type="color" value={data.color} onChange={e => setData('color', e.target.value)} className="h-10 p-1 bg-background border-border" />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Icono (texto)</Label>
                            <Input value={data.icon} onChange={e => setData('icon', e.target.value)} placeholder="ej: PR" className="bg-background border-border" />
                        </div>
                    </div>

                    <div className="grid grid-cols-3 gap-4">
                        <div className="flex flex-col gap-2"><Label>Inicio</Label><Input type="date" value={data.start_date} onChange={e => setData('start_date', e.target.value)} className="bg-background border-border" /></div>
                        <div className="flex flex-col gap-2"><Label>Fin</Label><Input type="date" value={data.end_date} onChange={e => setData('end_date', e.target.value)} className="bg-background border-border" /></div>
                        <div className="flex flex-col gap-2"><Label>Deadline</Label><Input type="date" value={data.deadline} onChange={e => setData('deadline', e.target.value)} className="bg-background border-border" /></div>
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label>Presupuesto</Label>
                        <Input type="number" step="0.01" value={data.budget} onChange={e => setData('budget', e.target.value)} placeholder="0.00" className="bg-background border-border" />
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label>Tags (separados por coma)</Label>
                        <Input value={data.tags} onChange={e => setData('tags', e.target.value)} placeholder="trabajo, personal, urgente" className="bg-background border-border" />
                    </div>

                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing} className="bg-primary font-bold flex-1">{isEdit ? 'Guardar' : 'Crear'}</Button>
                        <Button type="button" variant="outline" asChild><Link href="/personal/projects">Cancelar</Link></Button>
                    </div>
                </form>
            </div>
        </MainLayout>
    );
}
