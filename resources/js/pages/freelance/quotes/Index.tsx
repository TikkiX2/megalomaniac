import { Head, Link, router } from '@inertiajs/react';
import {
    Plus,
    Search,
    MoreHorizontal,
    Eye,
    Pencil,
    Trash,
    FileDown,
    Copy
} from 'lucide-react';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
} from '@/components/ui/table';
import MainLayout from '@/layouts/main-layout';
import freelance from '@/routes/freelance';

export default function QuotesIndex({ quotes, filters }: any) {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (value: string) => {
        setSearch(value);
        router.get(
            freelance.quotes.index().url,
            { search: value },
            { preserveState: true, replace: true }
        );
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Estás seguro de eliminar esta cotización?')) {
            router.delete(freelance.quotes.destroy(id).url);
        }
    };

    const handleDuplicate = (id: number) => {
        router.post(freelance.quotes.duplicate(id).url);
    };

    const getStatusBadge = (status: string) => {
        const styles: Record<string, string> = {
            'draft': 'bg-gray-500/20 text-gray-500',
            'sent': 'bg-blue-500/20 text-blue-500',
            'accepted': 'bg-primary/20 text-primary',
            'rejected': 'bg-rose-500/20 text-rose-500',
            'expired': 'bg-orange-500/20 text-orange-500',
        };
        return <Badge variant="outline" className={`border-0 font-black uppercase tracking-tighter text-[10px] ${styles[status] || ''}`}>{status}</Badge>;
    };

    return (
        <MainLayout>
            <Head title="Cotizaciones" />

            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Cotizaciones</h1>
                        <p className="text-muted-foreground">Crea y gestiona presupuestos para tus prospectos.</p>
                    </div>
                    <Button asChild className="bg-primary text-white font-bold">
                        <Link href={freelance.quotes.create().url}>
                            <Plus className="mr-2 h-4 w-4" /> Nueva Cotización
                        </Link>
                    </Button>
                </div>

                <div className="flex items-center gap-2">
                    <div className="relative flex-1 md:max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder="Buscar por número o cliente..."
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
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Número</TableHead>
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Cliente</TableHead>
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Fecha</TableHead>
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Vence</TableHead>
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Total</TableHead>
                                <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest">Estado</TableHead>
                                <TableHead className="w-[80px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {quotes.data.length === 0 ? (
                                <TableRow className="hover:bg-transparent border-[#3e2121]">
                                    <TableCell colSpan={7} className="text-center h-24 text-muted-foreground italic">
                                        No se encontraron cotizaciones.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                quotes.data.map((quote: any) => (
                                    <TableRow key={quote.id} className="hover:bg-white/5 border-[#3e2121]">
                                        <TableCell className="font-bold text-white">{quote.quote_number}</TableCell>
                                        <TableCell className="text-white/80">{quote.client?.name}</TableCell>
                                        <TableCell className="text-white/80 text-sm">{new Date(quote.issue_date).toLocaleDateString()}</TableCell>
                                        <TableCell className="text-white/80 text-sm">{quote.expiry_date ? new Date(quote.expiry_date).toLocaleDateString() : '-'}</TableCell>
                                        <TableCell className="font-bold text-primary">
                                            {quote.currency?.symbol} {Number(quote.total_amount).toLocaleString()}
                                        </TableCell>
                                        <TableCell>{getStatusBadge(quote.status)}</TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" className="h-8 w-8 p-0 text-white hover:bg-white/10">
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                                    <DropdownMenuItem asChild className="focus:bg-[#3e2121] focus:text-white">
                                                        <Link href={freelance.quotes.show(quote.id).url}>
                                                            <Eye className="mr-2 h-4 w-4" /> Ver
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild className="focus:bg-[#3e2121] focus:text-white">
                                                        <Link href={freelance.quotes.edit(quote.id).url}>
                                                            <Pencil className="mr-2 h-4 w-4" /> Editar
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        className="focus:bg-[#3e2121] focus:text-white"
                                                        onClick={() => handleDuplicate(quote.id)}
                                                    >
                                                        <Copy className="mr-2 h-4 w-4" /> Duplicar
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild className="focus:bg-[#3e2121] focus:text-white">
                                                        <a href={freelance.quotes.pdf(quote.id).url} target="_blank">
                                                            <FileDown className="mr-2 h-4 w-4" /> Descargar PDF
                                                        </a>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator className="bg-[#3e2121]" />
                                                    <DropdownMenuItem
                                                        className="text-rose-400 focus:bg-rose-500/20 focus:text-rose-400"
                                                        onClick={() => handleDelete(quote.id)}
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
