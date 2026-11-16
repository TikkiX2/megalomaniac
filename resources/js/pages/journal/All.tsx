import { Head, Link } from '@inertiajs/react';
import { NotebookPen } from 'lucide-react';
import MemoryCard from '@/components/journal/MemoryCard';
import type { JournalMemory } from '@/components/journal/MemoryCard';
import { Button } from '@/components/ui/button';

interface JournalTimelinePaginator {
    data: JournalMemory[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

interface JournalAllProps {
    days: JournalTimelinePaginator;
    total: number;
}

interface MonthGroup {
    month: string;
    label: string;
    memories: JournalMemory[];
}

function formatMonth(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);

    return new Date(year, monthNumber - 1, 1).toLocaleDateString('es-AR', {
        month: 'long',
        year: 'numeric',
    });
}

/**
 * The timeline arrives ordered by date descending, so consecutive entries of
 * the same month collapse into a single group.
 */
function groupByMonth(memories: JournalMemory[]): MonthGroup[] {
    const groups: MonthGroup[] = [];

    for (const memory of memories) {
        const month = memory.date.slice(0, 7);
        const current = groups[groups.length - 1];

        if (current?.month === month) {
            current.memories.push(memory);
        } else {
            groups.push({
                month,
                label: formatMonth(month),
                memories: [memory],
            });
        }
    }

    return groups;
}

export default function JournalAll({ days, total }: JournalAllProps) {
    const groups = groupByMonth(days.data);

    return (
        <>
            <Head title="Todas las memorias" />
            <div className="mx-auto w-full max-w-3xl p-4 md:p-6">
                <header className="mb-6 flex flex-col gap-1">
                    <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                        Diario
                    </p>
                    <div className="flex items-baseline justify-between gap-3">
                        <h1 className="text-xl font-semibold text-white/90">
                            Todas las memorias
                        </h1>
                        <p className="text-[11px] text-muted-foreground tabular-nums">
                            {total} {total === 1 ? 'memoria' : 'memorias'}
                        </p>
                    </div>
                </header>

                {days.data.length === 0 ? (
                    <section className="flex flex-col items-center gap-4 rounded-lg border border-dashed border-border px-4 py-14 text-center">
                        <NotebookPen
                            aria-hidden="true"
                            className="size-5 text-muted-foreground"
                        />
                        <div>
                            <p className="text-sm font-semibold text-white/90">
                                Todavía no hay memorias
                            </p>
                            <p className="mt-1 text-[13px] text-muted-foreground">
                                Escribí el primer día y va a aparecer acá.
                            </p>
                        </div>
                        <Button asChild className="min-h-11">
                            <Link href="/diario">Escribir hoy</Link>
                        </Button>
                    </section>
                ) : (
                    groups.map((group) => (
                        <section
                            key={group.month}
                            aria-labelledby={`timeline-month-${group.month}`}
                            className="mb-8"
                        >
                            <h2
                                id={`timeline-month-${group.month}`}
                                className="mb-2 px-1 text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                {group.label}
                            </h2>

                            <div className="flex flex-col gap-2">
                                {group.memories.map((memory) => (
                                    <MemoryCard
                                        key={memory.date}
                                        memory={memory}
                                    />
                                ))}
                            </div>
                        </section>
                    ))
                )}

                {(days.prev_page_url || days.next_page_url) && (
                    <nav
                        aria-label="Paginación de memorias"
                        className="flex items-center justify-end gap-2"
                    >
                        <Button
                            variant="outline"
                            disabled={!days.prev_page_url}
                            asChild={!!days.prev_page_url}
                            className="min-h-11"
                        >
                            {days.prev_page_url ? (
                                <Link href={days.prev_page_url}>Anterior</Link>
                            ) : (
                                <span>Anterior</span>
                            )}
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            Página {days.current_page} de {days.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={!days.next_page_url}
                            asChild={!!days.next_page_url}
                            className="min-h-11"
                        >
                            {days.next_page_url ? (
                                <Link href={days.next_page_url}>Siguiente</Link>
                            ) : (
                                <span>Siguiente</span>
                            )}
                        </Button>
                    </nav>
                )}
            </div>
        </>
    );
}
