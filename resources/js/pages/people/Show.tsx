import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, MessageCircle, Pencil, Star } from 'lucide-react';
import React from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PeopleLayout from '@/layouts/people-layout';
import people from '@/routes/people';

const CLOSENESS_LABELS: Record<string, string> = {
    inner_circle: 'Círculo íntimo',
    close: 'Cercano',
    friend: 'Amigo',
    acquaintance: 'Conocido',
};

export default function PersonShow({ person, interactions, upcoming, channelOptions }: any) {
    const quickLog = () => {
        router.post(people.contacted(person.id).url, {}, { preserveScroll: true });
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
                        {/* Task 7 inserta aquí la sección de fechas clave. */}
                        {/* Task 8 inserta aquí la sección de redes. */}
                        {/* Task 6 inserta aquí la sección de historial. */}
                    </div>
                </div>
            </div>
        </PeopleLayout>
    );
}
