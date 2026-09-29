import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Upload } from 'lucide-react';
import React, { useRef, useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import PeopleLayout from '@/layouts/people-layout';
import people from '@/routes/people';

const CLOSENESS_LABELS: Record<string, string> = {
    inner_circle: 'Círculo íntimo', close: 'Cercano', friend: 'Amigo', acquaintance: 'Conocido',
};

export default function PersonForm({ person, closenessOptions, relationshipOptions, channelOptions }: any) {
    const isEditing = !!person;
    const avatarInput = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const { data, setData, post, put, processing, errors } = useForm({
        first_name: person?.first_name || '',
        last_name: person?.last_name || '',
        nickname: person?.nickname || '',
        birthday: person?.birthday?.slice(0, 10) || '',
        email: person?.email || '',
        phone: person?.phone || '',
        whatsapp: person?.whatsapp || '',
        address: person?.address || '',
        city: person?.city || '',
        country: person?.country || '',
        company: person?.company || '',
        job_title: person?.job_title || '',
        website: person?.website || '',
        how_we_met: person?.how_we_met || '',
        closeness: person?.closeness || 'friend',
        relationship_status: person?.relationship_status || '',
        preferred_contact_channel: person?.preferred_contact_channel || '',
        is_favorite: person?.is_favorite || false,
        is_archived: person?.is_archived || false,
        notes: person?.notes || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) put(people.update(person.id).url);
        else post(people.store().url);
    };

    const uploadAvatar = (file: File) => {
        const formData = new FormData();
        formData.append('avatar', file);
        setUploading(true);
        router.post(people.avatar.store(person.id).url, formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setUploading(false),
        });
    };

    return (
        <PeopleLayout>
            <Head title={isEditing ? `Editar ${person.full_name}` : 'Nueva persona'} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={isEditing ? people.show(person.id).url : people.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight text-white">
                        {isEditing ? 'Editar persona' : 'Nueva persona'}
                    </h1>
                </div>

                {isEditing && (
                    <div className="flex items-center gap-4">
                        <Avatar className="h-16 w-16">
                            {person.avatar_url && <AvatarImage src={person.avatar_url} alt={person.full_name} />}
                            <AvatarFallback className="bg-primary/20 text-primary text-xl font-black">
                                {person.first_name?.[0]?.toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <input
                            ref={avatarInput}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={(e) => e.target.files?.[0] && uploadAvatar(e.target.files[0])}
                        />
                        <Button type="button" variant="outline" disabled={uploading} onClick={() => avatarInput.current?.click()}>
                            <Upload className="mr-2 h-4 w-4" /> {uploading ? 'Subiendo...' : 'Cambiar avatar'}
                        </Button>
                        {person.avatar_url && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive"
                                onClick={() => router.delete(people.avatar.destroy(person.id).url, { preserveScroll: true })}
                            >
                                Quitar
                            </Button>
                        )}
                    </div>
                )}

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <Card className="bg-card border-border">
                        <CardHeader><CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Datos</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="first_name">Nombre *</Label>
                                <Input id="first_name" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} />
                                {errors.first_name && <p className="text-xs text-destructive">{errors.first_name}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="last_name">Apellido</Label>
                                <Input id="last_name" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="nickname">Alias</Label>
                                <Input id="nickname" value={data.nickname} onChange={(e) => setData('nickname', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="birthday">Cumpleaños</Label>
                                <Input id="birthday" type="date" value={data.birthday} onChange={(e) => setData('birthday', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Cercanía *</Label>
                                <Select value={data.closeness} onValueChange={(value) => setData('closeness', value)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {closenessOptions.map((value: string) => (
                                            <SelectItem key={value} value={value}>{CLOSENESS_LABELS[value] ?? value}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.closeness && <p className="text-xs text-destructive">{errors.closeness}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Estado de relación</Label>
                                <Select value={data.relationship_status || 'none'} onValueChange={(value) => setData('relationship_status', value === 'none' ? '' : value)}>
                                    <SelectTrigger><SelectValue placeholder="Sin dato" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin dato</SelectItem>
                                        {relationshipOptions.map((value: string) => (
                                            <SelectItem key={value} value={value}>{value.replace(/_/g, ' ')}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label>Canal preferido</Label>
                                <Select value={data.preferred_contact_channel || 'none'} onValueChange={(value) => setData('preferred_contact_channel', value === 'none' ? '' : value)}>
                                    <SelectTrigger><SelectValue placeholder="Sin dato" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin dato</SelectItem>
                                        {channelOptions.map((value: string) => (
                                            <SelectItem key={value} value={value}>{value.replace(/_/g, ' ')}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <label className="flex items-center gap-2 text-sm text-white/90">
                                <Checkbox checked={data.is_favorite} onCheckedChange={(checked) => setData('is_favorite', !!checked)} />
                                Favorito
                            </label>
                            {isEditing && (
                                <label className="flex items-center gap-2 text-sm text-white/90">
                                    <Checkbox checked={data.is_archived} onCheckedChange={(checked) => setData('is_archived', !!checked)} />
                                    Archivada
                                </label>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="bg-card border-border">
                        <CardHeader><CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Contacto</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                {errors.email && <p className="text-xs text-destructive">{errors.email}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="phone">Teléfono</Label>
                                <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="whatsapp">WhatsApp</Label>
                                <Input id="whatsapp" value={data.whatsapp} onChange={(e) => setData('whatsapp', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="website">Sitio web</Label>
                                <Input id="website" value={data.website} onChange={(e) => setData('website', e.target.value)} />
                                {errors.website && <p className="text-xs text-destructive">{errors.website}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="city">Ciudad</Label>
                                <Input id="city" value={data.city} onChange={(e) => setData('city', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="country">País</Label>
                                <Input id="country" value={data.country} onChange={(e) => setData('country', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="address">Dirección</Label>
                                <Input id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                                {errors.address && <p className="text-xs text-destructive">{errors.address}</p>}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="bg-card border-border">
                        <CardHeader><CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Contexto</CardTitle></CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="company">Empresa</Label>
                                    <Input id="company" value={data.company} onChange={(e) => setData('company', e.target.value)} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="job_title">Puesto</Label>
                                    <Input id="job_title" value={data.job_title} onChange={(e) => setData('job_title', e.target.value)} />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="how_we_met">Cómo se conocieron</Label>
                                <Textarea id="how_we_met" value={data.how_we_met} onChange={(e) => setData('how_we_met', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notas</Label>
                                <Textarea id="notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" asChild>
                            <Link href={isEditing ? people.show(person.id).url : people.index().url}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="bg-primary text-white font-bold">
                            {isEditing ? 'Guardar cambios' : 'Crear persona'}
                        </Button>
                    </div>
                </form>
            </div>
        </PeopleLayout>
    );
}
