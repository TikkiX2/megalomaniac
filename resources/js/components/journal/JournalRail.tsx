import { Link } from '@inertiajs/react';
import { Flame } from 'lucide-react';
import { cn } from '@/lib/utils';
import CalendarGrid from './CalendarGrid';
import type { JournalCalendarDay } from './CalendarGrid';

interface JournalRailProps {
    month: string;
    days: JournalCalendarDay[];
    streak: number;
    today: string;
}

function formatMonth(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);

    return new Date(year, monthNumber - 1, 1).toLocaleDateString('es-AR', {
        month: 'long',
        year: 'numeric',
    });
}

export default function JournalRail({
    month,
    days,
    streak,
    today,
}: JournalRailProps) {
    return (
        <aside className="flex flex-col gap-4">
            <section className="rounded-lg border border-border bg-card/60 p-3">
                <header className="mb-2 flex items-baseline justify-between gap-2">
                    <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                        {formatMonth(month)}
                    </p>
                    <Link
                        href={`/diario/calendario?month=${month}`}
                        className="rounded-sm text-[11px] text-muted-foreground transition-colors outline-none hover:text-white/90 focus-visible:ring-3 focus-visible:ring-primary/40"
                    >
                        Ver calendario
                    </Link>
                </header>

                <CalendarGrid compact month={month} days={days} today={today} />
            </section>

            <section
                className={cn(
                    'flex items-center gap-3 rounded-lg border border-border bg-card/60 p-3',
                    streak === 0 ? 'text-muted-foreground' : 'text-white/90',
                )}
                aria-label="Racha del diario"
            >
                <Flame
                    aria-hidden="true"
                    className={cn(
                        'size-4 shrink-0',
                        streak === 0 ? 'text-muted-foreground' : 'text-primary',
                    )}
                />
                <div className="min-w-0">
                    <p className="text-sm font-semibold tabular-nums">
                        {streak === 0
                            ? 'Sin racha'
                            : `${streak} ${streak === 1 ? 'día seguido' : 'días seguidos'}`}
                    </p>
                    <p className="text-[11px] text-muted-foreground">
                        {streak === 0
                            ? 'Escribí hoy para empezar'
                            : 'Días con contenido'}
                    </p>
                </div>
            </section>

            {/* Tarea 8: teaser de Repaso */}
            <section
                data-slot="repaso-teaser"
                className="rounded-lg border border-dashed border-border p-3"
            >
                <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                    Repaso
                </p>
                <p className="mt-1 text-[11px] text-muted-foreground">
                    Próximamente
                </p>
            </section>
        </aside>
    );
}
