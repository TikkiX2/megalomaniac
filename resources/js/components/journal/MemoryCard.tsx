import { Link } from '@inertiajs/react';
import { Lock } from 'lucide-react';

export interface JournalMemory {
    date: string;
    excerpt: string;
    moments_count?: number;
    is_private: boolean;
    photo_url?: string | null;
}

interface MemoryCardProps {
    memory: JournalMemory;
    href?: string;
}

const MONTHS = [
    'ene',
    'feb',
    'mar',
    'abr',
    'may',
    'jun',
    'jul',
    'ago',
    'sep',
    'oct',
    'nov',
    'dic',
];

function dateParts(date: string): { day: string; month: string; year: string } {
    const [year, month, day] = date.split('-').map(Number);

    if (!year || !month || !day) {
        return { day: day ? String(day) : '—', month: '', year: '' };
    }

    return {
        day: String(day).padStart(2, '0'),
        month: MONTHS[month - 1] ?? '',
        year: String(year),
    };
}

function momentsLabel(count: number): string {
    return `${count} ${count === 1 ? 'momento' : 'momentos'}`;
}

export default function MemoryCard({ memory, href }: MemoryCardProps) {
    const { day, month, year } = dateParts(memory.date);
    const count = memory.moments_count ?? 0;

    return (
        <Link
            href={href ?? `/diario/${memory.date}`}
            className="group flex gap-3 rounded-lg border border-border bg-card/40 p-3 transition-colors outline-none hover:border-primary/40 focus-visible:ring-3 focus-visible:ring-primary/40"
        >
            <time
                dateTime={memory.date}
                className="flex w-14 shrink-0 flex-col items-center self-start rounded-md border border-border bg-background/60 px-1 py-1.5 text-center"
            >
                <span className="text-lg leading-none font-bold text-white/90 tabular-nums">
                    {day}
                </span>
                <span className="mt-1 text-[10px] font-black tracking-widest text-primary uppercase">
                    {month}
                </span>
                <span className="text-[10px] text-muted-foreground tabular-nums">
                    {year}
                </span>
            </time>

            <div className="min-w-0 flex-1">
                {memory.excerpt ? (
                    <p className="line-clamp-3 text-[15px] leading-relaxed text-white/80">
                        {memory.excerpt}
                    </p>
                ) : (
                    <p className="text-[15px] leading-relaxed text-muted-foreground italic">
                        Sin texto
                    </p>
                )}

                {(count > 0 || memory.is_private) && (
                    <p className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                        {count > 0 && (
                            <span className="tabular-nums">
                                {momentsLabel(count)}
                            </span>
                        )}

                        {memory.is_private && (
                            <span className="flex items-center gap-1">
                                <Lock aria-hidden="true" className="size-3" />
                                Privado
                            </span>
                        )}
                    </p>
                )}
            </div>

            {memory.photo_url ? (
                <img
                    src={memory.photo_url}
                    alt=""
                    loading="lazy"
                    className="size-16 shrink-0 self-center rounded-md border border-border object-cover"
                />
            ) : null}
        </Link>
    );
}
