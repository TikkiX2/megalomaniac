import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Calendar, Download, Paperclip, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import HealthLayout from '@/layouts/health-layout';
import health from '@/routes/health';

const STATUS_LABELS: Record<string, string> = {
    scheduled: 'Programada',
    completed: 'Completada',
    cancelled: 'Cancelada',
    no_show: 'No show',
};

export default function AppointmentShow({ appointment: appointmentProp }: any) {
    const appointment = appointmentProp ?? {};
    const media = appointment.media ?? [];

    return (
        <HealthLayout>
            <Head title={appointment.title} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={health.appointments.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight text-white">{appointment.title}</h1>
                </div>

                <Card className="bg-card border-border">
                    <CardHeader>
                        <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Detalles</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-xs uppercase text-muted-foreground">Fecha y hora</p>
                            <p className="flex items-center gap-2 font-semibold text-white">
                                <Calendar className="h-4 w-4 text-primary" />
                                {appointment.scheduled_at ? new Date(appointment.scheduled_at).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' }) : '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs uppercase text-muted-foreground">Estado</p>
                            <p className="font-semibold text-white">{STATUS_LABELS[appointment.status] ?? appointment.status}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase text-muted-foreground">Persona</p>
                            <p className="font-semibold text-white">
                                {appointment.person ? `${appointment.person.first_name ?? ''} ${appointment.person.last_name ?? ''}`.trim() : '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs uppercase text-muted-foreground">Profesional</p>
                            <p className="font-semibold text-white">{appointment.provider?.name ?? '—'}</p>
                        </div>
                        <div className="md:col-span-2">
                            <p className="text-xs uppercase text-muted-foreground">Notas</p>
                            <p className="text-white/80 whitespace-pre-wrap">{appointment.notes ?? '—'}</p>
                        </div>
                    </CardContent>
                </Card>

                {media.length > 0 ? (
                    <Card className="bg-card border-border">
                        <CardHeader>
                            <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Adjuntos</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="flex flex-col gap-2">
                                {media.map((m: any) => (
                                    <li key={m.id} className="flex items-center gap-3 rounded-lg border border-border bg-background px-3 py-2">
                                        <Paperclip className="h-4 w-4 shrink-0 text-primary" />
                                        <span className="min-w-0 flex-1 truncate text-sm text-white/90">{m.file_name}</span>
                                        <a
                                            href={health.appointments.attachments.download([appointment.id, m.id]).url}
                                            className="text-primary hover:underline"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <Download className="h-4 w-4" />
                                        </a>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (confirm('¿Eliminar este adjunto?')) {
                                                    router.delete(health.appointments.attachments.destroy([appointment.id, m.id]).url);
                                                }
                                            }}
                                            className="text-destructive hover:underline"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                ) : (
                    <p className="text-sm italic text-muted-foreground">Sin adjuntos.</p>
                )}

                <div className="flex justify-end gap-2">
                    <Button variant="outline" asChild>
                        <Link href={health.appointments.edit(appointment.id).url}>Editar</Link>
                    </Button>
                </div>
            </div>
        </HealthLayout>
    );
}