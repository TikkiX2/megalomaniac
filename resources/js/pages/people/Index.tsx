import { Head, Link, router } from '@inertiajs/react';
import { Archive, Cake, MoreHorizontal, Pencil, Plus, Search, Star, Trash } from 'lucide-react';
import React, { useState } from 'react';
import { ModuleAiButton } from '@/components/ai/module-ai-button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel,
    DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import PeopleLayout from '@/layouts/people-layout';
import people from '@/routes/people';

const CLOSENESS_LABELS: Record<string, string> = {
    inner_circle: 'Círculo íntimo',
    close: 'Cercano',
    friend: 'Amigo',
    acquaintance: 'Conocido',
};

function relativeDays(value: string | null): string {
    if (!value) return 'Sin registro';
    const days = Math.floor((Date.now() - new Date(value).getTime()) / 86_400_000);
    if (days <= 0) return 'Hoy';
    if (days === 1) return 'Ayer';
    if (days < 30) return `Hace ${days} días`;
    const months = Math.floor(days / 30);
    return months === 1 ? 'Hace 1 mes' : `Hace ${months} meses`;
}

export default function PeopleIndex({ people: paginator, filters, closenessOptions }: any) {
    const [search, setSearch] = useState(filters.search || '');

    const applyFilter = (patch: Record<string, string>) => {
        router.get(people.index().url, { ...filters, search, ...patch }, {
            preserveState: true,
            replace: true,
        });
    };

    const remove = (id: number) => {
        if (confirm('¿Eliminar esta persona y su historial?')) {
            router.delete(people.destroy(id).url);
        }
    };

    return (
        <PeopleLayout>
            <Head title="Personas" />
            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Personas</h1>
                        <p className="text-muted-foreground">Tu agenda social: vínculos, fechas y contacto.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={people.calendar().url}><Cake className="mr-2 h-4 w-4" /> Calendario</Link>
                        </Button>
                        <Button asChild className="bg-primary text-white font-bold">
                            <Link href={people.create().url}><Plus className="mr-2 h-4 w-4" /> Nueva Persona</Link>
                        </Button>
                        <ModuleAiButton module="people" />
                    </div>
                </div>

                <div className="flex flex-col gap-2 md:flex-row md:items-center">
                    <div className="relative flex-1 md:max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder="Buscar por nombre, alias o empresa..."
                            className="pl-8 bg-card border-border"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilter({})}
                        />
                    </div>
                    <Select value={filters.closeness || 'all'} onValueChange={(value) => applyFilter({ closeness: value === 'all' ? '' : value })}>
                        <SelectTrigger className="md:w-44 bg-card border-border"><SelectValue placeholder="Cercanía" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Toda cercanía</SelectItem>
                            {closenessOptions.map((value: string) => (
                                <SelectItem key={value} value={value}>{CLOSENESS_LABELS[value] ?? value}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={filters.stale_days || 'all'} onValueChange={(value) => applyFilter({ stale_days: value === 'all' ? '' : value })}>
                        <SelectTrigger className="md:w-48 bg-card border-border"><SelectValue placeholder="Contacto" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Sin filtro de contacto</SelectItem>
                            <SelectItem value="30">Sin contacto +30 días</SelectItem>
                            <SelectItem value="60">Sin contacto +60 días</SelectItem>
                            <SelectItem value="90">Sin contacto +90 días</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button
                        variant={filters.favorites ? 'default' : 'outline'}
                        className={filters.favorites ? 'bg-primary' : ''}
                        onClick={() => applyFilter({ favorites: filters.favorites ? '' : '1' })}
                    >
                        <Star className="mr-2 h-4 w-4" /> Favoritos
                    </Button>
                    <Button
                        variant={filters.archived ? 'default' : 'outline'}
                        className={filters.archived ? 'bg-primary' : ''}
                        onClick={() => applyFilter({ archived: filters.archived ? '' : '1' })}
                    >
                        <Archive className="mr-2 h-4 w-4" /> Archivados
                    </Button>
                </div>

                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <Table>
                        <TableHeader className="bg-background">
                            <TableRow className="hover:bg-transparent border-border">
                                <TableHead />
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Nombre</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Cercanía</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Último contacto</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Cumpleaños</TableHead>
                                <TableHead className="w-[80px]" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 ? (
                                <TableRow className="hover:bg-transparent border-border">
                                    <TableCell colSpan={6} className="text-center h-24 text-muted-foreground italic">
                                        No se encontraron personas.
                                    </TableCell>
                                </TableRow>
                            ) : paginator.data.map((person: any) => (
                                <TableRow key={person.id} className="hover:bg-white/5 border-border">
                                    <TableCell>
                                        <Avatar className="h-9 w-9">
                                            {person.avatar_url && <AvatarImage src={person.avatar_url} alt={person.full_name} />}
                                            <AvatarFallback className="bg-primary/20 text-primary text-xs font-black">
                                                {person.first_name?.[0]?.toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                    </TableCell>
                                    <TableCell>
                                        <Link href={people.show(person.id).url} className="font-bold text-white hover:text-primary">
                                            {person.full_name}
                                        </Link>
                                        {person.nickname && <span className="ml-2 text-xs text-muted-foreground">“{person.nickname}”</span>}
                                        {person.is_favorite && <Star className="ml-2 inline h-3 w-3 fill-primary text-primary" />}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline" className="border-primary/30 text-primary text-[10px] uppercase font-black">
                                            {CLOSENESS_LABELS[person.closeness] ?? person.closeness}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-white/80">{relativeDays(person.last_contacted_at)}</TableCell>
                                    <TableCell className="text-white/80">{person.birthday?.slice(0, 10) || '-'}</TableCell>
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
                                                    <Link href={people.edit(person.id).url}><Pencil className="mr-2 h-4 w-4" /> Editar</Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator className="bg-border" />
                                                <DropdownMenuItem className="text-destructive focus:bg-destructive/20" onClick={() => remove(person.id)}>
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
            </div>
        </PeopleLayout>
    );
}
