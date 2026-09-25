import { Head, useForm, Link, router } from '@inertiajs/react';
import {
    Plus,
    Trash,
    ArrowLeft,
    Save,
    Calculator,
    Calendar as CalendarIcon,
    Sparkles,
    Loader2
} from 'lucide-react';
import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import MainLayout from '@/layouts/main-layout';
import { csrfHeaders } from '@/lib/csrf';
import freelance from '@/routes/freelance';

interface QuoteFormProps {
    quote?: any;
    clients: any[];
    projects: any[];
    currencies: any[];
}

export default function QuoteForm({ quote, clients, projects, currencies }: QuoteFormProps) {
    const isEditing = !!quote;
    const [aiLoading, setAiLoading] = useState(false);
    const [aiError, setAiError] = useState<string | null>(null);

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

    const handleAiAssist = async () => {
        setAiLoading(true);
        setAiError(null);

        const clientName = clients.find(c => c.id.toString() === data.client_id.toString())?.name || '';
        const projectDescription = data.items.map((item: any) => item.description).filter(Boolean).join(', ') || 'General project work';

        try {
            const response = await fetch('/ai/generate-quote', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({
                    client_name: clientName,
                    project_description: projectDescription,
                    project_type: '',
                }),
            });

            if (!response.ok) {
                throw new Error('Failed to get AI suggestions');
            }

            const result = await response.json();

            if (result.suggestion) {
                if (result.suggestion.items && Array.isArray(result.suggestion.items)) {
                    const newItems = result.suggestion.items.map((item: any, index: number) => ({
                        description: item.description || '',
                        hours: item.hours || 1,
                        hourly_rate: item.hourly_rate || 0,
                        subtotal: (item.hours || 1) * (item.hourly_rate || 0),
                        order: index + 1,
                    }));
                    setData('items', newItems);
                }
                if (result.suggestion.summary) {
                    setData('notes', result.suggestion.summary);
                }
            } else if (result.message) {
                setAiError(result.message);
            }
        } catch (err) {
            setAiError('AI service unavailable. Please try again later.');
        } finally {
            setAiLoading(false);
        }
    };

    return (
        <MainLayout>
            <Head title={isEditing ? 'Editar Cotización' : 'Nueva Cotización'} />

            <div className="flex h-full flex-col gap-6 p-4 md:p-6 max-w-4xl mx-auto w-full animate-in fade-in duration-700 pb-20">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild className="bg-[#2b1a1a] border-[#3e2121] text-[#e8b4b4] hover:bg-white/5">
                        <Link href={freelance.quotes.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight text-white">
                        {isEditing ? `Editar Cotización: ${quote.quote_number}` : 'Nueva Cotización'}
                    </h1>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={handleAiAssist}
                        disabled={aiLoading}
                        className="ml-auto bg-[#2b1a1a] border-[#3e2121] text-[#e8b4b4] hover:bg-white/5 font-bold"
                    >
                        {aiLoading ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Generating...
                            </>
                        ) : (
                            <>
                                <Sparkles className="mr-2 h-4 w-4" />
                                AI Assist
                            </>
                        )}
                    </Button>
                </div>
                {aiError && (
                    <div className="rounded-lg bg-orange-500/10 border border-orange-500/20 p-3 text-sm text-orange-400">
                        {aiError}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <Card className="md:col-span-2 bg-[#2b1a1a] border-[#3e2121] text-white">
                            <CardHeader>
                                <CardTitle className="text-[#e8b4b4] text-xs uppercase font-black tracking-widest">Items del Presupuesto</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {data.items.map((item: any, index: number) => (
                                    <div key={index} className="space-y-3 p-4 rounded-lg bg-[#1c0f0f] border border-[#3e2121]">
                                        <div className="flex justify-between items-center">
                                            <span className="text-[10px] font-black uppercase text-[#e8b4b4]">Item {index + 1}</span>
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
                                                className="bg-[#2b1a1a] border-[#3e2121]"
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
                                                    className="bg-[#2b1a1a] border-[#3e2121]"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label className="text-[10px] uppercase font-bold text-white/60">Tarifa p/h</Label>
                                                <Input
                                                    type="number"
                                                    value={item.hourly_rate}
                                                    onChange={(e) => updateItem(index, 'hourly_rate', parseFloat(e.target.value))}
                                                    className="bg-[#2b1a1a] border-[#3e2121]"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label className="text-[10px] uppercase font-bold text-[#e8b4b4]">Subtotal</Label>
                                                <div className="h-10 flex items-center px-3 rounded-md bg-[#2b1a1a] border border-[#3e2121] font-bold text-primary">
                                                    ${Number(item.subtotal).toLocaleString()}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                                <Button type="button" variant="outline" className="w-full border-dashed border-[#3e2121] text-[#e8b4b4] hover:bg-white/5" onClick={addItem}>
                                    <Plus className="mr-2 h-4 w-4" /> Agregar Item
                                </Button>
                            </CardContent>
                        </Card>

                        <div className="space-y-6">
                            <Card className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                <CardHeader>
                                    <CardTitle className="text-[#e8b4b4] text-xs uppercase font-black tracking-widest">Información</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-2">
                                        <Label className="text-white/80">Cliente *</Label>
                                        <Select value={data.client_id.toString()} onValueChange={(v) => setData('client_id', v)}>
                                            <SelectTrigger className="bg-[#1c0f0f] border-[#3e2121]">
                                                <SelectValue placeholder="Seleccionar Cliente" />
                                            </SelectTrigger>
                                            <SelectContent className="bg-[#2b1a1a] border-[#3e2121] text-white">
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
                                            <SelectTrigger className="bg-[#1c0f0f] border-[#3e2121]">
                                                <SelectValue placeholder="Moneda" />
                                            </SelectTrigger>
                                            <SelectContent className="bg-[#2b1a1a] border-[#3e2121] text-white">
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
                                            className="bg-[#1c0f0f] border-[#3e2121]"
                                        />
                                    </div>

                                    <div className="grid grid-cols-1 gap-4">
                                        <div className="space-y-2">
                                            <Label className="text-white/80">Fecha de Emisión</Label>
                                            <Input
                                                type="date"
                                                value={data.issue_date}
                                                onChange={(e) => setData('issue_date', e.target.value)}
                                                className="bg-[#1c0f0f] border-[#3e2121]"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="text-white/80">Vencimiento</Label>
                                            <Input
                                                type="date"
                                                value={data.expiry_date}
                                                onChange={(e) => setData('expiry_date', e.target.value)}
                                                className="bg-[#1c0f0f] border-[#3e2121]"
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="bg-[#1c0f0f] border-primary/20 text-white shadow-[0_0_20px_rgba(239,68,68,0.05)]">
                                <CardContent className="p-6">
                                    <div className="flex flex-col gap-2">
                                        <span className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Total Presupuestado</span>
                                        <div className="text-3xl font-black text-primary">
                                            {currencies.find(c => c.id.toString() === data.currency_id.toString())?.symbol || '$'} {data.total_amount.toLocaleString()}
                                        </div>
                                    </div>
                                    <Button type="submit" disabled={processing} className="w-full mt-6 bg-primary text-white font-black uppercase tracking-widest shadow-[0_0_15px_rgba(239,68,68,0.2)]">
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
