import React, { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import freelance from '@/routes/freelance';
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
} from '@/components/ui/table';
import {
    Plus,
    Search,
    MoreHorizontal,
    Eye,
    Pencil,
    Trash,
    ArrowRight
} from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Progress } from '@/components/ui/progress';

export default function ProjectsIndex({ projects, filters }: any) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');

    const handleSearch = (value: string) => {
        setSearch(value);
        router.get(
            freelance.projects.index().url,
            { search: value, status },
            { preserveState: true, replace: true }
        );
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Estás seguro de eliminar este proyecto?')) {
            router.delete(freelance.projects.destroy(id).url);
        }
    };

    const getStatusBadge = (status: string) => {
        const styles: Record<string, string> = {
            'pending': 'bg-gray-500/20 text-gray-500',
            'in_progress': 'bg-primary/20 text-primary border-primary/20',
            'completed': 'bg-primary/20 text-primary',
            'maintenance': 'bg-purple-500/20 text-purple-500',
            'cancelled': 'bg-rose-500/20 text-rose-500',
        };
        return <Badge variant="outline" className={`border-0 font-black uppercase tracking-tighter text-[10px] ${styles[status] || ''}`}>{status.replace('_', ' ')}</Badge>;
    };

    return (
        <MainLayout>
            <Head title="Proyectos" />

            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Proyectos</h1>
                        <p className="text-muted-foreground">Gestiona tus trabajos y su progreso.</p>
                    </div>
                    <Button asChild className="bg-primary text-white font-bold">
                        <Link href={freelance.projects.create().url}>
                            <Plus className="mr-2 h-4 w-4" /> Nuevo Proyecto
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col md:flex-row gap-4 items-center">
                    <div className="relative flex-1 md:max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder="Buscar proyectos..."
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
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Proyecto</TableHead>
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Cliente</TableHead>
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Estado</TableHead>
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Presupuesto</TableHead>
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Fecha Entrega</TableHead>
                                <TableHead className="w-[80px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {projects.data.length === 0 ? (
                                <TableRow className="hover:bg-transparent border-[#3e2121]">
                                    <TableCell colSpan={6} className="text-center h-24 text-muted-foreground italic">
                                        No se encontraron proyectos.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                projects.data.map((project: any) => (
                                    <TableRow key={project.id} className="hover:bg-white/5 border-[#3e2121]">
                                        <TableCell>
                                            <div className="flex flex-col">
                                                <span className="font-bold text-white">{project.name}</span>
                                                <span className="text-[10px] text-muted-foreground uppercase">{project.area} / {project.module}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-white/80">{project.client?.name}</TableCell>
                                        <TableCell>{getStatusBadge(project.status)}</TableCell>
                                        <TableCell>
                                            <div className="flex flex-col gap-1 w-32">
                                                <div className="flex justify-between text-[10px] font-bold">
                                                    <span className="text-[#e8b4b4]">{project.currency?.symbol}{Number(project.paid_amount).toLocaleString()}</span>
                                                    <span className="text-white/60">/ {project.currency?.symbol}{Number(project.total_amount).toLocaleString()}</span>
                                                </div>
                                                <Progress value={(project.paid_amount / project.total_amount) * 100} className="h-1" />
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-white/80 text-sm">
                                            {project.deadline ? new Date(project.deadline).toLocaleDateString() : '-'}
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" className="h-8 w-8 p-0 text-white hover:bg-white/10">
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                                    <DropdownMenuItem asChild className="focus:bg-[#3e2121] focus:text-white">
                                                        <Link href={freelance.projects.show(project.id).url}>
                                                            <Eye className="mr-2 h-4 w-4" /> Ver Detalles
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild className="focus:bg-[#3e2121] focus:text-white">
                                                        <Link href={freelance.projects.edit(project.id).url}>
                                                            <Pencil className="mr-2 h-4 w-4" /> Editar
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator className="bg-[#3e2121]" />
                                                    <DropdownMenuItem
                                                        className="text-rose-400 focus:bg-rose-500/20 focus:text-rose-400"
                                                        onClick={() => handleDelete(project.id)}
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
