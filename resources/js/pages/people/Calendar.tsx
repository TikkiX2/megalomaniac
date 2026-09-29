import { Head, Link } from '@inertiajs/react';
import { Cake, ChevronLeft, ChevronRight } from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import PeopleLayout from '@/layouts/people-layout';
import people from '@/routes/people';

interface Entry {
    id: string;
    label: string;
    personId: number;
    personName: string;
    recurring: boolean;
    date: string;
}

function nextOccurrence(date: string, recurring: boolean): Date | null {
    const original = new Date(`${date.slice(0, 10)}T00:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    let candidate = new Date(original);

    if (recurring) {
        candidate = new Date(today.getFullYear(), original.getMonth(), original.getDate());
        if (candidate < today) candidate = new Date(today.getFullYear() + 1, original.getMonth(), original.getDate());
    }

    return candidate >= today ? candidate : null;
}

export default function PeopleCalendar({ people: peopleList, keyDates }: any) {
    const [current, setCurrent] = useState(() => new Date());
    const year = current.getFullYear();
    const month = current.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const startOffset = firstDay === 0 ? 6 : firstDay - 1;

    const days: (number | null)[] = Array(startOffset).fill(null);
    for (let day = 1; day <= daysInMonth; day++) days.push(day);
    while (days.length % 7 !== 0) days.push(null);

    const entries: Entry[] = [
        ...peopleList.filter((p: any) => p.birthday).map((p: any) => ({
            id: `person-${p.id}`,
            label: `Cumpleaños de ${p.first_name}`,
            personId: p.id,
            personName: p.first_name,
            recurring: true,
            date: p.birthday.slice(0, 10),
        })),
        ...keyDates.map((k: any) => ({
            id: `key-${k.id}`,
            label: k.label || k.type,
            personId: k.person?.id,
            personName: `${k.person?.first_name ?? ''} ${k.person?.last_name ?? ''}`.trim(),
            recurring: k.is_recurring_annually,
            date: k.date.slice(0, 10),
        })),
    ];

    const entriesForDay = (day: number) => {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        return entries.filter((entry) => {
            const occurrence = nextOccurrence(entry.date, entry.recurring);
            if (!occurrence) return false;
            return occurrence.toISOString().slice(0, 10) === dateStr;
        });
    };

    const upcoming = entries
        .map((entry) => ({ entry, next: nextOccurrence(entry.date, entry.recurring) }))
        .filter((row): row is { entry: Entry; next: Date } => row.next !== null)
        .sort((a, b) => a.next.getTime() - b.next.getTime())
        .slice(0, 8);

    const monthName = current.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });

    return (
        <PeopleLayout>
            <Head title="Calendario de fechas" />
            <div className="grid gap-6 p-4 md:p-6 lg:grid-cols-[2fr_1fr] animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 bg-card border border-border rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h1 className="text-lg font-bold capitalize text-white">{monthName}</h1>
                        <div className="flex gap-1">
                            <Button variant="outline" size="icon" className="h-8 w-8" onClick={() => setCurrent(new Date(year, month - 1, 1))}>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <Button variant="outline" size="sm" onClick={() => setCurrent(new Date())}>Hoy</Button>
                            <Button variant="outline" size="icon" className="h-8 w-8" onClick={() => setCurrent(new Date(year, month + 1, 1))}>
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <div className="grid grid-cols-7 gap-px bg-border rounded-lg overflow-hidden">
                        {['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'].map((day) => (
                            <div key={day} className="bg-muted py-2 text-center text-[10px] font-black uppercase tracking-widest text-muted-foreground">{day}</div>
                        ))}
                        {days.map((day, index) => (
                            <div key={index} className={`min-h-24 p-1 flex flex-col gap-1 ${day == null ? 'bg-muted/30' : 'bg-card'}`}>
                                {day != null && (
                                    <>
                                        <span className="text-xs font-bold h-6 w-6 flex items-center justify-center rounded-full text-white/80">{day}</span>
                                        {entriesForDay(day).map((entry) => (
                                            <Link
                                                key={entry.id}
                                                href={people.show(entry.personId).url}
                                                className="text-[10px] leading-tight truncate px-1 py-0.5 rounded bg-primary/20 text-primary font-medium hover:bg-primary/30"
                                            >
                                                {entry.label}
                                            </Link>
                                        ))}
                                    </>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex flex-col gap-3">
                    <h2 className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Próximas fechas</h2>
                    {upcoming.length === 0 ? (
                        <div className="text-center py-12 text-muted-foreground italic border border-dashed border-border rounded-xl bg-card">
                            Sin fechas cargadas.
                        </div>
                    ) : upcoming.map(({ entry, next }) => (
                        <Link
                            key={entry.id}
                            href={entry.personId ? people.show(entry.personId).url : people.calendar().url}
                            className="flex items-center gap-3 bg-card border border-border rounded-xl px-4 py-3 hover:border-primary/40"
                        >
                            <Cake className="h-4 w-4 text-primary" />
                            <div className="flex-1">
                                <p className="text-sm font-medium text-white">{entry.label}</p>
                                <p className="text-xs text-muted-foreground">{entry.personName}</p>
                            </div>
                            <Badge variant="outline" className="border-primary/30 text-primary text-[10px] font-black">
                                {next.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })}
                            </Badge>
                        </Link>
                    ))}
                </div>
            </div>
        </PeopleLayout>
    );
}
