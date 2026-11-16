import { Head, Link } from '@inertiajs/react';
import { Flame } from 'lucide-react';
import CalendarGrid from '@/components/journal/CalendarGrid';
import type { JournalCalendarDay } from '@/components/journal/CalendarGrid';
import JournalSubnav from '@/components/journal/JournalSubnav';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface JournalCalendarProps {
    month: string;
    days: JournalCalendarDay[];
    streak: number;
    today: string;
    prevMonth: string;
    nextMonth: string;
}

function formatMonth(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);

    return new Date(year, monthNumber - 1, 1).toLocaleDateString('es-AR', {
        month: 'long',
        year: 'numeric',
    });
}

export default function JournalCalendar({
    month,
    days,
    streak,
    today,
    prevMonth,
    nextMonth,
}: JournalCalendarProps) {
    return (
        <>
            <Head title="Calendario" />
            <div className="mx-auto w-full max-w-3xl px-2 py-4 md:p-6">
                <header className="mb-6 flex flex-col gap-3">
                    <div>
                        <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                            Diario
                        </p>
                        <h1 className="text-xl font-semibold text-white/90">
                            Calendario
                        </h1>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className="size-11"
                        >
                            <Link
                                href={`/diario/calendario?month=${prevMonth}`}
                                aria-label="Mes anterior"
                            >
                                <span
                                    aria-hidden="true"
                                    className="text-2xl leading-none"
                                >
                                    ‹
                                </span>
                            </Link>
                        </Button>

                        <p className="flex-1 text-center text-sm font-semibold text-white/90 first-letter:uppercase">
                            {formatMonth(month)}
                        </p>

                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className="size-11"
                        >
                            <Link
                                href={`/diario/calendario?month=${nextMonth}`}
                                aria-label="Mes siguiente"
                            >
                                <span
                                    aria-hidden="true"
                                    className="text-2xl leading-none"
                                >
                                    ›
                                </span>
                            </Link>
                        </Button>
                    </div>

                    <div className="flex items-center justify-between gap-2">
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="min-h-11 text-muted-foreground"
                        >
                            <Link href="/diario/calendario">Mes actual</Link>
                        </Button>

                        <p
                            className={cn(
                                'flex items-center gap-2 text-sm tabular-nums',
                                streak === 0
                                    ? 'text-muted-foreground'
                                    : 'text-white/90',
                            )}
                            aria-label="Racha del diario"
                        >
                            <Flame
                                aria-hidden="true"
                                className={cn(
                                    'size-4',
                                    streak === 0
                                        ? 'text-muted-foreground'
                                        : 'text-primary',
                                )}
                            />
                            {streak === 0
                                ? 'Sin racha'
                                : `${streak} ${streak === 1 ? 'día seguido' : 'días seguidos'}`}
                        </p>
                    </div>
                </header>

                <JournalSubnav />

                <section
                    aria-label="Calendario mensual"
                    className="rounded-lg border border-border bg-card/40 px-2 py-3 md:p-4"
                >
                    <CalendarGrid month={month} days={days} today={today} />
                </section>

                <div className="mt-6 flex justify-center">
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="min-h-11"
                    >
                        <Link href={`/diario/${today}`}>Ir al día de hoy</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
