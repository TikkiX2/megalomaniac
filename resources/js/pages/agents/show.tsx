import { Head, Link, usePage } from '@inertiajs/react';
import EmptyState from '@/components/integrations/EmptyState';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import MainLayout from '@/layouts/main-layout';
import type { SharedData } from '@/types';

interface AgentRunRow {
    id: number;
    status: string;
    triggered_by: string;
    report: string | null;
    error: string | null;
    suggestions_created: number;
    approvals_created: number;
    usage: Record<string, number> | null;
    created_at: string | null;
}

interface AgentDetail {
    id: number;
    key: string;
    name: string;
    description: string | null;
    instructions: string;
    enabled: boolean;
    schedule_type: string;
    schedule_value: string;
    tools_policy: { internal?: string[]; integrations?: string[] };
    next_run_at: string | null;
    failure_count: number;
}

interface ShowPageProps {
    agent: AgentDetail;
    runs: {
        data: AgentRunRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}

const statusStyles: Record<string, string> = {
    success: 'text-primary',
    failed: 'text-destructive',
    skipped: 'text-muted-foreground',
    running: 'text-amber-500',
};

export default function AgentShow() {
    const { agent, runs } = usePage<SharedData & ShowPageProps>().props;

    return (
        <MainLayout>
            <Head title={agent.name} />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title={agent.name} description={agent.description ?? undefined} />
                    <Button variant="outline" asChild>
                        <Link href="/agents">Volver</Link>
                    </Button>
                </div>

                <Card className="border-border bg-card">
                    <CardHeader>
                        <CardTitle className="text-sm font-bold uppercase tracking-widest text-muted-foreground">
                            Configuración
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p className="text-muted-foreground">
                            <span className="font-bold text-foreground">Schedule:</span>{' '}
                            {agent.schedule_type === 'cron'
                                ? `cron ${agent.schedule_value}`
                                : `cada ${agent.schedule_value}`}{' '}
                            · {agent.enabled ? 'activo' : 'pausado'} · fallos seguidos: {agent.failure_count}
                        </p>
                        <p className="text-muted-foreground">
                            <span className="font-bold text-foreground">Instrucciones:</span> {agent.instructions}
                        </p>
                        <p className="text-muted-foreground">
                            <span className="font-bold text-foreground">Herramientas:</span>{' '}
                            {[...(agent.tools_policy.internal ?? []), ...(agent.tools_policy.integrations ?? [])].join(', ') ||
                                'ninguna'}
                        </p>
                    </CardContent>
                </Card>

                <div className="space-y-3">
                    <h2 className="text-xs font-black uppercase tracking-widest text-muted-foreground">
                        Historial de runs
                    </h2>

                    {runs.data.length === 0 ? (
                        <EmptyState
                            title="Sin ejecuciones"
                            description="Cuando el agente corra, vas a ver acá el informe, el uso de tokens y las sugerencias generadas."
                        />
                    ) : (
                        runs.data.map((run) => (
                            <Card key={run.id} className="border-border bg-card">
                                <CardContent className="space-y-2 pt-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                                        <span
                                            className={`font-bold uppercase tracking-widest ${statusStyles[run.status] ?? ''}`}
                                        >
                                            {run.status}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {run.triggered_by} ·{' '}
                                            {run.created_at ? new Date(run.created_at).toLocaleString() : ''}
                                        </span>
                                    </div>
                                    {run.error && <p className="text-xs text-destructive">{run.error}</p>}
                                    {run.report && (
                                        <pre className="max-h-64 overflow-auto whitespace-pre-wrap rounded-lg bg-background/50 p-3 text-xs text-foreground">
                                            {run.report}
                                        </pre>
                                    )}
                                    <div className="flex flex-wrap gap-3 text-[11px] text-muted-foreground">
                                        <span>Sugerencias: {run.suggestions_created}</span>
                                        <span>Aprobaciones: {run.approvals_created}</span>
                                        {run.usage && (
                                            <span>
                                                Tokens: {run.usage.promptTokens ?? 0}/{run.usage.completionTokens ?? 0}
                                            </span>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>
            </div>
        </MainLayout>
    );
}
