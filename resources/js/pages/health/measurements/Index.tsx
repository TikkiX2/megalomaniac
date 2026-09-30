import { Head, Link, router, useForm } from '@inertiajs/react';
import { Activity, MoreHorizontal, Pencil, Plus, Trash } from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

const TYPE_LABELS: Record<string, string> = {
    weight: 'Peso',
    blood_pressure: 'Presión arterial',
    heart_rate: 'Frecuencia cardíaca',
    glucose: 'Glucosa',
    temperature: 'Temperatura',
    oxygen_saturation: 'Saturación de oxígeno',
    waist: 'Cintura',
};

interface ChartPoint {
    date: string;
    value: number;
}

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

function formatValue(measurement: any): string {
    const value = Number(measurement.value);
    if (measurement.type === 'blood_pressure' && measurement.secondary_value != null) {
        return `${value}/${Number(measurement.secondary_value)} ${measurement.unit}`;
    }
    return `${value} ${measurement.unit}`;
}

function EvolutionChart({ points, label }: { points: ChartPoint[]; label: string }) {
    if (points.length === 0) {
        return (
            <div className="flex h-44 items-center justify-center text-sm italic text-muted-foreground">
                Todavía no hay mediciones de {label.toLowerCase()}.
            </div>
        );
    }

    const width = 600;
    const height = 180;
    const padding = 18;
    const values = points.map((point) => Number(point.value));
    const min = Math.min(...values);
    const max = Math.max(...values);
    const span = max - min || 1;
    const innerHeight = height - padding * 2;
    const step = values.length > 1 ? (width - padding * 2) / (values.length - 1) : 0;
    const coords = values.map((value, index) => ({
        x: values.length > 1 ? padding + index * step : width / 2,
        y: height - padding - ((value - min) / span) * innerHeight,
    }));
    const polyline = coords.map((point) => `${point.x.toFixed(1)},${point.y.toFixed(1)}`).join(' ');
    const last = points[points.length - 1];

    return (
        <div className="flex w-full flex-col gap-2">
            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>Máx {max.toFixed(2)}</span>
                <span>
                    {points.length} {points.length === 1 ? 'punto' : 'puntos'} · último {last.date}
                </span>
                <span>Mín {min.toFixed(2)}</span>
            </div>
            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="h-44 w-full"
                preserveAspectRatio="none"
                role="img"
                aria-label={`Evolución de ${label}`}
            >
                <line
                    x1={padding} y1={padding} x2={width - padding} y2={padding}
                    className="stroke-border" strokeDasharray="6 6" strokeWidth="1"
                />
                <line
                    x1={padding} y1={height - padding} x2={width - padding} y2={height - padding}
                    className="stroke-border" strokeDasharray="6 6" strokeWidth="1"
                />
                {values.length > 1 && (
                    <polyline
                        points={polyline}
                        fill="none"
                        className="stroke-primary"
                        strokeWidth="2.5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        vectorEffect="non-scaling-stroke"
                    />
                )}
                {coords.map((point, index) => (
                    <circle key={index} cx={point.x} cy={point.y} r={values.length > 1 ? 3 : 5} className="fill-primary" />
                ))}
            </svg>
        </div>
    );
}

function blankForm() {
    return {
        type: 'weight',
        value: '',
        secondary_value: '',
        unit: 'kg',
        measured_at: localDateTimeInput(),
        person_id: '',
        notes: '',
    };
}

export default function MeasurementsIndex({ measurements: paginator, chart, filters, typeOptions, unitSuggestions, people }: any) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<any | null>(null);

    const currentType = typeOptions.includes(filters.type) ? filters.type : undefined;
    const selectedType = currentType || 'all';
    const chartType = currentType || 'weight';

    const form = useForm(blankForm());

    const applyFilter = (type: string) => {
        router.get(health.measurements.index().url, { ...filters, type: type === 'all' ? '' : type }, {
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

    const openEdit = (measurement: any) => {
        setEditing(measurement);
        form.setData({
            type: measurement.type,
            value: String(Number(measurement.value)),
            secondary_value: measurement.secondary_value != null ? String(Number(measurement.secondary_value)) : '',
            unit: measurement.unit ?? '',
            measured_at: localDateTimeInput(new Date(measurement.measured_at)),
            person_id: measurement.person_id ?? '',
            notes: measurement.notes ?? '',
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const changeType = (type: string) => {
        const suggestions = (unitSuggestions ?? {}) as Record<string, string>;
        const previousSuggestion = suggestions[form.data.type];
        const nextSuggestion = suggestions[type] ?? '';

        form.setData({
            ...form.data,
            type,
            unit: !form.data.unit || form.data.unit === previousSuggestion ? nextSuggestion : form.data.unit,
            secondary_value: type === 'blood_pressure' ? form.data.secondary_value : '',
        });
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
            measured_at: data.measured_at ? new Date(data.measured_at).toISOString() : data.measured_at,
        }));

        if (editing) {
            form.put(health.measurements.update(editing.id).url, options);
        } else {
            form.post(health.measurements.store().url, options);
        }
    };

    const remove = (id: number) => {
        if (confirm('¿Eliminar esta medición?')) {
            router.delete(health.measurements.destroy(id).url);
        }
    };

    return (
        <HealthLayout>
            <Head title="Mediciones" />
            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Mediciones</h1>
                        <p className="text-muted-foreground">Evolución de peso, presión y otros valores.</p>
                    </div>
                    <Button className="bg-primary text-white font-bold" onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" /> Nueva Medición
                    </Button>
                </div>

                <Card className="border-border bg-card">
                    <CardHeader className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <CardTitle className="flex items-center gap-2 text-white">
                            <Activity className="h-4 w-4 text-primary" />
                            Evolución · {TYPE_LABELS[chartType] ?? chartType}
                        </CardTitle>
                        <Select value={selectedType} onValueChange={applyFilter}>
                            <SelectTrigger className="md:w-56 bg-background border-border"><SelectValue placeholder="Tipo" /></SelectTrigger>
                            <SelectContent className="bg-card border-border text-white">
                                <SelectItem value="all">Todos los tipos</SelectItem>
                                {typeOptions.map((value: string) => (
                                    <SelectItem key={value} value={value}>{TYPE_LABELS[value] ?? value}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </CardHeader>
                    <CardContent>
                        <EvolutionChart points={chart ?? []} label={TYPE_LABELS[chartType] ?? chartType} />
                    </CardContent>
                </Card>

                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <Table>
                        <TableHeader className="bg-background">
                            <TableRow className="hover:bg-transparent border-border">
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Fecha</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Tipo</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Valor</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Persona</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Notas</TableHead>
                                <TableHead className="w-[80px]" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 ? (
                                <TableRow className="hover:bg-transparent border-border">
                                    <TableCell colSpan={6} className="text-center h-24 text-muted-foreground italic">
                                        No se encontraron mediciones.
                                    </TableCell>
                                </TableRow>
                            ) : paginator.data.map((measurement: any) => (
                                <TableRow key={measurement.id} className="hover:bg-white/5 border-border">
                                    <TableCell className="text-white/80">{formatDateTime(measurement.measured_at)}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline" className="border-primary/30 text-primary text-[10px] uppercase font-black">
                                            {TYPE_LABELS[measurement.type] ?? measurement.type}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="font-bold text-white">{formatValue(measurement)}</TableCell>
                                    <TableCell className="text-white/80">{fullName(measurement.person)}</TableCell>
                                    <TableCell className="text-muted-foreground max-w-[220px] truncate">{measurement.notes || '—'}</TableCell>
                                    <TableCell>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" className="h-8 w-8 p-0 text-white hover:bg-white/10">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="bg-card border-border text-white">
                                                <DropdownMenuLabel className="text-muted-foreground text-[10px] uppercase font-black">Acciones</DropdownMenuLabel>
                                                <DropdownMenuItem className="focus:bg-secondary focus:text-white" onClick={() => openEdit(measurement)}>
                                                    <Pencil className="mr-2 h-4 w-4" /> Editar
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator className="bg-border" />
                                                <DropdownMenuItem className="text-destructive focus:bg-destructive/20" onClick={() => remove(measurement.id)}>
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
                            <DialogTitle>{editing ? 'Editar medición' : 'Nueva medición'}</DialogTitle>
                            <DialogDescription className="text-muted-foreground">
                                {editing && editing.type === 'weight' && editing.person_id == null
                                    ? 'Al guardar se actualiza tu peso de perfil.'
                                    : 'Registrá un valor para seguir su evolución.'}
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label>Tipo</Label>
                                <Select value={form.data.type} onValueChange={changeType}>
                                    <SelectTrigger className="bg-background border-border"><SelectValue /></SelectTrigger>
                                    <SelectContent className="bg-card border-border text-white">
                                        {typeOptions.map((value: string) => (
                                            <SelectItem key={value} value={value}>{TYPE_LABELS[value] ?? value}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.type && <p className="text-xs text-destructive">{form.errors.type}</p>}
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="value">Valor</Label>
                                    <Input
                                        id="value"
                                        type="number"
                                        step="0.01"
                                        value={form.data.value}
                                        onChange={(e) => form.setData('value', e.target.value)}
                                        placeholder="0.00"
                                        className="bg-background border-border"
                                    />
                                    {form.errors.value && <p className="text-xs text-destructive">{form.errors.value}</p>}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="unit">Unidad</Label>
                                    <Input
                                        id="unit"
                                        value={form.data.unit}
                                        onChange={(e) => form.setData('unit', e.target.value)}
                                        placeholder={unitSuggestions?.[form.data.type] ?? ''}
                                        className="bg-background border-border"
                                    />
                                    {form.errors.unit && <p className="text-xs text-destructive">{form.errors.unit}</p>}
                                </div>
                            </div>
                            {form.data.type === 'blood_pressure' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="secondary_value">Diastólica (secundario)</Label>
                                    <Input
                                        id="secondary_value"
                                        type="number"
                                        step="0.01"
                                        value={form.data.secondary_value}
                                        onChange={(e) => form.setData('secondary_value', e.target.value)}
                                        placeholder="76"
                                        className="bg-background border-border"
                                    />
                                    {form.errors.secondary_value && <p className="text-xs text-destructive">{form.errors.secondary_value}</p>}
                                </div>
                            )}
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="measured_at">Fecha y hora</Label>
                                    <Input
                                        id="measured_at"
                                        type="datetime-local"
                                        value={form.data.measured_at}
                                        onChange={(e) => form.setData('measured_at', e.target.value)}
                                        className="bg-background border-border"
                                    />
                                    {form.errors.measured_at && <p className="text-xs text-destructive">{form.errors.measured_at}</p>}
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
                                    {form.processing ? 'Guardando…' : editing ? 'Guardar cambios' : 'Registrar medición'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </HealthLayout>
    );
}
