import React, { useState, useEffect } from 'react';
import MainLayout from '@/layouts/main-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Plus, Table as TableIcon, Kanban, Calendar as CalendarIcon, ListTodo, GalleryVertical, GanttChart } from 'lucide-react';
import ProjectSidebar from '@/components/personal/ProjectSidebar';
import TaskFilters from '@/components/personal/TaskFilters';
import SavedViewsBar from '@/components/personal/SavedViewsBar';
import TaskProperties from '@/components/personal/TaskProperties';
import TaskTable from '@/components/personal/views/TaskTable';
import TaskKanban from '@/components/personal/views/TaskKanban';
import TaskCalendar from '@/components/personal/views/TaskCalendar';
import TaskList from '@/components/personal/views/TaskList';
import TaskGallery from '@/components/personal/views/TaskGallery';
import TaskTimeline from '@/components/personal/views/TaskTimeline';
import type { PersonalProject, PersonalTask, TaskSavedView, TaskViewType } from '@/types/personal';
import YooptaEditor from '@/components/freelance/YooptaEditor';

interface Props {
    tasks: { data: PersonalTask[]; links: any; meta?: any };
    projects: PersonalProject[];
    savedViews: TaskSavedView[];
    filters: Record<string, any>;
}

export default function PersonalTasksIndex({ tasks, projects, savedViews, filters }: Props) {
    const [view, setView] = useState<TaskViewType>(() => {
        const saved = localStorage.getItem('personal-tasks-view') as TaskViewType | null;
        return saved || 'table';
    });
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedTask, setSelectedTask] = useState<PersonalTask | null>(null);
    const [description, setDescription] = useState<any>(null);

    useEffect(() => {
        localStorage.setItem('personal-tasks-view', view);
    }, [view]);

    useEffect(() => {
        const handler = (e: any) => {
            const v: TaskSavedView = e.detail;
            if (v.view_type && ['table', 'kanban', 'calendar', 'list', 'gallery', 'timeline'].includes(v.view_type)) {
                setView(v.view_type as TaskViewType);
            }
        };
        window.addEventListener('saved-view-apply', handler as any);
        return () => window.removeEventListener('saved-view-apply', handler as any);
    }, []);

    const { data, setData, post, processing, reset, errors } = useForm({
        title: '',
        description: null as any,
        status: 'Pending',
        priority: 'Normal',
        project_id: filters.project_id ? String(filters.project_id) : '',
        due_date: '',
        start_date: '',
        estimated_time: '',
        tags: '',
    });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        post('/personal/tasks', {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                reset();
                setDescription(null);
            },
        } as any);
    };

    // Keep form description in sync
    useEffect(() => {
        setData('description', description);
    }, [description]);

    const handleTaskClick = (task: PersonalTask) => setSelectedTask(task);
    const handleDateClick = (date: string) => {
        setData('due_date', date);
        setDialogOpen(true);
    };

    const handleProjectSelect = (id: number | null) => {
        router.get('/personal/tasks', { ...filters, project_id: id ?? undefined }, { preserveState: true, replace: true });
    };

    const handleSort = (field: string) => {
        const direction = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get('/personal/tasks', { ...filters, sort: field, direction }, { preserveState: true });
    };

    return (
        <MainLayout>
            <Head title="Tareas Personales" />
            <div className="flex flex-col lg:flex-row gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                {/* Project Sidebar - desktop */}
                <div className="hidden lg:block w-64 shrink-0">
                    <div className="sticky top-6 rounded-xl border border-border bg-card p-4">
                        <ProjectSidebar projects={projects} activeProjectId={filters.project_id ? Number(filters.project_id) : null} onSelect={handleProjectSelect} onCreateProject={() => (window.location.href = '/personal/projects')} />
                    </div>
                </div>

                {/* Main content */}
                <div className="flex-1 flex flex-col gap-4 min-w-0">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Tareas Personales</h1>
                            <p className="text-muted-foreground text-sm">Gestiona tus tareas con vistas flexibles.</p>
                        </div>
                        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                            <DialogTrigger asChild>
                                <Button className="bg-primary font-bold shrink-0"><Plus className="mr-2 h-4 w-4" />Nueva Tarea</Button>
                            </DialogTrigger>
                            <DialogContent className="bg-card border-border max-w-lg">
                                <DialogHeader><DialogTitle>Nueva Tarea</DialogTitle></DialogHeader>
                                <form onSubmit={handleCreate} className="flex flex-col gap-4">
                                    <div className="flex flex-col gap-2">
                                        <Label>Título *</Label>
                                        <Input value={data.title} onChange={e => setData('title', e.target.value)} placeholder="Qué necesitas hacer?" required className="bg-background border-border" />
                                        {errors.title && <p className="text-xs text-destructive">{errors.title}</p>}
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <Label>Descripción</Label>
                                        <YooptaEditor value={description} onChange={setDescription} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
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
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <Label>Proyecto</Label>
                                        <Select value={data.project_id || 'none'} onValueChange={v => setData('project_id', v === 'none' ? '' : v)}>
                                            <SelectTrigger className="bg-background border-border"><SelectValue placeholder="Sin proyecto" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">Sin proyecto</SelectItem>
                                                {projects.map(p => <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="flex flex-col gap-2"><Label>Vencimiento</Label><Input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)} className="bg-background border-border" /></div>
                                        <div className="flex flex-col gap-2"><Label>Inicio</Label><Input type="date" value={data.start_date} onChange={e => setData('start_date', e.target.value)} className="bg-background border-border" /></div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="flex flex-col gap-2"><Label>Tiempo est. (min)</Label><Input type="number" value={data.estimated_time} onChange={e => setData('estimated_time', e.target.value)} className="bg-background border-border" /></div>
                                        <div className="flex flex-col gap-2"><Label>Tags (coma)</Label><Input value={data.tags} onChange={e => setData('tags', e.target.value)} placeholder="casa, trabajo" className="bg-background border-border" /></div>
                                    </div>
                                    <Button type="submit" disabled={processing} className="bg-primary font-bold">Crear Tarea</Button>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>

                    {/* Mobile project selector */}
                    <div className="lg:hidden">
                        <Select value={filters.project_id ? String(filters.project_id) : 'all'} onValueChange={v => handleProjectSelect(v === 'all' ? null : Number(v))}>
                            <SelectTrigger className="bg-card border-border"><SelectValue placeholder="Proyecto" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos los proyectos</SelectItem>
                                {projects.map(p => <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </div>

                    <SavedViewsBar savedViews={savedViews} currentView={view} currentFilters={filters} />

                    <TaskFilters filters={filters} projects={projects} />

                    <Tabs value={view} onValueChange={v => setView(v as TaskViewType)} className="w-full">
                        <TabsList className="bg-card border border-border w-full justify-start overflow-auto">
                            <TabsTrigger value="table" className="gap-1"><TableIcon className="h-4 w-4" />Tabla</TabsTrigger>
                            <TabsTrigger value="kanban" className="gap-1"><Kanban className="h-4 w-4" />Kanban</TabsTrigger>
                            <TabsTrigger value="calendar" className="gap-1"><CalendarIcon className="h-4 w-4" />Calendario</TabsTrigger>
                            <TabsTrigger value="list" className="gap-1"><ListTodo className="h-4 w-4" />Lista</TabsTrigger>
                            <TabsTrigger value="gallery" className="gap-1"><GalleryVertical className="h-4 w-4" />Galería</TabsTrigger>
                            <TabsTrigger value="timeline" className="gap-1"><GanttChart className="h-4 w-4" />Timeline</TabsTrigger>
                        </TabsList>
                    </Tabs>

                    <div className="min-h-[400px]">
                        {view === 'table' && <TaskTable tasks={tasks.data} sortField={filters.sort} sortDirection={filters.direction} onTaskClick={handleTaskClick} onSort={handleSort} />}
                        {view === 'kanban' && <TaskKanban tasks={tasks.data} onTaskClick={handleTaskClick} />}
                        {view === 'calendar' && <TaskCalendar tasks={tasks.data} onTaskClick={handleTaskClick} onDateClick={handleDateClick} />}
                        {view === 'list' && <TaskList tasks={tasks.data} groupBy={filters.group_by || null} onTaskClick={handleTaskClick} />}
                        {view === 'gallery' && <TaskGallery tasks={tasks.data} onTaskClick={handleTaskClick} />}
                        {view === 'timeline' && <TaskTimeline tasks={tasks.data} onTaskClick={handleTaskClick} />}
                    </div>
                </div>
            </div>

            {/* Task detail drawer */}
            <Sheet open={!!selectedTask} onOpenChange={o => !o && setSelectedTask(null)}>
                <SheetContent className="bg-card border-border w-full sm:max-w-lg overflow-auto">
                    {selectedTask && (
                        <>
                            <SheetHeader><SheetTitle className="text-left">{selectedTask.title}</SheetTitle></SheetHeader>
                            <div className="flex flex-col gap-6 mt-6">
                                <div className="flex flex-col gap-2">
                                    <Label className="text-xs font-black uppercase tracking-widest text-muted-foreground">Descripción</Label>
                                    <YooptaEditor value={selectedTask.description} onChange={(v) => {
                                        // Debounced save could be added
                                    }} readOnly />
                                    <Button variant="outline" size="sm" onClick={() => window.location.href = `/personal/tasks/${selectedTask.id}`}>Abrir detalle completo</Button>
                                </div>
                                <TaskProperties task={selectedTask} />
                                <div className="flex gap-2">
                                    <Button variant="outline" className="flex-1" onClick={() => setSelectedTask(null)}>Cerrar</Button>
                                    <Button className="flex-1 bg-primary" onClick={() => window.location.href = `/personal/tasks/${selectedTask.id}`}>Editar</Button>
                                </div>
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </MainLayout>
    );
}
