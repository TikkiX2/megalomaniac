import { Head, Link } from '@inertiajs/react';
import { Activity, HeartPulse, Stethoscope, Tablets, Thermometer } from 'lucide-react';
import React from 'react';
import { ModuleAiButton } from '@/components/ai/module-ai-button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import HealthLayout from '@/layouts/health-layout';
import health from '@/routes/health';

interface HealthConditionItem {
    name: string;
}

interface HealthMedicationItem {
    name: string;
    dose_amount: number | string | null;
    dose_unit: string | null;
}

interface HealthMeasurementItem {
    type: string;
    value: number | string;
    secondary_value: number | string | null;
    unit: string;
}

interface HealthSymptomItem {
    symptom: string;
    severity: string;
}

interface HealthSummary {
    active_conditions: HealthConditionItem[];
    active_medications: HealthMedicationItem[];
    last_measurements: HealthMeasurementItem[];
    recent_symptoms: HealthSymptomItem[];
}

const SEVERITY_LABELS: Record<string, string> = {
    mild: 'Leve', moderate: 'Moderada', severe: 'Severa',
};

export default function HealthDashboard({ summary }: { summary: HealthSummary }) {
    const cards = [
        {
            title: 'Condiciones activas',
            icon: Stethoscope,
            href: health.conditions.index().url,
            items: summary.active_conditions,
            render: (item: HealthConditionItem) => item.name,
        },
        {
            title: 'Medicación activa',
            icon: Tablets,
            href: health.medications.index().url,
            items: summary.active_medications,
            render: (item: HealthMedicationItem) =>
                `${item.name}${item.dose_amount ? ` ${Number(item.dose_amount)} ${item.dose_unit ?? ''}` : ''}`,
        },
        {
            title: 'Últimas mediciones',
            icon: Activity,
            href: health.measurements.index().url,
            items: summary.last_measurements,
            render: (item: HealthMeasurementItem) =>
                `${item.type.replace(/_/g, ' ')}: ${Number(item.value)}${item.secondary_value ? `/${Number(item.secondary_value)}` : ''} ${item.unit}`,
        },
        {
            title: 'Síntomas recientes',
            icon: Thermometer,
            href: health.symptoms.index().url,
            items: summary.recent_symptoms,
            render: (item: HealthSymptomItem) =>
                `${item.symptom} · ${SEVERITY_LABELS[item.severity] ?? item.severity}`,
        },
    ];

    return (
        <HealthLayout>
            <Head title="Salud" />
            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Salud</h1>
                        <p className="text-muted-foreground">Tu expediente: condiciones, medicación, mediciones y síntomas.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href={health.chats.index().url} className="text-sm font-bold text-primary hover:underline">Chats de salud</Link>
                        <ModuleAiButton module="health" />
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => (
                        <Card key={card.title} className="bg-card border-border">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">{card.title}</CardTitle>
                                <card.icon className="h-4 w-4 text-primary" />
                            </CardHeader>
                            <CardContent className="flex flex-col gap-2">
                                {card.items.length === 0 ? (
                                    <p className="text-sm text-muted-foreground italic">Sin datos.</p>
                                ) : (card.items as unknown[]).slice(0, 4).map((item, index) => (
                                    <p key={index} className="truncate text-sm text-white/90">
                                        {(card.render as (item: unknown) => string)(item)}
                                    </p>
                                ))}
                                <Link href={card.href} className="text-xs font-bold text-primary hover:underline">Ver todo</Link>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="rounded-xl border border-dashed border-border bg-card p-6">
                    <div className="flex items-center gap-3">
                        <HeartPulse className="h-5 w-5 text-primary" />
                        <div>
                            <p className="text-sm font-bold text-white">Chats exclusivos de salud</p>
                            <p className="text-xs text-muted-foreground">
                                Abrí una conversación categorizada y vinculada a una condición o persona.
                            </p>
                        </div>
                        <Badge variant="outline" className="ml-auto border-primary/30 text-primary text-[10px] font-black uppercase">Salud</Badge>
                    </div>
                </div>
            </div>
        </HealthLayout>
    );
}
