import { Head, Link, router, useForm } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Plus, Search, Trash } from 'lucide-react';
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
import { Textarea } from '@/components/ui/textarea';
import HealthLayout from '@/layouts/health-layout';
import health from '@/routes/health';

const SEVERITY_LABELS: Record<string, string> = {
    mild: 'Leve',
    moderate: 'Moderada',
    severe: 'Severa',
};

const SEVERITY_CLASSES: Record<string, string> = {
    mild: 'border-border text-muted-foreground',
    moderate: 'border-primary/40 text-primary',
    severe: 'border-destructive/50 text-destructive',
};

function fullName(person: { first_name?: string; last_name?: string } | null): string {
    if (!person) return '—';
    return `${person.first_name ?? ''} ${person.last_name ?? ''}`.trim() || '—';
}

function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' });
}

function localDateTimeInput(date: Date = new Date()): string {
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

function blankForm() {
    return {
        symptom: '',
        severity: 'mild',
        occurred_at: localDateTimeInput(),
        person_id: '',
        notes: '',
    };
}

export default function SymptomsIndex({ symptoms: paginator, filters, severityOptions, people }: any) {
    const [search, setSearch] = useState(filters.search || '');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<any | null>(null);

    const form = useForm(blankForm());

    const applyFilter = (patch: Record<string, string>) => {
        router.get(health.symptoms.index().url, { ...filters, search, ...patch }, {
            preserveState: true,
            replace: true,
        });
    };

    const openCreate = () => {
        setEditing(null);
        form.setData(blankForm());
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (symptom: any) => {
        setEditing(symptom);
        form.setData({
            symptom: symptom.symptom ?? '',
            severity: symptom.severity ?? 'mild',
            occurred_at: localDateTimeInput(new Date(symptom.occurred_at)),
            person_id: symptom.person_id ?? '',
            notes: symptom.notes ?? '',
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                setEditing(null);
            },
        };

        form.transform((data) => ({
            ...data,
            occurred_at: data.occurred_at ? new Date(data.occurred_at).toISOString() : data.occurred_at,
        }));

        if (editing) {
            form.put(health.symptoms.update(editing.id).url, options);
        } else {
            form.post(health.symptoms.store().url, options);
        }
    };

    const remove = (id: number) => {
        if (confirm('¿Eliminar este síntoma?')) {
            router.delete(health.symptoms.destroy(id).url);
        }
    };

    return (
        <HealthLayout>
            <Head title="Síntomas" />
            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Síntomas</h1>
                        <p className="text-muted-foreground">Registro de molestias y episodios.</p>
                    </div>
                    <Button className="bg-primary text-white font-bold" onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" /> Nuevo Síntoma
                    </Button>
                </div>

                <div className="flex flex-col gap-2 md:flex-row md:items-center">
                    <div className="relative flex-1 md:max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder="Buscar síntoma..."
                            className="pl-8 bg-card border-border"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilter({})}
                        />
                    </div>
                    <Select
                        value={filters.severity || 'all'}
                        onValueChange={(value) => applyFilter({ severity: value === 'all' ? '' : value })}
                    >
                        <SelectTrigger className="md:w-44 bg-card border-border"><SelectValue placeholder="Severidad" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Toda severidad</SelectItem>
                            {severityOptions.map((value: string) => (
                                <SelectItem key={value} value={value}>{SEVERITY_LABELS[value] ?? value}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <Table>
                        <TableHeader className="bg-background">
                            <TableRow className="hover:bg-transparent border-border">
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Fecha</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Síntoma</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Severidad</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Persona</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Notas</TableHead>
                                <TableHead className="w-[80px]" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 ? (
                                <TableRow className="hover:bg-transparent border-border">
                                    <TableCell colSpan={6} className="text-center h-24 text-muted-foreground italic">
                                        No se encontraron síntomas.
                                    </TableCell>
                                </TableRow>
                            ) : paginator.data.map((symptom: any) => (
                                <TableRow key={symptom.id} className="hover:bg-white/5 border-border">
                                    <TableCell className="text-white/80">{formatDateTime(symptom.occurred_at)}</TableCell>
                                    <TableCell className="font-bold text-white">{symptom.symptom}</TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            className={`text-[10px] uppercase font-black ${SEVERITY_CLASSES[symptom.severity] ?? 'border-border text-muted-foreground'}`}
                                        >
                                            {SEVERITY_LABELS[symptom.severity] ?? symptom.severity}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-white/80">{fullName(symptom.person)}</TableCell>
                                    <TableCell className="text-muted-foreground max-w-[220px] truncate">{symptom.notes || '—'}</TableCell>
                                    <TableCell>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" className="h-8 w-8 p-0 text-white hover:bg-white/10">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="bg-card border-border text-white">
                                                <DropdownMenuLabel className="text-muted-foreground text-[10px] uppercase font-black">Acciones</DropdownMenuLabel>
                                                <DropdownMenuItem className="focus:bg-secondary focus:text-white" onClick={() => openEdit(symptom)}>
                                                    <Pencil className="mr-2 h-4 w-4" /> Editar
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator className="bg-border" />
                                                <DropdownMenuItem className="text-destructive focus:bg-destructive/20" onClick={() => remove(symptom.id)}>
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

                <Dialog open={dialogOpen} onOpenChange={(open) => { setDialogOpen(open); if (!open) setEditing(null); }}>
                    <DialogContent className="bg-card border-border text-white sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>{editing ? 'Editar síntoma' : 'Nuevo síntoma'}</DialogTitle>
                            <DialogDescription className="text-muted-foreground">
                                {editing ? 'Actualizá los datos del episodio.' : 'Registrá una molestia para ver su evolución.'}
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="symptom">Síntoma</Label>
                                <Input
                                    id="symptom"
                                    value={form.data.symptom}
                                    onChange={(e) => form.setData('symptom', e.target.value)}
                                    placeholder="Calambre, mareo, orina oscura…"
                                    className="bg-background border-border"
                                />
                                {form.errors.symptom && <p className="text-xs text-destructive">{form.errors.symptom}</p>}
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Severidad</Label>
                                    <Select value={form.data.severity} onValueChange={(value) => form.setData('severity', value)}>
                                        <SelectTrigger className="bg-background border-border"><SelectValue /></SelectTrigger>
                                        <SelectContent className="bg-card border-border text-white">
                                            {severityOptions.map((value: string) => (
                                                <SelectItem key={value} value={value}>{SEVERITY_LABELS[value] ?? value}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.severity && <p className="text-xs text-destructive">{form.errors.severity}</p>}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="occurred_at">Fecha y hora</Label>
                                    <Input
                                        id="occurred_at"
                                        type="datetime-local"
                                        value={form.data.occurred_at}
                                        onChange={(e) => form.setData('occurred_at', e.target.value)}
                                        className="bg-background border-border"
                                    />
                                    {form.errors.occurred_at && <p className="text-xs text-destructive">{form.errors.occurred_at}</p>}
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label>Persona</Label>
                                <Select
                                    value={form.data.person_id === '' ? 'none' : String(form.data.person_id)}
                                    onValueChange={(value) => form.setData('person_id', value === 'none' ? '' : value)}
                                >
                                    <SelectTrigger className="bg-background border-border"><SelectValue placeholder="Para mí" /></SelectTrigger>
                                    <SelectContent className="bg-card border-border text-white">
                                        <SelectItem value="none">Para mí</SelectItem>
                                        {people.map((person: any) => (
                                            <SelectItem key={person.id} value={String(person.id)}>
                                                {`${person.first_name ?? ''} ${person.last_name ?? ''}`.trim()}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.person_id && <p className="text-xs text-destructive">{form.errors.person_id}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notas</Label>
                                <Textarea
                                    id="notes"
                                    value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)}
                                    className="bg-background border-border"
                                />
                                {form.errors.notes && <p className="text-xs text-destructive">{form.errors.notes}</p>}
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>Cancelar</Button>
                                <Button type="submit" disabled={form.processing} className="bg-primary text-white font-bold">
                                    {form.processing ? 'Guardando…' : editing ? 'Guardar cambios' : 'Registrar síntoma'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </HealthLayout>
    );
}
