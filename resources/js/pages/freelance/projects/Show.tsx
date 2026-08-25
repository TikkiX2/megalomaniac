import React, { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import freelance from '@/routes/freelance';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger
} from '@/components/ui/tabs';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ArrowLeft,
    Pencil,
    Calendar,
    User,
    Briefcase,
    CheckCircle2,
    Clock,
    DollarSign,
    ExternalLink,
    FileText,
    MessageSquare,
    Zap
} from 'lucide-react';
import RichTextEditor from '@/components/freelance/YooptaEditor';
import MediaGallery from '@/components/freelance/MediaGallery';
import TaskBoard from '@/components/freelance/TaskBoard';
import CommentSection from '@/components/freelance/CommentSection';

export default function ProjectShow({ project, tasks, comments, currencies }: any) {
    const { data, setData, patch, processing } = useForm({
        status: project.status,
    });

    const handleStatusChange = (newStatus: string) => {
        setData('status', newStatus);
        patch(freelance.projects.update(project.id).url, {
            preserveScroll: true,
        });
    };

    const getStatusBadge = (status: string) => {
        const styles: Record<string, string> = {
            'pending': 'bg-gray-500/20 text-gray-500',
            'in_progress': 'bg-primary/20 text-primary border-primary/20',
            'completed': 'bg-primary/20 text-primary',
            'maintenance': 'bg-purple-500/20 text-purple-500',
            'cancelled': 'bg-rose-500/20 text-rose-500',
        };
        return <Badge variant="outline" className={`border-0 font-black uppercase tracking-tighter text-[10px] ${styles[status] || ''}`}>{status.replace('_', ' ')}</Badge>;
    };

    return (
        <MainLayout>
            <Head title={`Proyecto: ${project.name}`} />

            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                {/* Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild className="bg-[#2b1a1a] border-[#3e2121] text-[#e8b4b4] hover:bg-white/5">
                            <Link href={freelance.projects.index().url}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold tracking-tight text-white">{project.name}</h1>
                                {getStatusBadge(project.status)}
                            </div>
                            <p className="text-sm text-muted-foreground flex items-center gap-2 mt-1">
                                <User className="h-3 w-3" /> {project.client?.name}
                                {project.notion_page_id && (
                                    <Badge variant="secondary" className="bg-white/5 text-[10px] h-4">Notion Synced</Badge>
                                )}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild className="bg-[#2b1a1a] border-[#3e2121] text-[#e8b4b4] hover:bg-white/5">
                            <Link href={freelance.projects.edit(project.id).url}>
                                <Pencil className="mr-2 h-4 w-4" /> Editar
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    {/* Main Content Area */}
                    <div className="lg:col-span-3 space-y-6">
                        <Tabs defaultValue="overview" className="w-full">
                            <TabsList className="bg-[#2b1a1a] border border-[#3e2121] p-1 h-12">
                                <TabsTrigger value="overview" className="data-[state=active]:bg-[#1c0f0f] data-[state=active]:text-primary px-6">Resumen</TabsTrigger>
                                <TabsTrigger value="tasks" className="data-[state=active]:bg-[#1c0f0f] data-[state=active]:text-primary px-6">Tareas</TabsTrigger>
                                <TabsTrigger value="comments" className="data-[state=active]:bg-[#1c0f0f] data-[state=active]:text-primary px-6">Discusión</TabsTrigger>
                                <TabsTrigger value="files" className="data-[state=active]:bg-[#1c0f0f] data-[state=active]:text-primary px-6">Archivos</TabsTrigger>
                            </TabsList>

                            <TabsContent value="overview" className="mt-6 space-y-6">
                                <Card className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                    <CardHeader>
                                        <CardTitle className="text-[#e8b4b4] text-xs uppercase font-black tracking-widest">Descripción del Proyecto</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <RichTextEditor value={project.description} readOnly className="border-0 p-0 shadow-none min-h-[100px]" />
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="tasks" className="mt-6">
                                <TaskBoard project={project} tasks={tasks || project.tasks || []} />
                            </TabsContent>

                            <TabsContent value="comments" className="mt-6">
                                <CommentSection project={project} comments={comments || project.comments || []} />
                            </TabsContent>

                            <TabsContent value="files" className="mt-6">
                                <Card className="bg-[#2b1a1a] border-[#3e2121]">
                                    <CardHeader>
                                        <CardTitle className="text-[#e8b4b4] text-xs uppercase font-black tracking-widest">Documentos y Archivos</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <MediaGallery
                                            files={project.media || []}
                                            uploadUrl={freelance.projects.media.upload(project.id).url}
                                            deleteUrl={freelance.media.delete('__ID__').url}
                                            downloadUrl={freelance.media.download('__ID__').url}
                                        />
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </div>

                    {/* Sidebar Info */}
                    <div className="space-y-6">
                        <Card className="bg-[#2b1a1a] border-[#3e2121] text-white">
                            <CardHeader>
                                <CardTitle className="text-[#e8b4b4] text-xs uppercase font-black tracking-widest">Detalles del Proyecto</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1">
                                    <p className="text-[10px] text-muted-foreground uppercase font-bold">Área / Módulo</p>
                                    <p className="text-sm font-semibold">{project.area} • {project.module}</p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-[10px] text-muted-foreground uppercase font-bold">Prioridad</p>
                                    <Badge variant="outline" className="border-primary/20 text-primary uppercase text-[10px]">{project.priority || 'Normal'}</Badge>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-[10px] text-muted-foreground uppercase font-bold">Fecha Límite</p>
                                    <div className="flex items-center gap-2 text-sm font-semibold">
                                        <Calendar className="h-4 w-4 text-primary" />
                                        {project.deadline ? new Date(project.deadline).toLocaleDateString() : 'Sin fecha'}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="bg-[#1c0f0f] border-primary/20 text-white">
                            <CardHeader>
                                <CardTitle className="text-[#e8b4b4] text-xs uppercase font-black tracking-widest">Finanzas</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1">
                                    <p className="text-[10px] text-muted-foreground uppercase font-bold">Total Presupuestado</p>
                                    <p className="text-2xl font-black text-white">{project.currency?.symbol} {Number(project.total_amount).toLocaleString()}</p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-[10px] text-[#e8b4b4] uppercase font-bold">Pagado hasta la fecha</p>
                                    <p className="text-lg font-bold text-primary">{project.currency?.symbol} {Number(project.paid_amount).toLocaleString()}</p>
                                </div>
                                <div className="space-y-2 pt-2">
                                    <div className="flex justify-between text-[10px] font-bold">
                                        <span>Progreso de Pago</span>
                                        <span>{Math.round((project.paid_amount / project.total_amount) * 100)}%</span>
                                    </div>
                                    <Progress value={(project.paid_amount / project.total_amount) * 100} className="h-1.5" />
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
