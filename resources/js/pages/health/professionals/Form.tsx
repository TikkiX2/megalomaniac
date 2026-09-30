import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import React from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import HealthLayout from '@/layouts/health-layout';
import health from '@/routes/health';

const TYPE_LABELS: Record<string, string> = {
    professional: 'Profesional',
    center: 'Centro',
};

export default function ProfessionalForm({ professional: professionalProp, typeOptions }: any) {
    const professional = professionalProp ?? {};
    const isEditing = !!professionalProp;

    const { data, setData, post, put, processing, errors } = useForm({
        type: professional.type || 'professional',
        name: professional.name || '',
        specialty: professional.specialty || '',
        phone: professional.phone || '',
        email: professional.email || '',
        address: professional.address || '',
        is_active: professional.is_active ?? true,
        notes: professional.notes || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) put(health.professionals.update(professional.id).url);
        else post(health.professionals.store().url);
    };

    return (
        <HealthLayout>
            <Head title={isEditing ? `Editar ${professional.name}` : 'Nuevo profesional'} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={health.professionals.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight text-white">
                        {isEditing ? 'Editar profesional' : 'Nuevo profesional'}
                    </h1>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <Card className="bg-card border-border">
                        <CardHeader><CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Datos</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="name">Nombre *</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Tipo *</Label>
                                <Select value={data.type} onValueChange={(value) => setData('type', value)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {typeOptions.map((value: string) => (
                                            <SelectItem key={value} value={value}>{TYPE_LABELS[value] ?? value}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.type && <p className="text-xs text-destructive">{errors.type}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="specialty">Especialidad</Label>
                                <Input id="specialty" value={data.specialty} onChange={(e) => setData('specialty', e.target.value)} />
                                {errors.specialty && <p className="text-xs text-destructive">{errors.specialty}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="phone">Teléfono</Label>
                                <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                                {errors.phone && <p className="text-xs text-destructive">{errors.phone}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                {errors.email && <p className="text-xs text-destructive">{errors.email}</p>}
                            </div>
                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="address">Dirección</Label>
                                <Input id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                                {errors.address && <p className="text-xs text-destructive">{errors.address}</p>}
                            </div>
                            <div className="flex items-center gap-2 md:col-span-2">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked === true)}
                                />
                                <Label htmlFor="is_active">Activo</Label>
                                {errors.is_active && <p className="text-xs text-destructive">{errors.is_active}</p>}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="bg-card border-border">
                        <CardHeader><CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Notas</CardTitle></CardHeader>
                        <CardContent>
                            <Textarea id="notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            {errors.notes && <p className="text-xs text-destructive">{errors.notes}</p>}
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" asChild>
                            <Link href={health.professionals.index().url}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="bg-primary text-white font-bold">
                            {isEditing ? 'Guardar cambios' : 'Crear profesional'}
                        </Button>
                    </div>
                </form>
            </div>
        </HealthLayout>
    );
}
