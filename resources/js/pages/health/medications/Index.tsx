import { Head, Link, router } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Pill, Plus, Search, Trash } from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel,
    DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import HealthLayout from '@/layouts/health-layout';
import health from '@/routes/health';

const INTAKE_STATUS_LABELS: Record<string, string> = {
    taken: 'Tomada',
    skipped: 'Omitida',
};

function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' });
}

function localDateTimeInput(date: Date): string {
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

function doseLabel(medication: any): string {
    return [medication.dose_amount, medication.dose_unit].filter(Boolean).join(' ') || '—';
}

export default function MedicationsIndex({ medications: paginator, filters, statusOptions }: any) {
    const [search, setSearch] = useState(filters.search || '');
    const [intakeMedication, setIntakeMedication] = useState<any | null>(null);
    const [intakeDate, setIntakeDate] = useState('');
    const [intakeStatus, setIntakeStatus] = useState('taken');
    const [processingIntake, setProcessingIntake] = useState(false);

    const applyFilter = (patch: Record<string, string>) => {
        router.get(health.medications.index().url, { ...filters, search, ...patch }, {
            preserveState: true,
            replace: true,
        });
    };

    const remove = (id: number) => {
        if (confirm('¿Eliminar esta medicación?')) {
            router.delete(health.medications.destroy(id).url);
        }
    };

    const removeIntake = (medication: any) => {
        if (!medication.last_intake_id) return;
        if (confirm(`¿Eliminar la última toma de ${medication.name}?`)) {
            router.delete(health.medications.intakes.destroy({
                medication: medication.id,
                intake: medication.last_intake_id,
            }).url);
        }
    };

    const openIntakeDialog = (medication: any) => {
        setIntakeMedication(medication);
        setIntakeStatus('taken');
        setIntakeDate(localDateTimeInput(new Date()));
    };

    const submitIntake = (e: React.FormEvent) => {
        e.preventDefault();
        if (!intakeMedication) return;

        router.post(
            health.medications.intakes.store(intakeMedication.id).url,
            { taken_at: intakeDate, status: intakeStatus },
            {
                preserveScroll: true,
                onStart: () => setProcessingIntake(true),
                onFinish: () => setProcessingIntake(false),
                onSuccess: () => setIntakeMedication(null),
            },
        );
    };

    return (
        <HealthLayout>
            <Head title="Medicación" />
            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Medicación</h1>
                        <p className="text-muted-foreground">Tratamientos y registro de tomas.</p>
                    </div>
                    <Button asChild className="bg-primary text-white font-bold">
                        <Link href={health.medications.create().url}><Plus className="mr-2 h-4 w-4" /> Nueva Medicación</Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-2 md:flex-row md:items-center">
                    <div className="relative flex-1 md:max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder="Buscar por nombre..."
                            className="pl-8 bg-card border-border"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilter({})}
                        />
                    </div>
                    <Select value={filters.active ?? 'all'} onValueChange={(value) => applyFilter({ active: value === 'all' ? '' : value })}>
                        <SelectTrigger className="md:w-44 bg-card border-border"><SelectValue placeholder="Estado" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todo estado</SelectItem>
                            <SelectItem value="1">Activos</SelectItem>
                            <SelectItem value="0">Inactivos</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <Table>
                        <TableHeader className="bg-background">
                            <TableRow className="hover:bg-transparent border-border">
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Medicamento</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Dosis</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Condición</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Tomas</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Último intake</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Estado</TableHead>
                                <TableHead className="w-[80px]" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 ? (
                                <TableRow className="hover:bg-transparent border-border">
                                    <TableCell colSpan={7} className="text-center h-24 text-muted-foreground italic">
                                        No se encontraron medicamentos.
                                    </TableCell>
                                </TableRow>
                            ) : paginator.data.map((medication: any) => (
                                <TableRow key={medication.id} className="hover:bg-white/5 border-border">
                                    <TableCell>
                                        <span className="font-bold text-white">{medication.name}</span>
                                        {medication.frequency_text && (
                                            <span className="block text-xs text-muted-foreground">{medication.frequency_text}</span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-white/80">{doseLabel(medication)}</TableCell>
                                    <TableCell className="text-white/80">{medication.condition?.name ?? '—'}</TableCell>
                                    <TableCell className="text-white/80">{medication.intakes_count}</TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-1">
                                            <span className="text-white/80">{formatDateTime(medication.intakes_max_taken_at)}</span>
                                            {medication.last_intake_id && (
                                                <Button
                                                    variant="ghost"
                                                    className="h-6 w-6 p-0 text-muted-foreground hover:text-destructive"
                                                    title="Eliminar última toma"
                                                    onClick={() => removeIntake(medication)}
                                                >
                                                    <Trash className="h-3 w-3" />
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            className={`text-[10px] uppercase font-black ${medication.is_active ? 'border-primary/40 text-primary' : 'border-border text-muted-foreground'}`}
                                        >
                                            {medication.is_active ? 'Activo' : 'Inactivo'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" className="h-8 w-8 p-0 text-white hover:bg-white/10">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="bg-card border-border text-white">
                                                <DropdownMenuLabel className="text-muted-foreground text-[10px] uppercase font-black">Acciones</DropdownMenuLabel>
                                                <DropdownMenuItem className="focus:bg-secondary focus:text-white" onClick={() => openIntakeDialog(medication)}>
                                                    <Pill className="mr-2 h-4 w-4" /> Registrar toma
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild className="focus:bg-secondary focus:text-white">
                                                    <Link href={health.medications.edit(medication.id).url}><Pencil className="mr-2 h-4 w-4" /> Editar</Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator className="bg-border" />
                                                <DropdownMenuItem className="text-destructive focus:bg-destructive/20" onClick={() => remove(medication.id)}>
                                                    <Trash className="mr-2 h-4 w-4" /> Eliminar
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                {(paginator.prev_page_url || paginator.next_page_url) && (
                    <div className="flex items-center justify-end gap-2">
                        <Button variant="outline" disabled={!paginator.prev_page_url} asChild={!!paginator.prev_page_url}>
                            {paginator.prev_page_url
                                ? <Link href={paginator.prev_page_url}>Anterior</Link>
                                : <span>Anterior</span>}
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            Página {paginator.current_page} de {paginator.last_page}
                        </span>
                        <Button variant="outline" disabled={!paginator.next_page_url} asChild={!!paginator.next_page_url}>
                            {paginator.next_page_url
                                ? <Link href={paginator.next_page_url}>Siguiente</Link>
                                : <span>Siguiente</span>}
                        </Button>
                    </div>
                )}

                <Dialog open={!!intakeMedication} onOpenChange={(open) => !open && setIntakeMedication(null)}>
                    <DialogContent className="bg-card border-border text-white sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Registrar toma</DialogTitle>
                            <DialogDescription className="text-muted-foreground">
                                {intakeMedication ? `Nueva toma de ${intakeMedication.name}.` : ''}
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={submitIntake} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="taken_at">Fecha y hora</Label>
                                <Input
                                    id="taken_at"
                                    type="datetime-local"
                                    value={intakeDate}
                                    onChange={(e) => setIntakeDate(e.target.value)}
                                    required
                                    className="bg-background border-border"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>Estado</Label>
                                <Select value={intakeStatus} onValueChange={setIntakeStatus}>
                                    <SelectTrigger className="bg-background border-border"><SelectValue /></SelectTrigger>
                                    <SelectContent className="bg-card border-border text-white">
                                        {statusOptions.map((value: string) => (
                                            <SelectItem key={value} value={value}>{INTAKE_STATUS_LABELS[value] ?? value}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setIntakeMedication(null)}>Cancelar</Button>
                                <Button type="submit" disabled={processingIntake} className="bg-primary text-white font-bold">
                                    {processingIntake ? 'Guardando…' : 'Registrar toma'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </HealthLayout>
    );
}
