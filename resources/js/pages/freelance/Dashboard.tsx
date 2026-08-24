import React from 'react';
import MainLayout from '@/layouts/main-layout';
import freelance from '@/routes/freelance';
import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Users,
    Briefcase,
    FileText,
    TrendingUp,
    Clock,
    CheckCircle2,
    Calendar,
    ArrowRight
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface DashboardProps {
    stats: {
        active_projects: number;
        pending_quotes: number;
        monthly_income: number;
        pending_tasks: number;
    };
    recent_projects: any[];
    upcoming_tasks: any[];
}

export default function Dashboard({ stats, recent_projects, upcoming_tasks }: DashboardProps) {
    return (
        <MainLayout>
            <Head title="Freelance Dashboard" />

            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Panel de Control Freelance</h1>
                        <p className="text-muted-foreground">Resumen de tu actividad y proyectos.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={freelance.clients.create().url}>
                                <Users className="mr-2 h-4 w-4" /> Cliente
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={freelance.projects.create().url}>
                                <Briefcase className="mr-2 h-4 w-4" /> Proyecto
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={freelance.quotes.create().url}>
                                <FileText className="mr-2 h-4 w-4" /> Cotización
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Proyectos Activos"
                        value={stats.active_projects}
                        icon={Briefcase}
                        description="En progreso"
                    />
                    <StatCard
                        title="Cotizaciones Pendientes"
                        value={stats.pending_quotes}
                        icon={FileText}
                        description="Esperando respuesta"
                    />
                    <StatCard
                        title="Ingresos del Mes"
                        value={`$${(stats.monthly_income ?? 0).toLocaleString()}`}
                        icon={TrendingUp}
                        description="Pagos recibidos"
                    />
                    <StatCard
                        title="Tareas Pendientes"
                        value={stats.pending_tasks}
                        icon={Clock}
                        description="Próximos vencimientos"
                    />
                </div>

                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-7">
                    <Card className="col-span-4 bg-[#193322] border-[#23482f]">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Proyectos Recientes</CardTitle>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={freelance.projects.index().url}>Ver todo <ArrowRight className="ml-2 h-4 w-4" /></Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {recent_projects.length === 0 ? (
                                    <p className="text-sm text-muted-foreground py-4 text-center">No hay proyectos recientes.</p>
                                ) : (
                                    recent_projects.map((project) => (
                                        <div key={project.id} className="flex items-center justify-between p-3 rounded-lg border border-[#23482f] bg-[#102216]">
                                            <div className="flex flex-col">
                                                <span className="font-medium">{project.name}</span>
                                                <span className="text-xs text-muted-foreground">{project.client?.name}</span>
                                            </div>
                                            <div className="flex items-center gap-4">
                                                <Badge variant="outline" className="capitalize">
                                                    {project.status.replace('_', ' ')}
                                                </Badge>
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={freelance.projects.show(project.id).url}>
                                                        <ArrowRight className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="col-span-3 bg-[#193322] border-[#23482f]">
                        <CardHeader>
                            <CardTitle>Próximas Tareas</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {upcoming_tasks.length === 0 ? (
                                    <p className="text-sm text-muted-foreground py-4 text-center">No hay tareas pendientes.</p>
                                ) : (
                                    upcoming_tasks.map((task) => (
                                        <div key={task.id} className="flex items-start gap-3 p-3 rounded-lg border border-[#23482f] bg-[#102216]">
                                            <Clock className="h-4 w-4 text-primary mt-0.5" />
                                            <div className="flex flex-col flex-1">
                                                <span className="text-sm font-medium">{task.title}</span>
                                                <div className="flex items-center justify-between mt-1">
                                                    <span className="text-[10px] text-muted-foreground">{task.project?.name}</span>
                                                    <span className="text-[10px] bg-muted px-1.5 py-0.5 rounded">
                                                        {task.due_date ? new Date(task.due_date).toLocaleDateString() : 'Sin fecha'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
}

function StatCard({ title, value, icon: Icon, description }: any) {
    return (
        <Card className="bg-[#193322] border-[#23482f]">
            <CardContent className="p-6">
                <div className="flex items-center justify-between space-y-0 pb-2">
                    <p className="text-sm font-medium text-[#92c9a4] uppercase tracking-widest">{title}</p>
                    <Icon className="h-4 w-4 text-primary" />
                </div>
                <div className="flex flex-col gap-1">
                    <div className="text-2xl font-bold">{value}</div>
                    <p className="text-xs text-muted-foreground">{description}</p>
                </div>
            </CardContent>
        </Card>
    );
}
