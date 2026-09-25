import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AgentWizard, { type AgentRow } from '@/components/agents/AgentWizard';
import EmptyState from '@/components/integrations/EmptyState';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import MainLayout from '@/layouts/main-layout';
import type { SharedData } from '@/types';
import type { FlashProps } from '@/types/integrations';

interface AgentsPageProps {
    agents: AgentRow[];
    scheduleIntervals: string[];
    internalTools: { key: string; label: string }[];
    integrations: { kind: string; name: string }[];
    flash: FlashProps;
}

const statusStyles: Record<string, string> = {
    success: 'text-primary',
    failed: 'text-destructive',
    skipped: 'text-muted-foreground',
    running: 'text-amber-500',
};

function relative(value: string | null): string {
    if (!value) {
        return '—';
    }

    const diff = Math.round((new Date(value).getTime() - Date.now()) / 60000);
    const future = diff >= 0;
    const minutes = Math.abs(diff);

    if (minutes < 1) {
        return 'ahora';
    }

    const label = minutes < 60 ? `${minutes} min` : `${Math.round(minutes / 60)} h`;

    return future ? `en ${label}` : `hace ${label}`;
}

export default function AgentsIndex() {
    const { agents, scheduleIntervals, internalTools, integrations, flash } = usePage<
        SharedData & AgentsPageProps
    >().props;

    const [wizardOpen, setWizardOpen] = useState(false);
    const [editing, setEditing] = useState<AgentRow | null>(null);

    const openCreate = () => {
        setEditing(null);
        setWizardOpen(true);
    };

    const openEdit = (agent: AgentRow) => {
        setEditing(agent);
        setWizardOpen(true);
    };

    const toggle = (agent: AgentRow) => {
        router.post(`/agents/${agent.id}/toggle`, {}, { preserveScroll: true });
    };

    const runNow = (agent: AgentRow) => {
        router.post(`/agents/${agent.id}/run`, {}, { preserveScroll: true });
    };

    const remove = (agent: AgentRow) => {
        if (!window.confirm(`¿Eliminar el agente "${agent.name}"?`)) {
            return;
        }

        router.delete(`/agents/${agent.id}`, { preserveScroll: true });
    };

    return (
        <MainLayout>
            <Head title="Agentes" />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading
                        title="Agentes"
                        description="Agentes durables que corren solos y te dejan informes y sugerencias."
                    />
                    <Button onClick={openCreate} className="bg-primary font-bold">
                        Nuevo agente
                    </Button>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-primary/30 bg-primary/10 p-3 text-sm text-primary">
                        {flash.success}
                    </div>
                )}

                {agents.length === 0 ? (
                    <EmptyState
                        title="Todavía no hay agentes"
                        description="Creá uno para que revise tus datos cada cierto tiempo. También podés pedírselo al Chat IA (“creá un agente que...”)."
                        action={
                            <Button onClick={openCreate} className="bg-primary font-bold">
                                Nuevo agente
                            </Button>
                        }
                    />
                ) : (
                    <div className="grid gap-3 md:grid-cols-2">
                        {agents.map((agent) => (
                            <Card key={agent.id} className="border-border bg-card">
                                <CardHeader className="gap-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <CardTitle className="text-base font-bold">{agent.name}</CardTitle>
                                            <CardDescription className="text-xs">
                                                {agent.schedule_type === 'cron'
                                                    ? `cron ${agent.schedule_value}`
                                                    : `cada ${agent.schedule_value}`}
                                                {' · '}
                                                {agent.enabled ? 'activo' : 'pausado'}
                                            </CardDescription>
                                        </div>
                                        {agent.last_status && (
                                            <span
                                                className={`text-[11px] font-bold uppercase tracking-widest ${statusStyles[agent.last_status] ?? ''}`}
                                            >
                                                {agent.last_status}
                                            </span>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {agent.description && (
                                        <p className="text-xs text-muted-foreground">{agent.description}</p>
                                    )}
                                    <dl className="grid grid-cols-2 gap-1 text-[11px] text-muted-foreground">
                                        <dt>Próximo run</dt>
                                        <dd>{agent.enabled ? relative(agent.next_run_at) : '—'}</dd>
                                        <dt>Último run</dt>
                                        <dd>{relative(agent.last_run_at ?? null)}</dd>
                                        <dt>Runs</dt>
                                        <dd>{agent.runs_count ?? 0}</dd>
                                        <dt>Fallos seguidos</dt>
                                        <dd>{agent.failure_count ?? 0}</dd>
                                    </dl>
                                    <div className="flex flex-wrap gap-2">
                                        <Button size="sm" variant="outline" asChild>
                                            <Link href={`/agents/${agent.id}`}>Ver runs</Link>
                                        </Button>
                                        <Button size="sm" variant="outline" onClick={() => runNow(agent)}>
                                            Ejecutar
                                        </Button>
                                        <Button size="sm" variant="ghost" onClick={() => toggle(agent)}>
                                            {agent.enabled ? 'Pausar' : 'Activar'}
                                        </Button>
                                        <Button size="sm" variant="ghost" onClick={() => openEdit(agent)}>
                                            Editar
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive"
                                            onClick={() => remove(agent)}
                                        >
                                            Eliminar
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <AgentWizard
                agent={editing}
                open={wizardOpen}
                onOpenChange={setWizardOpen}
                scheduleIntervals={scheduleIntervals}
                internalTools={internalTools}
                integrations={integrations}
            />
        </MainLayout>
    );
}
