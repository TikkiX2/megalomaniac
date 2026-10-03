import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import HealthLayout from '@/layouts/health-layout';
import health from '@/routes/health';

const TYPE_LABELS: Record<string, string> = {
    lab: 'Laboratorio',
    imaging: 'Imágenes',
    report: 'Informe',
    other: 'Otro',
};

function fullName(person: { first_name?: string; last_name?: string } | null): string {
    if (!person) return '—';
    return `${person.first_name ?? ''} ${person.last_name ?? ''}`.trim() || '—';
}

export default function StudyShow({ study }: any) {
    return (
        <HealthLayout>
            <Head title={study.title} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={health.studies.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight text-white">{study.title}</h1>
                </div>

                <Card className="bg-card border-border">
                    <CardHeader><CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Detalles</CardTitle></CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-xs uppercase text-muted-foreground">Tipo</p>
                            <p className="font-semibold text-white">{TYPE_LABELS[study.type] ?? study.type}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase text-muted-foreground">Fecha de realización</p>
                            <p className="font-semibold text-white">{study.performed_at ? new Date(study.performed_at).toLocaleDateString() : '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase text-muted-foreground">Persona</p>
                            <p className="font-semibold text-white">{fullName(study.person)}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase text-muted-foreground">Profesional</p>
                            <p className="font-semibold text-white">{study.provider?.name ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase text-muted-foreground">Condición asociada</p>
                            <p className="font-semibold text-white">{study.condition?.name ?? '—'}</p>
                        </div>
                        <div className="md:col-span-2">
                            <p className="text-xs uppercase text-muted-foreground">Notas</p>
                            <p className="text-white/80 whitespace-pre-wrap">{study.notes ?? '—'}</p>
                        </div>
                    </CardContent>
                </Card>

                {study.results && study.results.length > 0 && (
                    <Card className="bg-card border-border">
                        <CardHeader><CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Resultados</CardTitle></CardHeader>
                        <CardContent>
                            <ul className="list-disc pl-5 space-y-1 text-white/80">
                                {study.results.map((r: any) => (
                                    <li key={r.id}>{r.analyte ?? 'Resultado'}: {r.value} {r.unit ?? ''} {r.flag ? `(${r.flag})` : ''}</li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <div className="flex justify-end gap-2">
                    <Button variant="outline" asChild>
                        <Link href={health.studies.edit(study.id).url}>Editar</Link>
                    </Button>
                </div>
            </div>
        </HealthLayout>
    );
}
