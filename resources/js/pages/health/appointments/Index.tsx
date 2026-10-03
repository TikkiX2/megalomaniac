import { Head, Link, router } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Plus, Search, Trash } from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import HealthLayout from '@/layouts/health-layout';
import health from '@/routes/health';

const STATUS_LABELS: Record<string, string> = {
    scheduled: 'Programada',
    completed: 'Completada',
    cancelled: 'Cancelada',
    no_show: 'No show',
};

function fullName(person: { first_name?: string; last_name?: string } | null): string {
    if (!person) return '—';
    return `${person.first_name ?? ''} ${person.last_name ?? ''}`.trim() || '—';
}

export default function AppointmentsIndex({ appointments: paginator, filters, statusOptions }: any) {
    const [search, setSearch] = useState(filters.search || '');

    const applyFilter = (patch: Record<string, string>) => {
        router.get(health.appointments.index().url, { ...filters, search, ...patch }, {
            preserveState: true,
            replace: true,
        });
    };

    const remove = (id: number) => {
        if (confirm('¿Eliminar esta cita?')) {
            router.delete(health.appointments.destroy(id).url);
        }
    };

    return (
        <HealthLayout>
            <Head title="Citas" />
            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Citas médicas</h1>
                        <p className="text-muted-foreground">Agenda y seguimiento de consultas.</p>
                    </div>
                    <Button asChild className="bg-primary text-white font-bold">
                        <Link href={health.appointments.create().url}>
                            <Plus className="mr-2 h-4 w-4" /> Nueva cita
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-2 md:flex-row md:items-center">
                    <div className="relative flex-1 md:max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder="Buscar por título..."
                            className="pl-8 bg-card border-border"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilter({})}
                        />
                    </div>
                    <Select value={filters.status || 'all'} onValueChange={(value) => applyFilter({ status: value === 'all' ? '' : value })}>
                        <SelectTrigger className="md:w-44 bg-card border-border">
                            <SelectValue placeholder="Estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todo estado</SelectItem>
                            {statusOptions.map((value: string) => (
                                <SelectItem key={value} value={value}>{STATUS_LABELS[value] ?? value}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <Table>
                        <TableHeader className="bg-background">
                            <TableRow className="hover:bg-transparent border-border">
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Título</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Programada</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Estado</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Persona</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Profesional</TableHead>
                                <TableHead className="w-[80px]" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 ? (
                                <TableRow className="hover:bg-transparent border-border">
                                    <TableCell colSpan={6} className="text-center h-24 text-muted-foreground italic">
                                        No se encontraron citas.
                                    </TableCell>
                                </TableRow>
                            ) : paginator.data.map((appointment: any) => (
                                <TableRow key={appointment.id} className="hover:bg-white/5 border-border">
                                    <TableCell>
                                        <span className="font-bold text-white">{appointment.title}</span>
                                    </TableCell>
                                    <TableCell className="text-white/80">
                                        {new Date(appointment.scheduled_at).toLocaleString()}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline" className="border-primary/30 text-primary text-[10px] uppercase font-black">
                                            {STATUS_LABELS[appointment.status] ?? appointment.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-white/80">{fullName(appointment.person)}</TableCell>
                                    <TableCell className="text-white/80">{appointment.provider?.name ?? '—'}</TableCell>
                                    <TableCell>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" className="h-8 w-8 p-0 text-white hover:bg-white/10">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="bg-card border-border text-white">
                                                <DropdownMenuLabel className="text-muted-foreground text-[10px] uppercase font-black">Acciones</DropdownMenuLabel>
                                                <DropdownMenuItem asChild className="focus:bg-secondary focus:text-white">
                                                    <Link href={health.appointments.edit(appointment.id).url}>
                                                        <Pencil className="mr-2 h-4 w-4" /> Editar
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator className="bg-border" />
                                                <DropdownMenuItem className="text-destructive focus:bg-destructive/20" onClick={() => remove(appointment.id)}>
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
                            {paginator.prev_page_url ? <Link href={paginator.prev_page_url}>Anterior</Link> : <span>Anterior</span>}
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            Página {paginator.current_page} de {paginator.last_page}
                        </span>
                        <Button variant="outline" disabled={!paginator.next_page_url} asChild={!!paginator.next_page_url}>
                            {paginator.next_page_url ? <Link href={paginator.next_page_url}>Siguiente</Link> : <span>Siguiente</span>}
                        </Button>
                    </div>
                )}
            </div>
        </HealthLayout>
    );
}
