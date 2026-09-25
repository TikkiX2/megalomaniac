import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

export interface AgentRow {
    id: number;
    key: string;
    name: string;
    description: string | null;
    instructions?: string;
    enabled: boolean;
    schedule_type: 'interval' | 'cron';
    schedule_value: string;
    tools_policy: { internal?: string[]; integrations?: string[] };
    next_run_at: string | null;
    last_run_at?: string | null;
    failure_count?: number;
    runs_count?: number;
    last_status?: string | null;
}

interface ToolOption {
    key: string;
    label: string;
}

interface IntegrationOption {
    kind: string;
    name: string;
}

interface FormState {
    name: string;
    description: string;
    instructions: string;
    schedule_type: 'interval' | 'cron';
    schedule_value: string;
    internal: string[];
    integrations: string[];
    max_runs_per_day: number;
}

function blankForm(): FormState {
    return {
        name: '',
        description: '',
        instructions: '',
        schedule_type: 'interval',
        schedule_value: '1h',
        internal: [],
        integrations: [],
        max_runs_per_day: 24,
    };
}

export default function AgentWizard({
    agent,
    open,
    onOpenChange,
    scheduleIntervals,
    internalTools,
    integrations,
}: {
    agent?: AgentRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    scheduleIntervals: string[];
    internalTools: ToolOption[];
    integrations: IntegrationOption[];
}) {
    const form = useForm<FormState>(blankForm());

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData(
            agent
                ? {
                      name: agent.name,
                      description: agent.description ?? '',
                      instructions: agent.instructions ?? '',
                      schedule_type: agent.schedule_type,
                      schedule_value: agent.schedule_value,
                      internal: agent.tools_policy?.internal ?? [],
                      integrations: agent.tools_policy?.integrations ?? [],
                      max_runs_per_day: 24,
                  }
                : blankForm(),
        );
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, agent?.id]);

    const submit = () => {
        const payload = {
            name: form.data.name,
            description: form.data.description || null,
            instructions: form.data.instructions,
            schedule_type: form.data.schedule_type,
            schedule_value: form.data.schedule_value,
            max_runs_per_day: form.data.max_runs_per_day,
            tools_policy: {
                internal: form.data.internal,
                integrations: form.data.integrations,
            },
        };

        form.transform(() => payload);

        if (agent) {
            form.patch(`/agents/${agent.id}`, { onSuccess: () => onOpenChange(false) });
        } else {
            form.post('/agents', { onSuccess: () => onOpenChange(false) });
        }
    };

    const toggle = (list: string[], value: string): string[] =>
        list.includes(value) ? list.filter((item) => item !== value) : [...list, value];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto border-border bg-card sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{agent ? 'Editar agente' : 'Nuevo agente'}</DialogTitle>
                    <DialogDescription>
                        Un agente durable que corre solo según el schedule y te deja un informe.
                    </DialogDescription>
                </DialogHeader>

                <form
                    className="space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="space-y-1">
                            <Label htmlFor="agent-name">Nombre</Label>
                            <Input
                                id="agent-name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="Monitor de precios"
                                className="bg-background border-border"
                            />
                            {form.errors.name && <p className="text-xs text-destructive">{form.errors.name}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="agent-description">Descripción</Label>
                            <Input
                                id="agent-description"
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                placeholder="Qué hace, en una línea"
                                className="bg-background border-border"
                            />
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="agent-instructions">Instrucciones</Label>
                        <Textarea
                            id="agent-instructions"
                            value={form.data.instructions}
                            onChange={(e) => form.setData('instructions', e.target.value)}
                            placeholder="Ej: Revisá mis compras y avisame si algún precio subió más del 10%."
                            rows={4}
                            className="bg-background border-border"
                        />
                        {form.errors.instructions && <p className="text-xs text-destructive">{form.errors.instructions}</p>}
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="space-y-1">
                            <Label>Tipo de schedule</Label>
                            <Select
                                value={form.data.schedule_type}
                                onValueChange={(value) => {
                                    form.setData('schedule_type', value as 'interval' | 'cron');
                                    form.setData('schedule_value', value === 'interval' ? '1h' : '0 8 * * *');
                                }}
                            >
                                <SelectTrigger className="bg-background border-border">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="interval">Intervalo</SelectItem>
                                    <SelectItem value="cron">Cron</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label>Frecuencia</Label>
                            {form.data.schedule_type === 'interval' ? (
                                <Select
                                    value={form.data.schedule_value}
                                    onValueChange={(value) => form.setData('schedule_value', value)}
                                >
                                    <SelectTrigger className="bg-background border-border">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {scheduleIntervals.map((interval) => (
                                            <SelectItem key={interval} value={interval}>
                                                {interval}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <Input
                                    value={form.data.schedule_value}
                                    onChange={(e) => form.setData('schedule_value', e.target.value)}
                                    placeholder="0 8 * * *"
                                    className="bg-background border-border font-mono"
                                />
                            )}
                            {form.errors.schedule_value && (
                                <p className="text-xs text-destructive">{form.errors.schedule_value}</p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label>Herramientas internas</Label>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {internalTools.map((tool) => (
                                <label key={tool.key} className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Checkbox
                                        checked={form.data.internal.includes(tool.key)}
                                        onCheckedChange={() =>
                                            form.setData('internal', toggle(form.data.internal, tool.key))
                                        }
                                    />
                                    {tool.label}
                                </label>
                            ))}
                        </div>
                    </div>

                    {integrations.length > 0 && (
                        <div className="space-y-2">
                            <Label>Integraciones permitidas</Label>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {integrations.map((integration) => (
                                    <label
                                        key={integration.kind}
                                        className="flex items-center gap-2 text-sm text-muted-foreground"
                                    >
                                        <Checkbox
                                            checked={form.data.integrations.includes(integration.kind)}
                                            onCheckedChange={() =>
                                                form.setData(
                                                    'integrations',
                                                    toggle(form.data.integrations, integration.kind),
                                                )
                                            }
                                        />
                                        {integration.name}
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing} className="bg-primary font-bold">
                            {agent ? 'Guardar cambios' : 'Crear agente'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
