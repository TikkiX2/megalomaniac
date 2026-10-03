import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, Paperclip, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { StudyResultChart } from '@/components/health/StudyResultChart';
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

const FLAG_LABELS: Record<string, string> = {
    low: 'Bajo',
    normal: 'Normal',
    high: 'Alto',
    unknown: 'Desconocido',
};

function fullName(person: { first_name?: string; last_name?: string } | null): string {
    if (!person) return '—';
    return `${person.first_name ?? ''} ${person.last_name ?? ''}`.trim() || '—';
}

export default function StudyShow({ study: studyProp, evolution }: any) {
    // Normalización del prop (requisito React Compiler del proyecto).
    const study = studyProp ?? {};
    const evolutions: Record<string, Array<{ date: string; value: number; unit?: string; flag?: string | null }>> =
        evolution ?? {};

    const analytes = useMemo(() => {
        const seen = new Set<string>();
        const list = Object.keys(evolutions).filter((a) => evolutions[a]?.length > 0);
        for (const r of study.results ?? []) {
            if (r.analyte && !list.includes(r.analyte)) list.push(r.analyte);
        }
        return list.filter((a) => !seen.has(a) && seen.add(a));
    }, [study.results, evolutions]);

    const [selected, setSelected] = useState<string | null>(null);
    const analyte = selected ?? analytes[0] ?? null;
    const chartPoints = analyte ? evolutions[analyte] ?? [] : [];

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
                    <CardHeader>
                        <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">
                            Detalles
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-xs uppercase text-muted-foreground">Tipo</p>
                            <p className="font-semibold text-white">
                                {TYPE_LABELS[study.type] ?? study.type}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs uppercase text-muted-foreground">Fecha de realización</p>
                            <p className="font-semibold text-white">
                                {study.performed_at ? new Date(study.performed_at).toLocaleDateString() : '—'}
                            </p>
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

                {analytes.length > 0 && (
                    <Card className="bg-card border-border">
                        <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">
                                Evolución
                            </CardTitle>
                            {analytes.length > 1 && (
                                <select
                                    value={analyte ?? ''}
                                    onChange={(e) => setSelected(e.target.value)}
                                    className="rounded-md border border-border bg-background px-2 py-1 text-sm text-white"
                                >
                                    {analytes.map((a) => (
                                        <option key={a} value={a}>
                                            {a}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </CardHeader>
                        <CardContent>
                            <StudyResultChart points={chartPoints} analyte={analyte ?? ''} />
                        </CardContent>
                    </Card>
                )}

                {study.results && study.results.length > 0 && (
                    <Card className="bg-card border-border">
                        <CardHeader>
                            <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">
                                Resultados
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="list-disc pl-5 space-y-1 text-white/80">
                                {study.results.map((r: any) => (
                                    <li key={r.id}>
                                        {r.analyte ?? 'Resultado'}: {r.value} {r.unit ?? ''}{' '}
                                        {r.flag ? `(${FLAG_LABELS[r.flag] ?? r.flag})` : ''}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {(study.media ?? []).length > 0 && (
                    <Card className="bg-card border-border">
                        <CardHeader>
                            <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">
                                Adjuntos
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="flex flex-col gap-2">
                                {study.media.map((m: any) => (
                                    <li key={m.id} className="flex items-center gap-3 rounded-lg border border-border bg-background px-3 py-2">
                                        <Paperclip className="h-4 w-4 shrink-0 text-primary" />
                                        <span className="min-w-0 flex-1 truncate text-sm text-white/90">{m.file_name}</span>
                                        <a
                                            href={health.studies.attachments.download([study.id, m.id]).url}
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
                                                    router.delete(health.studies.attachments.destroy([study.id, m.id]).url);
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