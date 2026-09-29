import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Cake, MessageCircle, Pencil, Plus, Star, Trash } from 'lucide-react';
import React, { useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import PeopleLayout from '@/layouts/people-layout';
import people from '@/routes/people';

const CLOSENESS_LABELS: Record<string, string> = {
    inner_circle: 'Círculo íntimo',
    close: 'Cercano',
    friend: 'Amigo',
    acquaintance: 'Conocido',
};

const localDateTimeInput = () => {
    const now = new Date();
    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
};

export default function PersonShow({ person, interactions, upcoming, channelOptions }: any) {
    const quickLog = () => {
        router.post(people.contacted(person.id).url, {}, { preserveScroll: true });
    };

    const [logOpen, setLogOpen] = useState(false);
    const form = useForm({
        channel: 'message',
        occurred_at: localDateTimeInput(),
        title: '',
        notes: '',
    });
    const openLogDialog = (open: boolean) => {
        if (open) {
            form.setData('occurred_at', localDateTimeInput());
        }
        setLogOpen(open);
    };
    const submitInteraction = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, occurred_at: new Date(data.occurred_at).toISOString() }));
        form.post(people.interactions.store(person.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                form.setData('occurred_at', localDateTimeInput());
                setLogOpen(false);
            },
        });
    };

    const [keyDateOpen, setKeyDateOpen] = useState(false);
    const keyDateForm = useForm({
        type: 'custom',
        label: '',
        date: '',
        remind_days_before: '7',
        is_recurring_annually: true,
    });
    const submitKeyDate = (e: React.FormEvent) => {
        e.preventDefault();
        keyDateForm.post(people.keyDates.store(person.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                keyDateForm.reset();
                setKeyDateOpen(false);
            },
        });
    };

    return (
        <PeopleLayout>
            <Head title={person.full_name} />
            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href={people.index().url}><ArrowLeft className="h-4 w-4" /></Link>
                        </Button>
                        <Avatar className="h-16 w-16">
                            {person.avatar_url && <AvatarImage src={person.avatar_url} alt={person.full_name} />}
                            <AvatarFallback className="bg-primary/20 text-primary text-xl font-black">
                                {person.first_name?.[0]?.toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight text-white">
                                {person.full_name}
                                {person.is_favorite && <Star className="h-4 w-4 fill-primary text-primary" />}
                            </h1>
                            <div className="mt-1 flex items-center gap-2">
                                <Badge variant="outline" className="border-primary/30 text-primary text-[10px] uppercase font-black">
                                    {CLOSENESS_LABELS[person.closeness] ?? person.closeness}
                                </Badge>
                                {person.nickname && <span className="text-sm text-muted-foreground">“{person.nickname}”</span>}
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button onClick={quickLog} className="bg-primary text-white font-bold">
                            <MessageCircle className="mr-2 h-4 w-4" /> Registrar contacto
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={people.edit(person.id).url}><Pencil className="mr-2 h-4 w-4" /> Editar</Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="bg-card border-border lg:col-span-1">
                        <CardHeader><CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Perfil</CardTitle></CardHeader>
                        <CardContent className="space-y-2 text-sm text-white/90">
                            {person.birthday && <p><span className="text-muted-foreground">Cumpleaños:</span> {person.birthday.slice(0, 10)}</p>}
                            {person.email && <p><span className="text-muted-foreground">Email:</span> {person.email}</p>}
                            {person.phone && <p><span className="text-muted-foreground">Teléfono:</span> {person.phone}</p>}
                            {person.whatsapp && <p><span className="text-muted-foreground">WhatsApp:</span> {person.whatsapp}</p>}
                            {person.company && <p><span className="text-muted-foreground">Empresa:</span> {person.company}{person.job_title ? ` · ${person.job_title}` : ''}</p>}
                            {person.website && <p><span className="text-muted-foreground">Web:</span> {person.website}</p>}
                            {(person.city || person.country) && <p><span className="text-muted-foreground">Ubicación:</span> {[person.city, person.country].filter(Boolean).join(', ')}</p>}
                            {person.preferred_contact_channel && <p><span className="text-muted-foreground">Canal preferido:</span> {person.preferred_contact_channel}</p>}
                            {person.how_we_met && <p><span className="text-muted-foreground">Cómo se conocieron:</span> {person.how_we_met}</p>}
                            {person.notes && <p className="whitespace-pre-line"><span className="text-muted-foreground">Notas:</span> {person.notes}</p>}
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4 lg:col-span-2">
                        <Card className="bg-card border-border">
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Fechas clave</CardTitle>
                                <Button size="sm" variant="outline" onClick={() => setKeyDateOpen(true)}>
                                    <Plus className="mr-2 h-4 w-4" /> Agregar
                                </Button>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-2">
                                {person.key_dates.length === 0 ? (
                                    <p className="py-4 text-center text-sm text-muted-foreground italic">Sin fechas clave.</p>
                                ) : person.key_dates.map((keyDate: any) => (
                                    <div key={keyDate.id} className="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-white/5">
                                        <Cake className="h-4 w-4 text-primary" />
                                        <div className="flex-1">
                                            <p className="text-sm font-medium text-white">{keyDate.label || keyDate.type.replace(/_/g, ' ')}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {keyDate.date.slice(0, 10)} · avisa {keyDate.remind_days_before} días antes
                                                {keyDate.is_recurring_annually ? ' · cada año' : ''}
                                            </p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-7 w-7 text-destructive"
                                            onClick={() => router.delete(people.keyDates.destroy(keyDate.id).url, { preserveScroll: true })}
                                        >
                                            <Trash className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                ))}
                            </CardContent>

                            <Dialog open={keyDateOpen} onOpenChange={setKeyDateOpen}>
                                <DialogContent className="bg-card border-border">
                                    <DialogHeader><DialogTitle>Nueva fecha clave</DialogTitle></DialogHeader>
                                    <form onSubmit={submitKeyDate} className="flex flex-col gap-4">
                                        <div className="grid gap-2">
                                            <Label>Tipo</Label>
                                            <Select value={keyDateForm.data.type} onValueChange={(value) => keyDateForm.setData('type', value)}>
                                                <SelectTrigger><SelectValue /></SelectTrigger>
                                                <SelectContent>
                                                    {['birthday', 'anniversary', 'graduation', 'memorial', 'custom'].map((value) => (
                                                        <SelectItem key={value} value={value}>{value}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="kd_label">Etiqueta</Label>
                                            <Input id="kd_label" value={keyDateForm.data.label} onChange={(e) => keyDateForm.setData('label', e.target.value)} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="kd_date">Fecha</Label>
                                            <Input id="kd_date" type="date" value={keyDateForm.data.date} onChange={(e) => keyDateForm.setData('date', e.target.value)} />
                                            {keyDateForm.errors.date && <p className="text-xs text-destructive">{keyDateForm.errors.date}</p>}
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="kd_remind">Días de aviso</Label>
                                            <Input id="kd_remind" type="number" min={0} max={90} value={keyDateForm.data.remind_days_before}
                                                onChange={(e) => keyDateForm.setData('remind_days_before', e.target.value)} />
                                        </div>
                                        <label className="flex items-center gap-2 text-sm text-white/90">
                                            <Checkbox checked={keyDateForm.data.is_recurring_annually}
                                                onCheckedChange={(checked) => keyDateForm.setData('is_recurring_annually', !!checked)} />
                                            Se repite cada año
                                        </label>
                                        <Button type="submit" disabled={keyDateForm.processing} className="bg-primary text-white font-bold">Guardar</Button>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </Card>
                        {/* Task 8 inserta aquí la sección de redes. */}
                        <Card className="bg-card border-border">
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">
                                    Historial de contacto
                                </CardTitle>
                                <Dialog open={logOpen} onOpenChange={openLogDialog}>
                                    <DialogTrigger asChild>
                                        <Button size="sm" variant="outline"><Plus className="mr-2 h-4 w-4" /> Registrar</Button>
                                    </DialogTrigger>
                                    <DialogContent className="bg-card border-border">
                                        <DialogHeader><DialogTitle>Registrar interacción</DialogTitle></DialogHeader>
                                        <form onSubmit={submitInteraction} className="flex flex-col gap-4">
                                            <div className="grid gap-2">
                                                <Label>Canal</Label>
                                                <Select value={form.data.channel} onValueChange={(value) => form.setData('channel', value)}>
                                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                                    <SelectContent>
                                                        {channelOptions.map((value: string) => (
                                                            <SelectItem key={value} value={value}>{value.replace(/_/g, ' ')}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {form.errors.channel && <p className="text-xs text-destructive">{form.errors.channel}</p>}
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="occurred_at">Fecha y hora</Label>
                                                <Input
                                                    id="occurred_at"
                                                    type="datetime-local"
                                                    required
                                                    value={form.data.occurred_at}
                                                    onChange={(e) => form.setData('occurred_at', e.target.value)}
                                                />
                                                {form.errors.occurred_at && <p className="text-xs text-destructive">{form.errors.occurred_at}</p>}
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="title">Título</Label>
                                                <Input id="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="notes">Notas</Label>
                                                <Textarea id="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                                            </div>
                                            <Button type="submit" disabled={form.processing} className="bg-primary text-white font-bold">Guardar</Button>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-1">
                                {interactions.data.length === 0 ? (
                                    <p className="py-6 text-center text-sm text-muted-foreground italic">Todavía no hay interacciones.</p>
                                ) : interactions.data.map((item: any) => (
                                    <div key={item.id} className="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-white/5">
                                        <MessageCircle className="h-4 w-4 text-primary" />
                                        <div className="flex-1">
                                            <p className="text-sm font-medium text-white">{item.title || 'Interacción'}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {new Date(item.occurred_at).toLocaleDateString('es-ES')} · {item.channel.replace(/_/g, ' ')}
                                            </p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-7 w-7 text-destructive"
                                            onClick={() => router.delete(people.interactions.destroy(item.id).url, { preserveScroll: true })}
                                        >
                                            <Trash className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                ))}
                                {(interactions.prev_page_url || interactions.next_page_url) && (
                                    <div className="flex justify-end gap-3 pt-2 text-xs">
                                        {interactions.prev_page_url && <Link className="text-primary" href={interactions.prev_page_url}>Anterior</Link>}
                                        {interactions.next_page_url && <Link className="text-primary" href={interactions.next_page_url}>Siguiente</Link>}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </PeopleLayout>
    );
}
