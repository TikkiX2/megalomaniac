import { router } from '@inertiajs/react';
import StatusBadge from '@/components/integrations/StatusBadge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ConnectionCatalogItem, ConnectionRow } from '@/types/integrations';

function relative(value: string | null): string {
    if (!value) {
        return 'nunca';
    }

    const date = new Date(value);
    const diffMinutes = Math.round((Date.now() - date.getTime()) / 60000);

    if (diffMinutes < 1) {
        return 'hace instantes';
    }

    if (diffMinutes < 60) {
        return `hace ${diffMinutes} min`;
    }

    const diffHours = Math.round(diffMinutes / 60);

    if (diffHours < 24) {
        return `hace ${diffHours} h`;
    }

    return `hace ${Math.round(diffHours / 24)} días`;
}

export default function ConnectionCard({
    connection,
    catalogItem,
    onEdit,
}: {
    connection: ConnectionRow;
    catalogItem?: ConnectionCatalogItem;
    onEdit: (connection: ConnectionRow) => void;
}) {
    const toggle = () => {
        router.patch(
            `/settings/connections/${connection.id}`,
            { enabled: !connection.enabled },
            { preserveScroll: true },
        );
    };

    const test = () => {
        router.post(
            '/settings/connections/test',
            { connection_id: connection.id },
            { preserveScroll: true },
        );
    };

    const remove = () => {
        if (!window.confirm(`¿Eliminar la conexión "${connection.name}"?`)) {
            return;
        }

        router.delete(`/settings/connections/${connection.id}`, { preserveScroll: true });
    };

    return (
        <Card className="border-border bg-card">
            <CardHeader className="gap-1">
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <CardTitle className="text-base font-bold">
                            {connection.name}
                        </CardTitle>
                        <CardDescription className="text-xs">
                            {catalogItem?.label ?? connection.kind} ·{' '}
                            {connection.transport}
                        </CardDescription>
                    </div>
                    <StatusBadge
                        status={connection.status}
                        message={connection.status_message}
                    />
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                <dl className="grid grid-cols-2 gap-1 text-[11px] text-muted-foreground">
                    <dt>Última prueba</dt>
                    <dd>{relative(connection.last_tested_at)}</dd>
                    <dt>Último uso</dt>
                    <dd>{relative(connection.last_used_at)}</dd>
                </dl>

                {!connection.enabled && (
                    <p className="text-xs text-amber-500">
                        Deshabilitada: el agente no puede usarla.
                    </p>
                )}

                <div className="flex flex-wrap gap-2">
                    {connection.auth_type === 'oauth2' && (
                        <Button
                            size="sm"
                            variant="outline"
                            asChild
                            className="border-primary/40 text-primary"
                        >
                            <a href={`/integrations/oauth/${connection.id}/redirect`}>
                                Conectar
                            </a>
                        </Button>
                    )}
                    <Button size="sm" variant="outline" onClick={test}>
                        Probar
                    </Button>
                    <Button size="sm" variant="ghost" onClick={toggle}>
                        {connection.enabled ? 'Deshabilitar' : 'Habilitar'}
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => onEdit(connection)}>
                        Editar
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        className="text-destructive"
                        onClick={remove}
                    >
                        Eliminar
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
