import React, { useState, useEffect } from 'react';
import MainLayout from '@/layouts/main-layout';
import freelance from '@/routes/freelance';
import { Head, useForm, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue
} from '@/components/ui/select';
import {
    Plus,
    Trash,
    ArrowLeft,
    Save,
    Calculator,
    Calendar as CalendarIcon
} from 'lucide-react';
import { Separator } from '@/components/ui/separator';

interface QuoteFormProps {
    quote?: any;
    clients: any[];
    projects: any[];
    currencies: any[];
}

export default function QuoteForm({ quote, clients, projects, currencies }: QuoteFormProps) {
    const isEditing = !!quote;

    const { data, setData, post, put, processing, errors } = useForm({
        client_id: quote?.client_id || '',
        project_id: quote?.project_id || '',
        currency_id: quote?.currency_id || (currencies.length > 0 ? currencies[0].id : ''),
        quote_number: quote?.quote_number || `COT-${new Date().getFullYear()}-${Math.floor(1000 + Math.random() * 9000)}`,
        status: quote?.status || 'draft',
        issue_date: quote?.issue_date || new Date().toISOString().split('T')[0],
        expiry_date: quote?.expiry_date || '',
        notes: quote?.notes || '',
        items: quote?.items || [
            { description: '', hours: 1, hourly_rate: 0, subtotal: 0, order: 1 }
        ],
        total_amount: quote?.total_amount || 0,
    });

    const addItem = () => {
        setData('items', [
            ...data.items,
            { description: '', hours: 1, hourly_rate: 0, subtotal: 0, order: data.items.length + 1 }
        ]);
    };

    const removeItem = (index: number) => {
        const newItems = [...data.items];
        newItems.splice(index, 1);
        setData('items', newItems);
    };

    const updateItem = (index: number, field: string, value: any) => {
        const newItems = [...data.items];
        newItems[index] = { ...newItems[index], [field]: value };

        if (field === 'hours' || field === 'hourly_rate') {
            newItems[index].subtotal = (newItems[index].hours || 0) * (newItems[index].hourly_rate || 0);
        }

        setData('items', newItems);
    };

    useEffect(() => {
        const total = data.items.reduce((sum: number, item: any) => sum + (Number(item.subtotal) || 0), 0);
        setData('total_amount', total);
    }, [data.items]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) {
            put(freelance.quotes.update(quote.id).url);
        } else {
            post(freelance.quotes.store().url);
        }
    };

    return (
        <MainLayout>
            <Head title={isEditing ? 'Editar Cotización' : 'Nueva Cotización'} />

            <div className="flex h-full flex-col gap-6 p-4 md:p-6 max-w-4xl mx-auto w-full animate-in fade-in duration-700 pb-20">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild className="bg-[#193322] border-[#23482f] text-[#92c9a4] hover:bg-white/5">
                        <Link href={freelance.quotes.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight text-white">
                        {isEditing ? `Editar Cotización: ${quote.quote_number}` : 'Nueva Cotización'}
                    </h1>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <Card className="md:col-span-2 bg-[#193322] border-[#23482f] text-white">
                            <CardHeader>
                                <CardTitle className="text-[#92c9a4] text-xs uppercase font-black tracking-widest">Items del Presupuesto</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {data.items.map((item: any, index: number) => (
                                    <div key={index} className="space-y-3 p-4 rounded-lg bg-[#102216] border border-[#23482f]">
                                        <div className="flex justify-between items-center">
                                            <span className="text-[10px] font-black uppercase text-[#92c9a4]">Item {index + 1}</span>
                                            {data.items.length > 1 && (
                                                <Button type="button" variant="ghost" size="icon" className="h-6 w-6 text-rose-400 hover:bg-rose-500/10" onClick={() => removeItem(index)}>
                                                    <Trash className="h-3.5 w-3.5" />
                                                </Button>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="text-[10px] uppercase font-bold text-white/60">Descripción</Label>
                                            <Input
                                                value={item.description}
                                                onChange={(e) => updateItem(index, 'description', e.target.value)}
                                                className="bg-[#193322] border-[#23482f]"
                                                placeholder="Ej: Desarrollo de Landing Page"
                                            />
                                        </div>
                                        <div className="grid grid-cols-3 gap-4">
                                            <div className="space-y-2">
                                                <Label className="text-[10px] uppercase font-bold text-white/60">Horas</Label>
                                                <Input
                                                    type="number"
                                                    value={item.hours}
                                                    onChange={(e) => updateItem(index, 'hours', parseFloat(e.target.value))}
                                                    className="bg-[#193322] border-[#23482f]"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label className="text-[10px] uppercase font-bold text-white/60">Tarifa p/h</Label>
                                                <Input
                                                    type="number"
                                                    value={item.hourly_rate}
                                                    onChange={(e) => updateItem(index, 'hourly_rate', parseFloat(e.target.value))}
                                                    className="bg-[#193322] border-[#23482f]"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label className="text-[10px] uppercase font-bold text-[#92c9a4]">Subtotal</Label>
                                                <div className="h-10 flex items-center px-3 rounded-md bg-[#193322] border border-[#23482f] font-bold text-primary">
                                                    ${Number(item.subtotal).toLocaleString()}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                                <Button type="button" variant="outline" className="w-full border-dashed border-[#23482f] text-[#92c9a4] hover:bg-white/5" onClick={addItem}>
                                    <Plus className="mr-2 h-4 w-4" /> Agregar Item
                                </Button>
                            </CardContent>
                        </Card>

                        <div className="space-y-6">
                            <Card className="bg-[#193322] border-[#23482f] text-white">
                                <CardHeader>
                                    <CardTitle className="text-[#92c9a4] text-xs uppercase font-black tracking-widest">Información</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-2">
                                        <Label className="text-white/80">Cliente *</Label>
                                        <Select value={data.client_id.toString()} onValueChange={(v) => setData('client_id', v)}>
                                            <SelectTrigger className="bg-[#102216] border-[#23482f]">
                                                <SelectValue placeholder="Seleccionar Cliente" />
                                            </SelectTrigger>
                                            <SelectContent className="bg-[#193322] border-[#23482f] text-white">
                                                {clients.map(c => (
                                                    <SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.client_id && <p className="text-xs text-rose-400">{errors.client_id}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label className="text-white/80">Moneda *</Label>
                                        <Select value={data.currency_id.toString()} onValueChange={(v) => setData('currency_id', v)}>
                                            <SelectTrigger className="bg-[#102216] border-[#23482f]">
                                                <SelectValue placeholder="Moneda" />
                                            </SelectTrigger>
                                            <SelectContent className="bg-[#193322] border-[#23482f] text-white">
                                                {currencies.map(c => (
                                                    <SelectItem key={c.id} value={c.id.toString()}>{c.code} ({c.symbol})</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label className="text-white/80">Nro Cotización</Label>
                                        <Input
                                            value={data.quote_number}
                                            onChange={(e) => setData('quote_number', e.target.value)}
                                            className="bg-[#102216] border-[#23482f]"
                                        />
                                    </div>

                                    <div className="grid grid-cols-1 gap-4">
                                        <div className="space-y-2">
                                            <Label className="text-white/80">Fecha de Emisión</Label>
                                            <Input
                                                type="date"
                                                value={data.issue_date}
                                                onChange={(e) => setData('issue_date', e.target.value)}
                                                className="bg-[#102216] border-[#23482f]"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="text-white/80">Vencimiento</Label>
                                            <Input
                                                type="date"
                                                value={data.expiry_date}
                                                onChange={(e) => setData('expiry_date', e.target.value)}
                                                className="bg-[#102216] border-[#23482f]"
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="bg-[#102216] border-primary/20 text-white shadow-[0_0_20px_rgba(19,236,91,0.05)]">
                                <CardContent className="p-6">
                                    <div className="flex flex-col gap-2">
                                        <span className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Total Presupuestado</span>
                                        <div className="text-3xl font-black text-primary">
                                            {currencies.find(c => c.id.toString() === data.currency_id.toString())?.symbol || '$'} {data.total_amount.toLocaleString()}
                                        </div>
                                    </div>
                                    <Button type="submit" disabled={processing} className="w-full mt-6 bg-primary text-[#102216] font-black uppercase tracking-widest shadow-[0_0_15px_rgba(19,236,91,0.2)]">
                                        <Save className="mr-2 h-4 w-4" />
                                        {isEditing ? 'Actualizar' : 'Guardar'}
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </form>
            </div>
        </MainLayout>
    );
}
