import React from 'react';
import MainLayout from '@/layouts/main-layout';
import freelance from '@/routes/freelance';
import { Head, useForm, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { ArrowLeft, Save } from 'lucide-react';
import RichTextEditor from '@/components/freelance/YooptaEditor';

interface ProjectFormProps {
    project?: any;
    clients: any[];
    currencies: any[];
}

export default function ProjectForm({ project, clients, currencies }: ProjectFormProps) {
    const isEditing = !!project;

    const { data, setData, post, put, processing, errors } = useForm({
        client_id: project?.client_id || '',
        name: project?.name || '',
        description: project?.description || null, // Yoopta JSON
        status: project?.status || 'pending',
        start_date: project?.start_date || '',
        end_date: project?.end_date || '',
        deadline: project?.deadline || '',
        currency_id: project?.currency_id || (currencies.length > 0 ? currencies[0].id : ''),
        hourly_rate: project?.hourly_rate || '',
        estimated_hours: project?.estimated_hours || '',
        total_amount: project?.total_amount || '',
        area: project?.area || '',
        module: project?.module || '',
        priority: project?.priority || 'Normal',
        urgency: project?.urgency || 'Normal',
        importance: project?.importance || 'Normal',
        notes: project?.notes || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) {
            put(freelance.projects.update(project.id).url);
        } else {
            post(freelance.projects.store().url);
        }
    };

    return (
        <MainLayout>
            <Head title={isEditing ? 'Editar Proyecto' : 'Nuevo Proyecto'} />

            <div className="flex h-full flex-col gap-6 p-4 md:p-6 max-w-4xl mx-auto w-full animate-in fade-in duration-700 pb-20">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild className="bg-[#2b1a1a] border-[#3e2121] text-[#e8b4b4] hover:bg-white/5">
                        <Link href={freelance.projects.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">
                            {isEditing ? `Editar: ${project.name}` : 'Crear Nuevo Proyecto'}
                        </h1>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card className="bg-[#2b1a1a] border-[#3e2121] text-white">
                        <CardHeader>
                            <CardTitle className="text-[#e8b4b4] text-xs uppercase font-black tracking-widest">Información General</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label className="text-white/80">Cliente *</Label>
                                    <Select
                                        value={String(data.client_id)}
                                        onValueChange={(val) => setData('client_id', val)}
                                    >
                                        <SelectTrigger className="bg-[#1c0f0f] border-[#3e2121]">
                                            <SelectValue placeholder="Seleccionar cliente" />
                                        </SelectTrigger>
                                        <SelectContent className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                            {clients.map(client => (
                                                <SelectItem key={client.id} value={String(client.id)}>
                                                    {client.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.client_id && <p className="text-xs text-rose-400">{errors.client_id}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-white/80">Nombre del Proyecto *</Label>
                                    <Input
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="bg-[#1c0f0f] border-[#3e2121]"
                                        required
                                    />
                                    {errors.name && <p className="text-xs text-rose-400">{errors.name}</p>}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label className="text-white/80">Descripción</Label>
                                <RichTextEditor
                                    value={data.description}
                                    onChange={(val) => setData('description', val)}
                                    className="min-h-[200px] bg-[#1c0f0f]/50 border-[#3e2121] rounded-md"
                                />
                                {errors.description && <p className="text-xs text-rose-400">{errors.description}</p>}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <Card className="bg-[#2b1a1a] border-[#3e2121] text-white">
                            <CardHeader>
                                <CardTitle className="text-[#e8b4b4] text-xs uppercase font-black tracking-widest">Estado y Fechas</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label className="text-white/80">Estado *</Label>
                                    <Select
                                        value={data.status}
                                        onValueChange={(val) => setData('status', val)}
                                    >
                                        <SelectTrigger className="bg-[#1c0f0f] border-[#3e2121]">
                                            <SelectValue placeholder="Seleccionar estado" />
                                        </SelectTrigger>
                                        <SelectContent className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                            <SelectItem value="pending">Pendiente</SelectItem>
                                            <SelectItem value="in_progress">En Progreso</SelectItem>
                                            <SelectItem value="completed">Completado</SelectItem>
                                            <SelectItem value="maintenance">Mantenimiento</SelectItem>
                                            <SelectItem value="cancelled">Cancelado</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label className="text-white/80">Fecha Inicio</Label>
                                        <Input
                                            type="date"
                                            value={data.start_date}
                                            onChange={(e) => setData('start_date', e.target.value)}
                                            className="bg-[#1c0f0f] border-[#3e2121]"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-white/80">Deadline</Label>
                                        <Input
                                            type="date"
                                            value={data.deadline}
                                            onChange={(e) => setData('deadline', e.target.value)}
                                            className="bg-[#1c0f0f] border-[#3e2121]"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="bg-[#2b1a1a] border-[#3e2121] text-white">
                            <CardHeader>
                                <CardTitle className="text-[#e8b4b4] text-xs uppercase font-black tracking-widest">Presupuesto</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex gap-4">
                                    <div className="flex-1 space-y-2">
                                        <Label className="text-white/80">Moneda *</Label>
                                        <Select
                                            value={String(data.currency_id)}
                                            onValueChange={(val) => setData('currency_id', val)}
                                        >
                                            <SelectTrigger className="bg-[#1c0f0f] border-[#3e2121]">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                                {currencies.map(c => (
                                                    <SelectItem key={c.id} value={String(c.id)}>
                                                        {c.code} ({c.symbol})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex-1 space-y-2">
                                        <Label className="text-white/80">Monto Total</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={data.total_amount}
                                            onChange={(e) => setData('total_amount', e.target.value)}
                                            className="bg-[#1c0f0f] border-[#3e2121]"
                                        />
                                    </div>
                                </div>
                                <div className="flex gap-4">
                                    <div className="flex-1 space-y-2">
                                        <Label className="text-white/80">Tarifa/Hora</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={data.hourly_rate}
                                            onChange={(e) => setData('hourly_rate', e.target.value)}
                                            className="bg-[#1c0f0f] border-[#3e2121]"
                                        />
                                    </div>
                                    <div className="flex-1 space-y-2">
                                        <Label className="text-white/80">Horas Est.</Label>
                                        <Input
                                            type="number"
                                            step="0.1"
                                            value={data.estimated_hours}
                                            onChange={(e) => setData('estimated_hours', e.target.value)}
                                            className="bg-[#1c0f0f] border-[#3e2121]"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="bg-[#2b1a1a] border-[#3e2121] text-white">
                        <CardHeader>
                            <CardTitle className="text-[#e8b4b4] text-xs uppercase font-black tracking-widest">Propiedades Notion</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div className="space-y-2">
                                    <Label className="text-white/80">Área</Label>
                                    <Input
                                        value={data.area}
                                        onChange={(e) => setData('area', e.target.value)}
                                        className="bg-[#1c0f0f] border-[#3e2121]"
                                        placeholder="Ej. Backend"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-white/80">Módulo</Label>
                                    <Input
                                        value={data.module}
                                        onChange={(e) => setData('module', e.target.value)}
                                        className="bg-[#1c0f0f] border-[#3e2121]"
                                        placeholder="Ej. Auth"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-white/80">Prioridad</Label>
                                    <Select
                                        value={data.priority}
                                        onValueChange={(val) => setData('priority', val)}
                                    >
                                        <SelectTrigger className="bg-[#1c0f0f] border-[#3e2121]"><SelectValue /></SelectTrigger>
                                        <SelectContent className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                            <SelectItem value="Low">Baja</SelectItem>
                                            <SelectItem value="Normal">Normal</SelectItem>
                                            <SelectItem value="High">Alta</SelectItem>
                                            <SelectItem value="Urgent">Urgente</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label className="text-white/80">Importancia</Label>
                                    <Select
                                        value={data.importance}
                                        onValueChange={(val) => setData('importance', val)}
                                    >
                                        <SelectTrigger className="bg-[#1c0f0f] border-[#3e2121]"><SelectValue /></SelectTrigger>
                                        <SelectContent className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                            <SelectItem value="Low">Baja</SelectItem>
                                            <SelectItem value="Normal">Normal</SelectItem>
                                            <SelectItem value="High">Alta</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </CardContent>
                        <CardFooter className="flex justify-end gap-2 border-t border-[#3e2121] pt-4">
                            <Button variant="ghost" type="button" asChild className="text-white hover:bg-white/5">
                                <Link href={freelance.projects.index().url}>Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing} className="bg-primary text-white font-bold">
                                <Save className="mr-2 h-4 w-4" />
                                {isEditing ? 'Actualizar Proyecto' : 'Guardar Proyecto'}
                            </Button>
                        </CardFooter>
                    </Card>
                </form>
            </div>
        </MainLayout>
    );
}
