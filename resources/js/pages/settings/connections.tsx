import { Head, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import ConnectionCard from '@/components/integrations/ConnectionCard';
import ConnectionWizard from '@/components/integrations/ConnectionWizard';
import EmptyState from '@/components/integrations/EmptyState';
import { Button } from '@/components/ui/button';
import MainLayout from '@/layouts/main-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { SharedData } from '@/types';
import type {
    ConnectionCatalogItem,
    ConnectionRow,
    FlashProps,
} from '@/types/integrations';

interface ConnectionsPageProps {
    connections: ConnectionRow[];
    catalog: ConnectionCatalogItem[];
    flash: FlashProps;
}

export default function ConnectionsSettings() {
    const { connections, catalog, flash } = usePage<
        SharedData & ConnectionsPageProps
    >().props;

    const [wizardOpen, setWizardOpen] = useState(false);
    const [editing, setEditing] = useState<ConnectionRow | null>(null);

    const catalogByKind = useMemo(
        () => new Map(catalog.map((item) => [item.kind, item])),
        [catalog],
    );

    const groups = useMemo(() => {
        const grouped = new Map<string, ConnectionRow[]>();

        for (const connection of connections) {
            const group = catalogByKind.get(connection.kind)?.group ?? 'Otros';
            grouped.set(group, [...(grouped.get(group) ?? []), connection]);
        }

        return [...grouped.entries()];
    }, [connections, catalogByKind]);

    const openCreate = () => {
        setEditing(null);
        setWizardOpen(true);
    };

    const openEdit = (connection: ConnectionRow) => {
        setEditing(connection);
        setWizardOpen(true);
    };

    return (
        <MainLayout>
            <Head title="Conexiones" />

            <h1 className="sr-only">Conexiones</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <Heading
                            variant="small"
                            title="Conexiones"
                            description="Servicios externos que el agente y vos pueden operar."
                        />
                        <Button
                            onClick={openCreate}
                            className="bg-primary font-bold"
                            disabled={catalog.length === 0}
                        >
                            Nueva conexión
                        </Button>
                    </div>

                    {flash?.success && (
                        <div className="rounded-xl border border-primary/30 bg-primary/10 p-3 text-sm text-primary">
                            {flash.success}
                        </div>
                    )}

                    {flash?.error && (
                        <div className="rounded-xl border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
                            {flash.error}
                        </div>
                    )}

                    {flash?.test_result && (
                        <div
                            className={
                                flash.test_result.ok
                                    ? 'rounded-xl border border-primary/30 bg-primary/10 p-3 text-sm text-primary'
                                    : 'rounded-xl border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive'
                            }
                        >
                            {flash.test_result.ok
                                ? `Conexión OK: ${flash.test_result.message}`
                                : `Falló: ${flash.test_result.message}`}
                        </div>
                    )}

                    {connections.length === 0 ? (
                        <EmptyState
                            title="Todavía no hay conexiones"
                            description="Conectá GitHub, Google o Docker para que el agente pueda consultar datos y proponer acciones sobre tus servicios."
                            action={
                                <Button
                                    onClick={openCreate}
                                    className="bg-primary font-bold"
                                    disabled={catalog.length === 0}
                                >
                                    Nueva conexión
                                </Button>
                            }
                        />
                    ) : (
                        <div className="space-y-6">
                            {groups.map(([group, items]) => (
                                <section key={group} className="space-y-3">
                                    <h2 className="text-xs font-black uppercase tracking-widest text-muted-foreground">
                                        {group}
                                    </h2>
                                    <div className="grid gap-3 md:grid-cols-2">
                                        {items.map((connection) => (
                                            <ConnectionCard
                                                key={connection.id}
                                                connection={connection}
                                                catalogItem={catalogByKind.get(connection.kind)}
                                                onEdit={openEdit}
                                            />
                                        ))}
                                    </div>
                                </section>
                            ))}
                        </div>
                    )}
                </div>

                <ConnectionWizard
                    catalog={catalog}
                    connection={editing}
                    open={wizardOpen}
                    onOpenChange={setWizardOpen}
                />
            </SettingsLayout>
        </MainLayout>
    );
}
