import { Link } from '@inertiajs/react';
import { Flame, History } from 'lucide-react';
import { cn } from '@/lib/utils';
import CalendarGrid from './CalendarGrid';
import type { JournalCalendarDay } from './CalendarGrid';
import type { JournalMemory } from './MemoryCard';

interface JournalRailProps {
    month: string;
    days: JournalCalendarDay[];
    streak: number;
    today: string;
    onThisDay?: JournalMemory | null;
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
    onThisDay = null,
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

            <section
                data-slot="repaso-teaser"
                className="rounded-lg border border-border bg-card/60 p-3"
            >
                <header className="mb-2 flex items-baseline justify-between gap-2">
                    <p className="flex items-center gap-1.5 text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                        <History aria-hidden="true" className="size-3.5" />
                        Repaso
                    </p>
                    <Link
                        href="/diario/repaso"
                        className="rounded-sm text-[11px] text-muted-foreground transition-colors outline-none hover:text-white/90 focus-visible:ring-3 focus-visible:ring-primary/40"
                    >
                        Ver repaso
                    </Link>
                </header>

                {onThisDay === null ? (
                    <p className="text-[11px] text-muted-foreground">
                        Tal día como hoy, en años anteriores.
                    </p>
                ) : (
                    <Link
                        href="/diario/repaso"
                        className="block rounded-md outline-none focus-visible:ring-3 focus-visible:ring-primary/40"
                    >
                        <time
                            dateTime={onThisDay.date}
                            className="text-[10px] font-black tracking-widest text-primary uppercase tabular-nums"
                        >
                            {onThisDay.date.slice(0, 4)}
                        </time>
                        <p className="mt-1 line-clamp-3 text-[13px] leading-relaxed text-white/80">
                            {onThisDay.excerpt || 'Sin texto'}
                        </p>
                    </Link>
                )}
            </section>
        </aside>
    );
}
