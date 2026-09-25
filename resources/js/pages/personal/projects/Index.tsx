import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, FolderKanban, Calendar, Trash, Eye, Pencil, MoreHorizontal } from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import MainLayout from '@/layouts/main-layout';
import type { PersonalProject } from '@/types/personal';

interface Props {
    projects: { data: PersonalProject[]; links: unknown; meta?: unknown };
    filters: { status?: string; search?: string };
}

export default function PersonalProjectsIndex({ projects, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [form, setForm] = useState({ name: '', status: 'pending', color: '#EF4444', priority: 'Normal' });

    const handleSearch = (value: string) => {
        setSearch(value);
        router.get('/personal/projects', { search: value, status: filters.status }, { preserveState: true, replace: true });
    };

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/personal/projects', { ...form, type: 'personal' }, {
            onSuccess: () => setDialogOpen(false),
        });
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar proyecto?')) {
            router.delete(`/personal/projects/${id}`);
        }
    };

    const getStatusStyle = (status: string) => {
        const map: Record<string, string> = {
            pending: 'bg-amber-500/20 text-amber-500 border-amber-500/20',
            in_progress: 'bg-primary/20 text-primary border-primary/20',
            completed: 'bg-emerald-500/20 text-emerald-500 border-emerald-500/20',
            cancelled: 'bg-rose-500/20 text-rose-500',
            maintenance: 'bg-violet-500/20 text-violet-500',
            active: 'bg-primary/20 text-primary',
            archived: 'bg-muted text-muted-foreground',
        };
        return map[status] || 'bg-muted text-muted-foreground';
    };

    return (
        <MainLayout>
            <Head title="Proyectos Personales" />
            <div className="flex flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Proyectos Personales</h1>
                        <p className="text-muted-foreground">Organiza tus proyectos y tareas personales.</p>
                    </div>
                    <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                        <DialogTrigger asChild>
                            <Button className="bg-primary font-bold">
                                <Plus className="mr-2 h-4 w-4" /> Nuevo Proyecto
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="bg-card border-border">
                            <DialogHeader><DialogTitle>Crear Proyecto</DialogTitle></DialogHeader>
                            <form onSubmit={handleCreate} className="flex flex-col gap-4">
                                <div className="flex flex-col gap-2">
                                    <Label>Nombre</Label>
                                    <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Mi proyecto" required className="bg-background border-border" />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="flex flex-col gap-2">
                                        <Label>Estado</Label>
                                        <Select value={form.status} onValueChange={(v) => setForm({ ...form, status: v })}>
                                            <SelectTrigger className="bg-background border-border"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="pending">Pendiente</SelectItem>
                                                <SelectItem value="in_progress">En Progreso</SelectItem>
                                                <SelectItem value="completed">Completado</SelectItem>
                                                <SelectItem value="cancelled">Cancelado</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <Label>Color</Label>
                                        <Input type="color" value={form.color} onChange={(e) => setForm({ ...form, color: e.target.value })} className="h-10 p-1 bg-background border-border" />
                                    </div>
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label>Prioridad</Label>
                                    <Select value={form.priority} onValueChange={(v) => setForm({ ...form, priority: v })}>
                                        <SelectTrigger className="bg-background border-border"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Low">Baja</SelectItem>
                                            <SelectItem value="Normal">Normal</SelectItem>
                                            <SelectItem value="High">Alta</SelectItem>
                                            <SelectItem value="Urgent">Urgente</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button type="submit" className="bg-primary font-bold">Crear</Button>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="flex gap-4 items-center">
                    <div className="relative flex-1 max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input placeholder="Buscar proyectos..." className="pl-8 bg-card border-border" value={search} onChange={(e) => handleSearch(e.target.value)} />
                    </div>
                </div>

                {projects.data.length === 0 ? (
                    <Card className="bg-card border-border border-dashed">
                        <CardContent className="py-12 text-center flex flex-col items-center gap-3">
                            <FolderKanban className="h-10 w-10 text-muted-foreground" />
                            <p className="text-muted-foreground">No hay proyectos aún. Crea el primero.</p>
                            <Button onClick={() => setDialogOpen(true)} className="bg-primary">Crear Proyecto</Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {projects.data.map((project) => (
                            <Card key={project.id} className="bg-card border-border overflow-hidden hover:border-primary/30 transition-colors">
                                <CardContent className="p-5 flex flex-col gap-4">
                                    <div className="flex items-start justify-between">
                                        <div className="flex gap-3">
                                            <div className="h-10 w-10 rounded-lg flex items-center justify-center text-white font-black text-sm shrink-0" style={{ backgroundColor: project.color || '#EF4444' }}>
                                                {project.name.slice(0, 2).toUpperCase()}
                                            </div>
                                            <div>
                                                <h3 className="font-bold leading-none">{project.name}</h3>
                                                <p className="text-xs text-muted-foreground mt-1">{project.status}</p>
                                            </div>
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild><Button variant="ghost" size="icon" className="h-8 w-8"><MoreHorizontal className="h-4 w-4" /></Button></DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="bg-card border-border">
                                                <DropdownMenuItem asChild><Link href={`/personal/projects/${project.id}`}><Eye className="mr-2 h-4 w-4" />Ver</Link></DropdownMenuItem>
                                                <DropdownMenuItem asChild><Link href={`/personal/projects/${project.id}/edit`}><Pencil className="mr-2 h-4 w-4" />Editar</Link></DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem onClick={() => handleDelete(project.id)} className="text-destructive"><Trash className="mr-2 h-4 w-4" />Eliminar</DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>

                                    <div className="flex gap-2 flex-wrap">
                                        <Badge variant="outline" className={`text-[10px] font-black uppercase ${getStatusStyle(project.status)}`}>{project.status}</Badge>
                                        {project.priority && <Badge variant="secondary" className="text-[10px]">{project.priority}</Badge>}
                                        {project.budget && <Badge variant="outline" className="text-[10px]">${project.budget}</Badge>}
                                    </div>

                                    {project.tags && project.tags.length > 0 && (
                                        <div className="flex flex-wrap gap-1">
                                            {project.tags.map((t) => <Badge key={t} variant="secondary" className="text-[10px]">{t}</Badge>)}
                                        </div>
                                    )}

                                    <div className="flex flex-col gap-1">
                                        <div className="flex justify-between text-xs text-muted-foreground font-bold">
                                            <span>Progreso</span><span>{project.progress}%</span>
                                        </div>
                                        <Progress value={project.progress} className="h-1.5" />
                                        <span className="text-xs text-muted-foreground">{project.tasks_count ?? 0} tareas</span>
                                    </div>

                                    {(project.start_date || project.end_date) && (
                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <Calendar className="h-3 w-3" />
                                            <span>{project.start_date ? new Date(project.start_date).toLocaleDateString() : '?'} — {project.end_date ? new Date(project.end_date).toLocaleDateString() : '?'}</span>
                                        </div>
                                    )}

                                    <div className="flex gap-2 pt-2">
                                        <Button asChild variant="outline" size="sm" className="flex-1"><Link href={`/personal/projects/${project.id}`}>Ver</Link></Button>
                                        <Button asChild size="sm" className="flex-1 bg-primary"><Link href={`/personal/tasks?project_id=${project.id}`}>Tareas</Link></Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </MainLayout>
    );
}
