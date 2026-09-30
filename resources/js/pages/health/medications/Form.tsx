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

export default function MedicationForm({ medication: medicationProp, conditions, providers, people }: any) {
    const medication = medicationProp ?? {};
    const isEditing = !!medicationProp;

    const { data, setData, post, put, processing, errors } = useForm({
        name: medication.name || '',
        dose_amount: medication.dose_amount ?? '',
        dose_unit: medication.dose_unit || '',
        route: medication.route || '',
        frequency_text: medication.frequency_text || '',
        started_at: medication.started_at ? String(medication.started_at).slice(0, 10) : '',
        ended_at: medication.ended_at ? String(medication.ended_at).slice(0, 10) : '',
        is_active: medication.is_active ?? true,
        condition_id: medication.condition_id ?? '',
        prescriber_id: medication.prescriber_id ?? '',
        person_id: medication.person_id ?? '',
        notes: medication.notes || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) put(health.medications.update(medication.id).url);
        else post(health.medications.store().url);
    };

    return (
        <HealthLayout>
            <Head title={isEditing ? `Editar ${medication.name}` : 'Nueva medicación'} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={health.medications.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight text-white">
                        {isEditing ? 'Editar medicación' : 'Nueva medicación'}
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
                                <Label htmlFor="dose_amount">Dosis</Label>
                                <Input id="dose_amount" type="number" step="0.01" min="0" value={data.dose_amount} onChange={(e) => setData('dose_amount', e.target.value)} />
                                {errors.dose_amount && <p className="text-xs text-destructive">{errors.dose_amount}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="dose_unit">Unidad</Label>
                                <Input id="dose_unit" placeholder="mcg, mg, ml..." value={data.dose_unit} onChange={(e) => setData('dose_unit', e.target.value)} />
                                {errors.dose_unit && <p className="text-xs text-destructive">{errors.dose_unit}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="route">Vía</Label>
                                <Input id="route" placeholder="oral, subcutánea..." value={data.route} onChange={(e) => setData('route', e.target.value)} />
                                {errors.route && <p className="text-xs text-destructive">{errors.route}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="frequency_text">Frecuencia</Label>
                                <Input id="frequency_text" placeholder="cada 24 h, 1-0-0..." value={data.frequency_text} onChange={(e) => setData('frequency_text', e.target.value)} />
                                {errors.frequency_text && <p className="text-xs text-destructive">{errors.frequency_text}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="started_at">Inicio</Label>
                                <Input id="started_at" type="date" value={data.started_at} onChange={(e) => setData('started_at', e.target.value)} />
                                {errors.started_at && <p className="text-xs text-destructive">{errors.started_at}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="ended_at">Fin</Label>
                                <Input id="ended_at" type="date" value={data.ended_at} onChange={(e) => setData('ended_at', e.target.value)} />
                                {errors.ended_at && <p className="text-xs text-destructive">{errors.ended_at}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Condición</Label>
                                <Select value={data.condition_id === '' ? 'none' : String(data.condition_id)} onValueChange={(value) => setData('condition_id', value === 'none' ? '' : value)}>
                                    <SelectTrigger><SelectValue placeholder="Sin condición" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin condición</SelectItem>
                                        {conditions.map((condition: any) => (
                                            <SelectItem key={condition.id} value={String(condition.id)}>{condition.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.condition_id && <p className="text-xs text-destructive">{errors.condition_id}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Profesional prescriptor</Label>
                                <Select value={data.prescriber_id === '' ? 'none' : String(data.prescriber_id)} onValueChange={(value) => setData('prescriber_id', value === 'none' ? '' : value)}>
                                    <SelectTrigger><SelectValue placeholder="Sin profesional" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin profesional</SelectItem>
                                        {providers.map((provider: any) => (
                                            <SelectItem key={provider.id} value={String(provider.id)}>{provider.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.prescriber_id && <p className="text-xs text-destructive">{errors.prescriber_id}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Persona</Label>
                                <Select value={data.person_id === '' ? 'none' : String(data.person_id)} onValueChange={(value) => setData('person_id', value === 'none' ? '' : value)}>
                                    <SelectTrigger><SelectValue placeholder="Sin vincular" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin vincular</SelectItem>
                                        {people.map((person: any) => (
                                            <SelectItem key={person.id} value={String(person.id)}>
                                                {`${person.first_name ?? ''} ${person.last_name ?? ''}`.trim()}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.person_id && <p className="text-xs text-destructive">{errors.person_id}</p>}
                            </div>
                            <div className="flex items-center gap-2 md:col-span-2">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked === true)}
                                />
                                <Label htmlFor="is_active">Medicación activa</Label>
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
                            <Link href={health.medications.index().url}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="bg-primary text-white font-bold">
                            {isEditing ? 'Guardar cambios' : 'Crear medicación'}
                        </Button>
                    </div>
                </form>
            </div>
        </HealthLayout>
    );
}
