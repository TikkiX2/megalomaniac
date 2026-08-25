import MainLayout from '@/layouts/main-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { ArrowLeft, Calendar, Trash, Pencil, Target, Users, CheckSquare } from 'lucide-react';
import type { PersonalProject } from '@/types/personal';

interface Props {
    project: PersonalProject;
}

export default function PersonalProjectShow({ project }: Props) {
    const handleDelete = () => {
        if (confirm('¿Eliminar proyecto y sus tareas?')) {
            router.delete(`/personal/projects/${project.id}`);
        }
    };

    return (
        <MainLayout>
            <Head title={project.name} />
            <div className="flex flex-col gap-6 p-4 md:p-6 max-w-5xl mx-auto animate-in fade-in duration-700">
                <Button variant="ghost" asChild className="w-fit">
                    <Link href="/personal/projects"><ArrowLeft className="mr-2 h-4 w-4" />Volver</Link>
                </Button>

                <div className="flex flex-col md:flex-row gap-4 md:items-start justify-between">
                    <div className="flex gap-4">
                        <div className="h-14 w-14 rounded-xl flex items-center justify-center text-white font-black text-lg shrink-0" style={{ backgroundColor: project.color || '#EF4444' }}>
                            {project.name.slice(0, 2).toUpperCase()}
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{project.name}</h1>
                            <div className="flex gap-2 mt-2 flex-wrap">
                                <Badge variant="outline" className="uppercase text-[10px] font-black">{project.status}</Badge>
                                {project.priority && <Badge variant="secondary">{project.priority}</Badge>}
                                {project.is_archived && <Badge variant="destructive">Archivado</Badge>}
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><Link href={`/personal/projects/${project.id}/edit`}><Pencil className="mr-2 h-4 w-4" />Editar</Link></Button>
                        <Button variant="destructive" onClick={handleDelete}><Trash className="mr-2 h-4 w-4" />Eliminar</Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <Card className="md:col-span-2 bg-card border-border">
                        <CardHeader><CardTitle className="text-sm uppercase tracking-widest font-black text-muted-foreground">Detalles</CardTitle></CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div className="flex flex-col gap-2">
                                <span className="text-xs font-black uppercase tracking-widest text-muted-foreground">Progreso</span>
                                <Progress value={project.progress} className="h-2" />
                                <span className="text-sm text-muted-foreground">{project.progress}% — {project.tasks_count ?? project.tasks?.length ?? 0} tareas</span>
                            </div>

                            {(project.start_date || project.end_date || project.deadline) && (
                                <div className="grid grid-cols-3 gap-4 text-sm">
                                    <div><p className="text-xs uppercase font-black text-muted-foreground">Inicio</p><p>{project.start_date ? new Date(project.start_date).toLocaleDateString() : '—'}</p></div>
                                    <div><p className="text-xs uppercase font-black text-muted-foreground">Fin</p><p>{project.end_date ? new Date(project.end_date).toLocaleDateString() : '—'}</p></div>
                                    <div><p className="text-xs uppercase font-black text-muted-foreground">Deadline</p><p>{project.deadline ? new Date(project.deadline).toLocaleDateString() : '—'}</p></div>
                                </div>
                            )}

                            {project.budget && (
                                <div><p className="text-xs uppercase font-black text-muted-foreground">Presupuesto</p><p className="text-lg font-bold">${project.budget}</p></div>
                            )}

                            {project.tags && project.tags.length > 0 && (
                                <div><p className="text-xs uppercase font-black text-muted-foreground mb-2">Tags</p><div className="flex flex-wrap gap-1">{project.tags.map(t => <Badge key={t} variant="secondary">{t}</Badge>)}</div></div>
                            )}

                            <div className="flex gap-2 pt-2">
                                <Button asChild className="bg-primary"><Link href={`/personal/tasks?project_id=${project.id}`}><CheckSquare className="mr-2 h-4 w-4" />Ver Tareas</Link></Button>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4">
                        <Card className="bg-card border-border">
                            <CardHeader><CardTitle className="text-sm flex items-center gap-2"><Target className="h-4 w-4" /> Hitos</CardTitle></CardHeader>
                            <CardContent>
                                {project.milestones && project.milestones.length > 0 ? (
                                    <div className="flex flex-col gap-2">
                                        {project.milestones.map(m => (
                                            <div key={m.id} className="rounded-lg border border-border p-3">
                                                <p className="font-semibold text-sm">{m.name}</p>
                                                <p className="text-xs text-muted-foreground">{m.status} {m.due_date ? `— ${new Date(m.due_date).toLocaleDateString()}` : ''}</p>
                                            </div>
                                        ))}
                                    </div>
                                ) : <p className="text-sm text-muted-foreground italic">Sin hitos</p>}
                            </CardContent>
                        </Card>

                        <Card className="bg-card border-border">
                            <CardHeader><CardTitle className="text-sm flex items-center gap-2"><Users className="h-4 w-4" /> Miembros</CardTitle></CardHeader>
                            <CardContent>
                                {project.members && project.members.length > 0 ? (
                                    <div className="flex flex-col gap-2">
                                        {project.members.map(m => (
                                            <div key={m.id} className="flex justify-between text-sm">
                                                <span>{m.user?.name ?? `Usuario ${m.user_id}`}</span><Badge variant="outline" className="text-[10px]">{m.role}</Badge>
                                            </div>
                                        ))}
                                    </div>
                                ) : <p className="text-sm text-muted-foreground italic">Solo tú</p>}
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {project.tasks && project.tasks.length > 0 && (
                    <Card className="bg-card border-border">
                        <CardHeader><CardTitle>Tareas del proyecto ({project.tasks.length})</CardTitle></CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {project.tasks.map((t: any) => (
                                <Link key={t.id} href={`/personal/tasks/${t.id}`} className="flex justify-between items-center rounded-lg border border-border p-3 hover:bg-accent transition-colors">
                                    <span className="font-medium">{t.title}</span>
                                    <Badge variant="outline" className="text-[10px] uppercase">{t.status}</Badge>
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </MainLayout>
    );
}
