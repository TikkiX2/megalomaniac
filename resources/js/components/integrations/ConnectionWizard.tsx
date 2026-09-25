import { useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import { Button } from '@/components/ui/button';
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
import { cn } from '@/lib/utils';
import type {
    ConnectionCatalogItem,
    ConnectionFormState,
    ConnectionRow,
} from '@/types/integrations';

const transportLabels: Record<string, string> = {
    direct: 'Directa (URL)',
    local_socket: 'Socket local (Docker)',
    ssh_tunnel: 'Túnel SSH',
    ssh_exec: 'SSH (comandos)',
};

function blankForm(kind = ''): ConnectionFormState {
    return {
        kind,
        name: '',
        credentials: {},
        options: {},
        base_url: '',
        transport: 'direct',
        transport_config: {},
        enabled: true,
    };
}

export default function ConnectionWizard({
    catalog,
    connection,
    open,
    onOpenChange,
}: {
    catalog: ConnectionCatalogItem[];
    connection?: ConnectionRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm<ConnectionFormState>(blankForm());

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData(
            connection
                ? {
                      kind: connection.kind,
                      name: connection.name,
                      credentials: {},
                      options: {},
                      base_url: connection.base_url ?? '',
                      transport: connection.transport,
                      transport_config: {},
                      enabled: connection.enabled,
                  }
                : blankForm(),
        );
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, connection?.id]);

    const selected = useMemo(
        () => catalog.find((item) => item.kind === form.data.kind) ?? null,
        [catalog, form.data.kind],
    );

    const submit = () => {
        const payload: Record<string, unknown> = {
            ...form.data,
            auth_type: selected?.auth_type ?? 'api_token',
            base_url: form.data.base_url || null,
            transport_config: pruneEmpty(form.data.transport_config),
            credentials: pruneEmpty(form.data.credentials),
        };

        if (Object.keys(form.data.options).length > 0) {
            payload.options = pruneEmpty(form.data.options);
        } else {
            delete payload.options;
        }

        if (connection) {
            form.transform(() => payload);
            form.patch(`/settings/connections/${connection.id}`, {
                onSuccess: () => onOpenChange(false),
            });

            return;
        }

        form.transform(() => payload);
        form.post('/settings/connections', {
            onSuccess: () => onOpenChange(false),
        });
    };

    const testDraft = () => {
        form.transform((data) => ({
            connection_id: connection?.id,
            kind: data.kind,
            auth_type: selected?.auth_type ?? 'api_token',
            credentials: pruneEmpty(data.credentials),
            base_url: data.base_url || null,
            transport: data.transport,
            transport_config: pruneEmpty(data.transport_config),
        }));
        form.post('/settings/connections/test', {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto border-border bg-card sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {connection ? 'Editar conexión' : 'Nueva conexión'}
                    </DialogTitle>
                    <DialogDescription>
                        Elegí un servicio, cargá sus credenciales y configurá cómo llegar a él.
                    </DialogDescription>
                </DialogHeader>

                <form
                    className="space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    {!connection && (
                        <div className="space-y-2">
                            <Label>Servicio</Label>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {catalog.map((item) => (
                                    <button
                                        key={item.kind}
                                        type="button"
                                        onClick={() => {
                                            form.setData('kind', item.kind);
                                            form.setData(
                                                'transport',
                                                item.transports[0] ?? 'direct',
                                            );
                                        }}
                                        className={cn(
                                            'rounded-lg border p-3 text-left transition-colors',
                                            form.data.kind === item.kind
                                                ? 'border-primary/40 bg-primary/10'
                                                : 'border-border bg-background hover:bg-accent',
                                        )}
                                    >
                                        <p className="text-sm font-bold text-foreground">
                                            {item.label}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {item.description}
                                        </p>
                                        <p className="mt-1 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                            {item.group}
                                        </p>
                                    </button>
                                ))}
                            </div>
                            {form.errors.kind && (
                                <p className="text-xs text-destructive">{form.errors.kind}</p>
                            )}
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="connection-name">Nombre</Label>
                        <Input
                            id="connection-name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="GitHub personal"
                            className="bg-background border-border"
                        />
                        {form.errors.name && (
                            <p className="text-xs text-destructive">{form.errors.name}</p>
                        )}
                    </div>

                    {selected && selected.auth_fields.length > 0 && (
                        <div className="space-y-3">
                            <Label>Credenciales</Label>
                            {selected.auth_fields.map((field) =>
                                field.type === 'oauth' ? (
                                    <div
                                        key={field.name}
                                        className="rounded-lg border border-border bg-background p-3 text-xs text-muted-foreground"
                                    >
                                        {field.help ??
                                            'Guardá la conexión y usá el botón Conectar para autorizar con OAuth.'}
                                    </div>
                                ) : (
                                    <div key={field.name} className="space-y-1">
                                        <Label
                                            htmlFor={`cred-${field.name}`}
                                            className="text-xs text-muted-foreground"
                                        >
                                            {field.label}
                                        </Label>
                                        <Input
                                            id={`cred-${field.name}`}
                                            type={
                                                field.type === 'password'
                                                    ? 'password'
                                                    : 'text'
                                            }
                                            value={
                                                form.data.credentials[field.name] ?? ''
                                            }
                                            onChange={(e) =>
                                                form.setData('credentials', {
                                                    ...form.data.credentials,
                                                    [field.name]: e.target.value,
                                                })
                                            }
                                            className="bg-background border-border"
                                        />
                                        {field.help && (
                                            <p className="text-[11px] text-muted-foreground">
                                                {field.help}
                                            </p>
                                        )}
                                    </div>
                                ),
                            )}
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="connection-url">
                            Base URL (opcional)
                        </Label>
                        <Input
                            id="connection-url"
                            value={form.data.base_url}
                            onChange={(e) => form.setData('base_url', e.target.value)}
                            placeholder="https://api.github.com"
                            className="bg-background border-border"
                        />
                        {form.errors.base_url && (
                            <p className="text-xs text-destructive">{form.errors.base_url}</p>
                        )}
                    </div>

                    {selected && selected.transports.length > 1 && (
                        <div className="space-y-2">
                            <Label>Transporte</Label>
                            <Select
                                value={form.data.transport}
                                onValueChange={(value) =>
                                    form.setData('transport', value)
                                }
                            >
                                <SelectTrigger className="bg-background border-border">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {selected.transports.map((transport) => (
                                        <SelectItem key={transport} value={transport}>
                                            {transportLabels[transport] ?? transport}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <TransportFields
                        transport={form.data.transport}
                        values={form.data.transport_config}
                        onChange={(key, value) =>
                            form.setData('transport_config', {
                                ...form.data.transport_config,
                                [key]: value,
                            })
                        }
                    />

                    {selected && (selected.option_fields?.length ?? 0) > 0 && (
                        <div className="space-y-3 rounded-lg border border-border bg-background/50 p-3">
                            <Label className="text-xs uppercase tracking-widest text-muted-foreground">
                                Opciones del servicio
                            </Label>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {selected.option_fields?.map((field) => (
                                    <div key={field.name} className="space-y-1">
                                        <Label
                                            htmlFor={`option-${field.name}`}
                                            className="text-xs text-muted-foreground"
                                        >
                                            {field.label}
                                        </Label>
                                        <Input
                                            id={`option-${field.name}`}
                                            value={form.data.options[field.name] ?? ''}
                                            placeholder={field.placeholder}
                                            onChange={(e) =>
                                                form.setData('options', {
                                                    ...form.data.options,
                                                    [field.name]: e.target.value,
                                                })
                                            }
                                            className="bg-background border-border"
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {form.errors.credentials && (
                        <p className="text-xs text-destructive">{form.errors.credentials}</p>
                    )}
                    {form.errors.transport && (
                        <p className="text-xs text-destructive">{form.errors.transport}</p>
                    )}

                    {Object.entries(form.errors)
                        .filter(
                            ([key]) =>
                                ![
                                    'kind',
                                    'name',
                                    'base_url',
                                    'credentials',
                                    'transport',
                                ].includes(key),
                        )
                        .map(([key, message]) => (
                            <p key={key} className="text-xs text-destructive">
                                {message}
                            </p>
                        ))}

                    <div className="flex flex-wrap justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={testDraft}
                            disabled={form.processing || !form.data.kind}
                        >
                            Probar
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || !form.data.kind}
                            className="bg-primary font-bold"
                        >
                            {connection ? 'Guardar cambios' : 'Crear conexión'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function TransportFields({
    transport,
    values,
    onChange,
}: {
    transport: string;
    values: Record<string, string>;
    onChange: (key: string, value: string) => void;
}) {
    const fields: { key: string; label: string; placeholder?: string; type?: string }[] =
        transport === 'ssh_tunnel' || transport === 'ssh_exec'
            ? [
                  { key: 'ssh_host', label: 'Host SSH', placeholder: '10.0.0.5' },
                  { key: 'ssh_port', label: 'Puerto SSH', placeholder: '22' },
                  { key: 'ssh_user', label: 'Usuario SSH', placeholder: 'root' },
                  {
                      key: 'key_path',
                      label: 'Ruta de la llave privada',
                      placeholder: '/root/.ssh/id_ed25519',
                  },
                  ...(transport === 'ssh_tunnel'
                      ? [
                            {
                                key: 'remote_host',
                                label: 'Host remoto',
                                placeholder: '127.0.0.1',
                            },
                            { key: 'remote_port', label: 'Puerto remoto', placeholder: '2375' },
                        ]
                      : []),
              ]
            : transport === 'local_socket'
              ? [
                    {
                        key: 'socket_path',
                        label: 'Socket',
                        placeholder: '/var/run/docker.sock',
                    },
                ]
              : [];

    if (fields.length === 0) {
        return null;
    }

    return (
        <div className="space-y-3 rounded-lg border border-border bg-background/50 p-3">
            <Label className="text-xs uppercase tracking-widest text-muted-foreground">
                Configuración del transporte
            </Label>
            <div className="grid gap-3 sm:grid-cols-2">
                {fields.map((field) => (
                    <div key={field.key} className="space-y-1">
                        <Label
                            htmlFor={`transport-${field.key}`}
                            className="text-xs text-muted-foreground"
                        >
                            {field.label}
                        </Label>
                        <Input
                            id={`transport-${field.key}`}
                            value={values[field.key] ?? ''}
                            placeholder={field.placeholder}
                            onChange={(e) => onChange(field.key, e.target.value)}
                            className="bg-background border-border"
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}

function pruneEmpty(values: Record<string, string>): Record<string, string> {
    return Object.fromEntries(
        Object.entries(values).filter(([, value]) => value !== '' && value !== undefined),
    );
}
