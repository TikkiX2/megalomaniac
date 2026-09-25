import { Head, router, usePage } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import Heading from '@/components/heading';
import EmptyState from '@/components/integrations/EmptyState';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import MainLayout from '@/layouts/main-layout';
import type { SharedData } from '@/types';
import type { ActivityLogRow } from '@/types/integrations';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

interface ActivityPageProps {
    logs: Paginated<ActivityLogRow>;
    connections: { id: number; name: string }[];
    filters: {
        connection_id?: number | null;
        status?: string | null;
        action_key?: string | null;
    };
}

const statusStyles: Record<string, string> = {
    success: 'text-primary',
    failed: 'text-destructive',
    denied: 'text-destructive',
    pending: 'text-amber-500',
    running: 'text-muted-foreground',
};

export default function Activity() {
    const { logs, connections, filters } = usePage<SharedData & ActivityPageProps>().props;
    const [actionKey, setActionKey] = useState(filters.action_key ?? '');
    const [expanded, setExpanded] = useState<number | null>(null);

    const applyFilters = (next: Partial<ActivityPageProps['filters']>) => {
        router.get(
            '/integrations/activity',
            {
                ...filters,
                ...next,
                action_key: next.action_key ?? (actionKey || undefined),
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <MainLayout>
            <Head title="Actividad de integraciones" />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                <Heading
                    title="Actividad"
                    description="Auditoría de cada acción ejecutada contra tus conexiones."
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Select
                        value={filters.connection_id ? String(filters.connection_id) : 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                connection_id: value === 'all' ? undefined : Number(value),
                            })
                        }
                    >
                        <SelectTrigger className="w-52 bg-card border-border">
                            <SelectValue placeholder="Conexión" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todas las conexiones</SelectItem>
                            {connections.map((connection) => (
                                <SelectItem key={connection.id} value={String(connection.id)}>
                                    {connection.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ status: value === 'all' ? undefined : value })
                        }
                    >
                        <SelectTrigger className="w-40 bg-card border-border">
                            <SelectValue placeholder="Estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos</SelectItem>
                            <SelectItem value="success">Success</SelectItem>
                            <SelectItem value="failed">Failed</SelectItem>
                            <SelectItem value="denied">Denied</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                        </SelectContent>
                    </Select>

                    <Input
                        value={actionKey}
                        onChange={(e) => setActionKey(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                applyFilters({ action_key: actionKey || undefined });
                            }
                        }}
                        placeholder="Filtrar por acción..."
                        className="w-56 bg-card border-border"
                    />
                    <Button
                        variant="outline"
                        onClick={() => applyFilters({ action_key: actionKey || undefined })}
                    >
                        Filtrar
                    </Button>
                </div>

                {logs.data.length === 0 ? (
                    <EmptyState
                        title="Sin actividad"
                        description="Acá se registra cada acción que el agente o vos ejecuten sobre una conexión."
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border border-border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow className="border-border hover:bg-transparent">
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Conexión</TableHead>
                                    <TableHead>Acción</TableHead>
                                    <TableHead>Acceso</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead className="text-right">Duración</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {logs.data.map((log) => (
                                    <Fragment key={log.id}>
                                        <TableRow
                                            className="cursor-pointer border-border"
                                            onClick={() =>
                                                setExpanded(expanded === log.id ? null : log.id)
                                            }
                                        >
                                            <TableCell className="text-xs text-muted-foreground">
                                                {log.created_at
                                                    ? new Date(log.created_at).toLocaleString()
                                                    : '—'}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                {log.connection_name}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {log.action_key}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {log.access}
                                            </TableCell>
                                            <TableCell
                                                className={`text-xs font-bold uppercase ${statusStyles[log.status] ?? ''}`}
                                            >
                                                {log.status}
                                            </TableCell>
                                            <TableCell className="text-right text-xs text-muted-foreground">
                                                {log.duration_ms !== null
                                                    ? `${log.duration_ms} ms`
                                                    : '—'}
                                            </TableCell>
                                        </TableRow>
                                        {expanded === log.id && (
                                            <TableRow className="border-border">
                                                <TableCell colSpan={6} className="bg-background/40">
                                                    <pre className="max-h-48 overflow-auto text-[11px] text-muted-foreground">
                                                        {JSON.stringify(
                                                            {
                                                                source: log.source,
                                                                params: log.params,
                                                                result: log.result_summary,
                                                                error: log.error,
                                                            },
                                                            null,
                                                            2,
                                                        )}
                                                    </pre>
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </Fragment>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {logs.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {logs.links.map((link) =>
                            link.url ? (
                                <Button
                                    key={link.label}
                                    size="sm"
                                    variant={link.active ? 'default' : 'ghost'}
                                    asChild
                                >
                                    <a
                                        href={link.url}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                </Button>
                            ) : (
                                <Button key={link.label} size="sm" variant="ghost" disabled>
                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                </Button>
                            ),
                        )}
                    </div>
                )}
            </div>
        </MainLayout>
    );
}
