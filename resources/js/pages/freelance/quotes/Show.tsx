import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, Printer, Copy, Briefcase, FileText, Calendar, Building, Mail } from 'lucide-react';
import React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import MainLayout from '@/layouts/main-layout';
import freelance from '@/routes/freelance';

export default function QuoteShow({ quote }: any) {
    const handleConvert = () => {
        if (confirm('¿Convertir esta cotización en un nuevo proyecto?')) {
            router.post(freelance.quotes.convert(quote.id).url);
        }
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
            <Head title={`Cotización ${quote.quote_number}`} />

            <div className="flex h-full flex-col gap-6 p-4 md:p-6 max-w-4xl mx-auto w-full animate-in fade-in duration-700 pb-20">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild className="bg-[#2b1a1a] border-[#3e2121] text-[#e8b4b4] hover:bg-white/5">
                            <Link href={freelance.quotes.index().url}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold tracking-tight text-white">{quote.quote_number}</h1>
                                {getStatusBadge(quote.status)}
                            </div>
                            <p className="text-sm text-muted-foreground flex items-center gap-2 mt-1">
                                <FileText className="h-3 w-3" /> {quote.title || 'Cotización de Servicios'}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild className="bg-[#2b1a1a] border-[#3e2121] text-[#e8b4b4] hover:bg-white/5">
                            <a href={freelance.quotes.pdf(quote.id).url} target="_blank" rel="noopener noreferrer">
                                <Download className="mr-2 h-4 w-4" /> PDF
                            </a>
                        </Button>
                        {quote.status !== 'accepted' && (
                            <Button variant="outline" onClick={handleConvert} className="bg-[#2b1a1a] border-[#3e2121] text-[#e8b4b4] hover:bg-white/5">
                                <Briefcase className="mr-2 h-4 w-4" /> Convertir a Proyecto
                            </Button>
                        )}
                        <Button asChild className="bg-primary text-white font-bold">
                            <Link href={freelance.quotes.edit(quote.id).url}>
                                Editar
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card className="bg-[#2b1a1a] border-[#3e2121] text-white overflow-hidden shadow-xl">
                    <CardHeader className="bg-[#1c0f0f] border-b border-[#3e2121] p-8">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div className="space-y-4">
                                <div>
                                    <p className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4] mb-2">Cliente</p>
                                    <p className="text-xl font-bold text-white flex items-center gap-2">
                                        <Building className="h-4 w-4 text-primary" /> {quote.client?.name}
                                    </p>
                                    <p className="text-sm text-white/60 ml-6">{quote.client?.company}</p>
                                    <p className="text-sm text-white/60 ml-6 flex items-center gap-2 mt-1">
                                        <Mail className="h-3 w-3" /> {quote.client?.email}
                                    </p>
                                </div>
                            </div>
                            <div className="md:text-right space-y-4">
                                <div>
                                    <p className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4] mb-2">Detalles del Presupuesto</p>
                                    <div className="space-y-1 text-sm">
                                        <p className="text-white/80"><span className="text-white/40 font-medium">Fecha:</span> {new Date(quote.issue_date).toLocaleDateString()}</p>
                                        {quote.expiry_date && (
                                            <p className="text-white/80"><span className="text-white/40 font-medium">Válido hasta:</span> {new Date(quote.expiry_date).toLocaleDateString()}</p>
                                        )}
                                        <p className="text-white/80"><span className="text-white/40 font-medium">Moneda:</span> {quote.currency?.code}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader className="bg-[#1c0f0f]/50">
                                <TableRow className="hover:bg-transparent border-[#3e2121]">
                                    <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest h-12">Descripción</TableHead>
                                    <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest text-center h-12">Horas</TableHead>
                                    <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest text-right h-12">Tarifa</TableHead>
                                    <TableHead className="text-[#e8b4b4] font-black uppercase text-[10px] tracking-widest text-right h-12">Subtotal</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {quote.items.map((item: any, index: number) => (
                                    <TableRow key={index} className="hover:bg-white/5 border-[#3e2121]">
                                        <TableCell className="text-white/90 py-4 font-medium">{item.description}</TableCell>
                                        <TableCell className="text-center text-white/70">{item.hours || '-'}</TableCell>
                                        <TableCell className="text-right text-white/70">{quote.currency?.symbol} {Number(item.hourly_rate).toLocaleString()}</TableCell>
                                        <TableCell className="text-right font-bold text-primary">
                                            {quote.currency?.symbol} {Number(item.subtotal).toLocaleString()}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                    <CardFooter className="flex flex-col items-end gap-2 border-t border-[#3e2121] bg-[#1c0f0f] p-8">
                        <div className="flex justify-between items-center w-full md:w-1/2">
                            <span className="text-sm font-black uppercase tracking-widest text-[#e8b4b4]">Total Presupuestado</span>
                            <span className="text-3xl font-black text-primary">
                                {quote.currency?.symbol} {Number(quote.total_amount).toLocaleString()}
                            </span>
                        </div>
                    </CardFooter>
                </Card>

                {(quote.notes || quote.terms_and_conditions) && (
                    <div className="grid gap-6 md:grid-cols-2">
                        {quote.notes && (
                            <Card className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                <CardHeader>
                                    <CardTitle className="text-[#e8b4b4] text-xs uppercase font-black tracking-widest">Notas</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-white/70 whitespace-pre-wrap">{quote.notes}</p>
                                </CardContent>
                            </Card>
                        )}
                        {quote.terms_and_conditions && (
                            <Card className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                <CardHeader>
                                    <CardTitle className="text-[#e8b4b4] text-xs uppercase font-black tracking-widest">Términos y Condiciones</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-white/70 whitespace-pre-wrap">{quote.terms_and_conditions}</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}
            </div>
        </MainLayout>
    );
}
