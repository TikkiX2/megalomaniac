import React from 'react';
import MainLayout from '@/layouts/main-layout';
import freelance from '@/routes/freelance';
import { Head, useForm, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { ArrowLeft, Save } from 'lucide-react';

interface ClientFormProps {
    client?: any;
}

export default function ClientForm({ client }: ClientFormProps) {
    const isEditing = !!client;

    const { data, setData, post, put, processing, errors } = useForm({
        name: client?.name || '',
        email: client?.email || '',
        phone: client?.phone || '',
        company: client?.company || '',
        address: client?.address || '',
        tax_id: client?.tax_id || '',
        notes: client?.notes || '',
        is_active: client?.is_active ?? true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) {
            put(freelance.clients.update(client.id).url);
        } else {
            post(freelance.clients.store().url);
        }
    };

    return (
        <MainLayout>
            <Head title={isEditing ? 'Editar Cliente' : 'Nuevo Cliente'} />

            <div className="flex h-full flex-col gap-6 p-4 md:p-6 max-w-2xl mx-auto w-full animate-in fade-in duration-700">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild className="bg-[#2b1a1a] border-[#3e2121] text-[#e8b4b4] hover:bg-white/5">
                        <Link href={freelance.clients.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">
                            {isEditing ? `Editar: ${client.name}` : 'Nuevo Cliente'}
                        </h1>
                    </div>
                </div>

                <form onSubmit={submit}>
                    <Card className="bg-[#2b1a1a] border-[#3e2121] text-white">
                        <CardHeader>
                            <CardTitle className="text-[#e8b4b4] text-xs uppercase font-black tracking-widest">Información Básica</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name" className="text-white/80">Nombre Completo *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121]"
                                    placeholder="Juan Pérez"
                                />
                                {errors.name && <p className="text-sm text-rose-400">{errors.name}</p>}
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="email" className="text-white/80">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="bg-[#1c0f0f] border-[#3e2121]"
                                    />
                                    {errors.email && <p className="text-sm text-rose-400">{errors.email}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="phone" className="text-white/80">Teléfono</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        className="bg-[#1c0f0f] border-[#3e2121]"
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="company" className="text-white/80">Empresa</Label>
                                <Input
                                    id="company"
                                    value={data.company}
                                    onChange={(e) => setData('company', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121]"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="tax_id" className="text-white/80">Identificación Fiscal (RIT/CUIT/NIT)</Label>
                                <Input
                                    id="tax_id"
                                    value={data.tax_id}
                                    onChange={(e) => setData('tax_id', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121]"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="address" className="text-white/80">Dirección</Label>
                                <Textarea
                                    id="address"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121]"
                                    rows={2}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="notes" className="text-white/80">Notas Adicionales</Label>
                                <Textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121]"
                                    rows={3}
                                />
                            </div>
                        </CardContent>
                        <CardFooter className="flex justify-end gap-2 border-t border-[#3e2121] pt-6">
                            <Button variant="ghost" type="button" asChild className="text-[#e8b4b4] hover:bg-white/5">
                                <Link href={freelance.clients.index().url}>Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing} className="bg-primary text-white font-bold">
                                <Save className="mr-2 h-4 w-4" />
                                {isEditing ? 'Actualizar Cliente' : 'Guardar Cliente'}
                            </Button>
                        </CardFooter>
                    </Card>
                </form>
            </div>
        </MainLayout>
    );
}
