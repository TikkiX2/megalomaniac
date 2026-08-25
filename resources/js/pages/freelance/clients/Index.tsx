import React, { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import freelance from '@/routes/freelance';
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Plus, Search, MoreHorizontal, Eye, Pencil, Trash } from 'lucide-react';

export default function ClientsIndex({ clients, filters }: any) {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (value: string) => {
        setSearch(value);
        router.get(
            freelance.clients.index().url,
            { search: value },
            { preserveState: true, replace: true }
        );
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Estás seguro de eliminar este cliente?')) {
            router.delete(freelance.clients.destroy(id).url);
        }
    };

    return (
        <MainLayout>
            <Head title="Clientes" />

            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Clientes</h1>
                        <p className="text-muted-foreground">Gestiona tu base de clientes y sus proyectos.</p>
                    </div>
                    <Button asChild className="bg-primary text-white font-bold">
                        <Link href={freelance.clients.create().url}>
                            <Plus className="mr-2 h-4 w-4" /> Nuevo Cliente
                        </Link>
                    </Button>
                </div>

                <div className="flex items-center gap-2">
                    <div className="relative flex-1 md:max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder="Buscar clientes por nombre o empresa..."
                            className="pl-8 bg-[#2b1a1a] border-[#3e2121]"
                            value={search}
                            onChange={(e) => handleSearch(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-xl border border-[#3e2121] bg-[#2b1a1a] overflow-hidden">
                    <Table>
                        <TableHeader className="bg-[#1c0f0f]">
                            <TableRow className="hover:bg-transparent border-[#3e2121]">
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Nombre</TableHead>
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Empresa</TableHead>
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Email</TableHead>
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Estado</TableHead>
                                <TableHead className="w-[80px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {clients.data.length === 0 ? (
                                <TableRow className="hover:bg-transparent border-[#3e2121]">
                                    <TableCell colSpan={5} className="text-center h-24 text-muted-foreground italic">
                                        No se encontraron clientes.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                clients.data.map((client: any) => (
                                    <TableRow key={client.id} className="hover:bg-white/5 border-[#3e2121]">
                                        <TableCell className="font-bold text-white">{client.name}</TableCell>
                                        <TableCell className="text-white/80">{client.company || '-'}</TableCell>
                                        <TableCell className="text-white/80">{client.email || '-'}</TableCell>
                                        <TableCell>
                                            <span className={`rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-tighter ${client.is_active ? 'bg-primary/20 text-primary' : 'bg-rose-500/20 text-rose-500'}`}>
                                                {client.is_active ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" className="h-8 w-8 p-0 text-white hover:bg-white/10">
                                                        <span className="sr-only">Abrir menú</span>
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                                    <DropdownMenuLabel className="text-[#e8b4b4] text-[10px] uppercase font-black">Acciones</DropdownMenuLabel>
                                                    <DropdownMenuItem asChild className="focus:bg-[#3e2121] focus:text-white">
                                                        <Link href={freelance.clients.edit(client.id).url}>
                                                            <Pencil className="mr-2 h-4 w-4" /> Editar
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator className="bg-[#3e2121]" />
                                                    <DropdownMenuItem
                                                        className="text-rose-400 focus:bg-rose-500/20 focus:text-rose-400"
                                                        onClick={() => handleDelete(client.id)}
                                                    >
                                                        <Trash className="mr-2 h-4 w-4" /> Eliminar
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </MainLayout>
    );
}
