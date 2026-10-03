import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import React from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import HealthLayout from '@/layouts/health-layout';
import health from '@/routes/health';

const STATUS_LABELS: Record<string, string> = {
    scheduled: 'Programada',
    completed: 'Completada',
    cancelled: 'Cancelada',
    no_show: 'No show',
};

export default function AppointmentForm({ appointment: appointmentProp, people, providers, statusOptions }: any) {
    const appointment = appointmentProp ?? {};
    const isEditing = !!appointmentProp;

    const { data, setData, post, put, processing, errors } = useForm({
        title: appointment.title || '',
        scheduled_at: appointment.scheduled_at ? String(appointment.scheduled_at).slice(0, 16) : '',
        status: appointment.status || 'scheduled',
        person_id: appointment.person_id ?? '',
        provider_id: appointment.provider_id ?? '',
        notes: appointment.notes || '',
        attachments: [] as File[],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = { forceFormData: true as const };
        if (isEditing) {
            put(health.appointments.update(appointment.id).url, options);
        } else {
            post(health.appointments.store().url, options);
        }
    };

    return (
        <HealthLayout>
            <Head title={isEditing ? `Editar ${appointment.title}` : 'Nueva cita'} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={health.appointments.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight text-white">
                        {isEditing ? 'Editar cita' : 'Nueva cita'}
                    </h1>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <Card className="bg-card border-border">
                        <CardHeader>
                            <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Datos</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="title">Título *</Label>
                                <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                                {errors.title && <p className="text-xs text-destructive">{errors.title}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="scheduled_at">Fecha y hora *</Label>
                                <Input id="scheduled_at" type="datetime-local" value={data.scheduled_at} onChange={(e) => setData('scheduled_at', e.target.value)} />
                                {errors.scheduled_at && <p className="text-xs text-destructive">{errors.scheduled_at}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label>Estado *</Label>
                                <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statusOptions.map((value: string) => (
                                            <SelectItem key={value} value={value}>
                                                {STATUS_LABELS[value] ?? value}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.status && <p className="text-xs text-destructive">{errors.status}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label>Persona</Label>
                                <Select
                                    value={data.person_id === '' ? 'none' : String(data.person_id)}
                                    onValueChange={(value) => setData('person_id', value === 'none' ? '' : value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Sin vincular" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin vincular</SelectItem>
                                        {people.map((person: any) => (
                                            <SelectItem key={person.id} value={String(person.id)}>
                                                {`${person.first_name ?? ''} ${person.last_name ?? ''}`.trim()}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.person_id && <p className="text-xs text-destructive">{errors.person_id}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label>Profesional</Label>
                                <Select
                                    value={data.provider_id === '' ? 'none' : String(data.provider_id)}
                                    onValueChange={(value) => setData('provider_id', value === 'none' ? '' : value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Sin profesional" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin profesional</SelectItem>
                                        {providers.map((provider: any) => (
                                            <SelectItem key={provider.id} value={String(provider.id)}>
                                                {provider.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.provider_id && <p className="text-xs text-destructive">{errors.provider_id}</p>}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="bg-card border-border">
                        <CardHeader>
                            <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Notas</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            {errors.notes && <p className="text-xs text-destructive">{errors.notes}</p>}
                        </CardContent>
                    </Card>

                    <Card className="bg-card border-border">
                        <CardHeader>
                            <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Archivos</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Input
                                id="attachments"
                                type="file"
                                multiple
                                onChange={(e) => setData('attachments', Array.from(e.target.files ?? []))}
                            />
                            {data.attachments.length > 0 && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {data.attachments.length} archivo(s) seleccionado(s).
                                </p>
                            )}
                            {errors.attachments && <p className="text-xs text-destructive">{errors.attachments}</p>}
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" asChild>
                            <Link href={health.appointments.index().url}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="bg-primary text-white font-bold">
                            {isEditing ? 'Guardar cambios' : 'Crear cita'}
                        </Button>
                    </div>
                </form>
            </div>
        </HealthLayout>
    );
}
