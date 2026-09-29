import { router } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface MoveProjectDialogProps {
    project: {
        id: number;
        name: string;
        type: 'personal' | 'freelance';
        status?: string;
    };
    clients: { id: number; name: string }[];
    updateUrl: string;
}

export function MoveProjectDialog({ project, clients, updateUrl }: MoveProjectDialogProps) {
    const defaultType = project.type === 'personal' ? 'freelance' : 'personal';
    const [open, setOpen] = useState(false);
    const [type, setType] = useState<'personal' | 'freelance'>(defaultType);
    const [clientId, setClientId] = useState('');
    const [processing, setProcessing] = useState(false);

    const needsClient = type === 'freelance';
    const canSubmit = !needsClient || clientId !== '';

    const submit = () => {
        setProcessing(true);
        router.put(
            updateUrl,
            {
                name: project.name,
                status: project.status,
                type,
                client_id: needsClient ? clientId : null,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => setOpen(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <ArrowLeftRight className="mr-2 h-4 w-4" />
                    Mover a…
                </Button>
            </DialogTrigger>
            <DialogContent className="bg-card border-border">
                <DialogHeader>
                    <DialogTitle>Mover proyecto de módulo</DialogTitle>
                    <DialogDescription>
                        El proyecto cambiará de módulo y las columnas del tablero se re-mapean
                        automáticamente.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-2">
                        <Label>Módulo destino</Label>
                        <Select
                            value={type}
                            onValueChange={(value) => setType(value as 'personal' | 'freelance')}
                        >
                            <SelectTrigger className="bg-background border-border">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="personal">Personal</SelectItem>
                                <SelectItem value="freelance">Freelance</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {needsClient && (
                        <div className="flex flex-col gap-2">
                            <Label>Cliente *</Label>
                            <Select value={clientId} onValueChange={setClientId}>
                                <SelectTrigger className="bg-background border-border">
                                    <SelectValue placeholder="Seleccionar cliente" />
                                </SelectTrigger>
                                <SelectContent>
                                    {clients.map((client) => (
                                        <SelectItem key={client.id} value={String(client.id)}>
                                            {client.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {clients.length === 0 && (
                                <p className="text-xs text-muted-foreground">
                                    Necesitás al menos un cliente para mover a Freelance.
                                </p>
                            )}
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="ghost" onClick={() => setOpen(false)} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={processing || !canSubmit}
                        className="bg-primary font-bold"
                    >
                        Mover proyecto
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
