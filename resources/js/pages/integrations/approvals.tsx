import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import ApprovalCard from '@/components/integrations/ApprovalCard';
import EmptyState from '@/components/integrations/EmptyState';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import MainLayout from '@/layouts/main-layout';
import type { SharedData } from '@/types';
import type { ApprovalRow, FlashProps } from '@/types/integrations';

interface ApprovalsPageProps {
    pending: ApprovalRow[];
    history: ApprovalRow[];
    flash: FlashProps;
}

export default function Approvals() {
    const { pending, history, flash } = usePage<SharedData & ApprovalsPageProps>().props;

    return (
        <MainLayout>
            <Head title="Aprobaciones" />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                <Heading
                    title="Aprobaciones"
                    description="Acciones de escritura o destructivas que el agente quiere ejecutar."
                />

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

                <Tabs defaultValue="pending" className="space-y-4">
                    <TabsList className="bg-card border border-border">
                        <TabsTrigger value="pending">
                            Pendientes{pending.length > 0 ? ` (${pending.length})` : ''}
                        </TabsTrigger>
                        <TabsTrigger value="history">Historial</TabsTrigger>
                    </TabsList>

                    <TabsContent value="pending" className="space-y-3">
                        {pending.length === 0 ? (
                            <EmptyState
                                title="Sin aprobaciones pendientes"
                                description="Cuando el agente proponga una acción de escritura, aparecerá acá para que la apruebes o rechaces."
                            />
                        ) : (
                            pending.map((approval) => (
                                <ApprovalCard key={approval.id} approval={approval} />
                            ))
                        )}
                    </TabsContent>

                    <TabsContent value="history" className="space-y-3">
                        {history.length === 0 ? (
                            <EmptyState
                                title="Sin historial"
                                description="Las decisiones que tomes sobre las aprobaciones quedan registradas acá."
                            />
                        ) : (
                            history.map((approval) => (
                                <ApprovalCard
                                    key={approval.id}
                                    approval={approval}
                                    pending={false}
                                />
                            ))
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </MainLayout>
    );
}
