import { Link } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface JournalCalendarDay {
    date: string;
    mood: number | null;
    public: boolean;
    moments: number;
}

interface CalendarGridProps {
    month: string;
    days: JournalCalendarDay[];
    today: string;
    compact?: boolean;
}

const WEEKDAYS = ['L', 'M', 'X', 'J', 'V', 'S', 'D'] as const;

const MOOD_LABELS: Record<number, string> = {
    1: 'Bajo',
    2: 'Flojo',
    3: 'Normal',
    4: 'Bien',
    5: 'Muy bien',
};

function formatDate(date: Date): string {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function formatLongDate(date: Date): string {
    return date.toLocaleDateString('es-AR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

/**
 * Six weeks of cells, Monday first, including the leading and trailing days
 * of the adjacent months.
 */
function buildCells(
    month: string,
): { date: string; day: number; inMonth: boolean }[] {
    const [year, monthNumber] = month.split('-').map(Number);
    const first = new Date(year, monthNumber - 1, 1);
    const offset = (first.getDay() + 6) % 7;

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(year, monthNumber - 1, index - offset + 1);

        return {
            date: formatDate(date),
            day: date.getDate(),
            inMonth: date.getMonth() === monthNumber - 1,
        };
    });
}

function describeDay(day: JournalCalendarDay): string {
    const parts: string[] = [];

    if (day.moments > 0) {
        parts.push(day.moments === 1 ? '1 momento' : `${day.moments} momentos`);
    }

    if (day.mood !== null && MOOD_LABELS[day.mood] !== undefined) {
        parts.push(`Ánimo ${MOOD_LABELS[day.mood].toLowerCase()}`);
    }

    return parts.join(' · ');
}

export default function CalendarGrid({
    month,
    days,
    today,
    compact = false,
}: CalendarGridProps) {
    const byDate = new Map(days.map((day) => [day.date, day]));
    const cells = buildCells(month);

    return (
        <div
            role="group"
            className="grid grid-cols-7 gap-1"
            aria-label={`Calendario de ${month}`}
        >
            {WEEKDAYS.map((weekday) => (
                <div
                    key={weekday}
                    aria-hidden="true"
                    className={cn(
                        'pb-1 text-center text-[10px] font-black tracking-widest text-muted-foreground uppercase',
                        compact ? 'text-[9px]' : '',
                    )}
                >
                    {weekday}
                </div>
            ))}

            {cells.map((cell) => {
                const day = byDate.get(cell.date);

                if (!cell.inMonth) {
                    return (
                        <div
                            key={cell.date}
                            aria-hidden="true"
                            className={cn(
                                'flex flex-col items-center justify-center rounded-md text-muted-foreground/30',
                                compact
                                    ? 'aspect-square text-[11px]'
                                    : 'min-h-12 text-xs',
                            )}
                        >
                            {cell.day}
                        </div>
                    );
                }

                const isToday = cell.date === today;
                const hasContent = day !== undefined;
                const label = formatLongDate(new Date(`${cell.date}T12:00:00`));
                const description = day === undefined ? '' : describeDay(day);

                return (
                    <Link
                        key={cell.date}
                        href={`/diario/${cell.date}`}
                        title={
                            day?.public === true && description !== ''
                                ? description
                                : undefined
                        }
                        aria-label={
                            day === undefined
                                ? label
                                : day.public
                                  ? `${label}: ${description === '' ? 'con contenido' : description}`
                                  : `${label}: contenido privado`
                        }
                        aria-current={isToday ? 'date' : undefined}
                        className={cn(
                            'relative flex flex-col items-center justify-center gap-0.5 rounded-md border border-transparent text-white/80 transition-colors outline-none hover:border-primary/40 hover:text-white focus-visible:ring-3 focus-visible:ring-primary/40',
                            compact
                                ? 'aspect-square text-[11px]'
                                : 'min-h-12 text-sm',
                            isToday
                                ? 'border-primary/60 text-white ring-1 ring-primary/60'
                                : '',
                        )}
                    >
                        <span className="font-semibold tabular-nums">
                            {cell.day}
                        </span>

                        {hasContent &&
                            (day.public ? (
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'rounded-full bg-primary',
                                        compact ? 'size-1' : 'size-1.5',
                                    )}
                                />
                            ) : (
                                <Lock
                                    aria-hidden="true"
                                    className={cn(
                                        'text-muted-foreground',
                                        compact ? 'size-2.5' : 'size-3',
                                    )}
                                />
                            ))}
                    </Link>
                );
            })}
        </div>
    );
}
