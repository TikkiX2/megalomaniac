import { Head, Link } from '@inertiajs/react';
import { CalendarClock } from 'lucide-react';
import React from 'react';
import PeopleLayout from '@/layouts/people-layout';
import people from '@/routes/people';

function monthLabel(value: string): string {
    return new Date(value).toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });
}

export default function PeopleTimeline({ interactions }: any) {
    const groups = interactions.data.reduce((acc: Record<string, any[]>, item: any) => {
        const key = monthLabel(item.occurred_at);
        (acc[key] ||= []).push(item);
        return acc;
    }, {});

    return (
        <PeopleLayout>
            <Head title="Historial social" />
            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-white">Historial social</h1>
                    <p className="text-muted-foreground">Todas tus interacciones, agrupadas por mes.</p>
                </div>

                {interactions.data.length === 0 ? (
                    <div className="text-center py-12 text-muted-foreground italic border border-dashed border-border rounded-xl bg-card">
                        Todavía no registraste interacciones.
                    </div>
                ) : Object.entries(groups).map(([month, items]) => (
                    <div key={month} className="flex flex-col gap-2">
                        <h2 className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">{month}</h2>
                        <div className="flex flex-col gap-1 bg-card border border-border rounded-xl overflow-hidden">
                            {(items as any[]).map((item) => (
                                <div key={item.id} className="flex items-center gap-3 px-4 py-3 border-b border-border last:border-b-0">
                                    <CalendarClock className="h-4 w-4 text-primary" />
                                    <div className="flex-1">
                                        <p className="text-sm text-white font-medium">{item.title || 'Interacción'}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(item.occurred_at).toLocaleDateString('es-ES')} · {item.channel}
                                        </p>
                                    </div>
                                    {item.person && (
                                        <Link href={people.show(item.person.id).url} className="text-xs font-bold text-primary hover:underline">
                                            {item.person.first_name} {item.person.last_name}
                                        </Link>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                ))}

                {(interactions.prev_page_url || interactions.next_page_url) && (
                    <div className="flex items-center justify-end gap-2">
                        {interactions.prev_page_url && <Link className="text-sm text-primary" href={interactions.prev_page_url}>Anterior</Link>}
                        {interactions.next_page_url && <Link className="text-sm text-primary" href={interactions.next_page_url}>Siguiente</Link>}
                    </div>
                )}
            </div>
        </PeopleLayout>
    );
}
