import { Head, Link, router } from '@inertiajs/react';
import { NotebookPen, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import JournalSubnav from '@/components/journal/JournalSubnav';
import MemoryCard from '@/components/journal/MemoryCard';
import type { JournalMemory } from '@/components/journal/MemoryCard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface JournalTimelinePaginator {
    data: JournalMemory[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

interface JournalAllProps {
    q?: string;
    days: JournalTimelinePaginator | JournalMemory[];
    moments?: JournalMemory[];
    total?: number;
}

interface MonthGroup {
    month: string;
    label: string;
    memories: JournalMemory[];
}

const SEARCH_DEBOUNCE_MS = 300;

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

/**
 * Search results carry an id and a type, so two moments of the same day do not
 * collide as keys.
 */
function memoryKey(memory: JournalMemory): string {
    const candidate = memory as JournalMemory & {
        id?: number;
        type?: 'day' | 'moment';
    };

    return candidate.id === undefined
        ? memory.date
        : `${candidate.type ?? 'memory'}-${candidate.id}`;
}

function SearchField({ initialQuery }: { initialQuery: string }) {
    // The field owns the query while the page is mounted: every change goes
    // through it, and a debounced visit re-renders with the server's copy.
    const [value, setValue] = useState(initialQuery);

    useEffect(() => {
        const query = value.trim();

        if (query === initialQuery) {
            return;
        }

        const timeout = window.setTimeout(() => {
            if (query === '') {
                router.get(
                    '/diario/todas',
                    {},
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                );

                return;
            }

            router.get(
                '/diario/buscar',
                { q: query },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timeout);
    }, [value, initialQuery]);

    return (
        <div className="relative">
            <Search
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                type="search"
                value={value}
                onChange={(event) => setValue(event.target.value)}
                placeholder="Buscar en tus memorias"
                aria-label="Buscar memorias"
                maxLength={100}
                className="min-h-11 border-border bg-card/40 pl-9"
            />
        </div>
    );
}

function ResultSection({
    title,
    memories,
}: {
    title: string;
    memories: JournalMemory[];
}) {
    if (memories.length === 0) {
        return null;
    }

    return (
        <section className="mb-8">
            <h2 className="mb-2 px-1 text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                {title}{' '}
                <span className="tabular-nums">({memories.length})</span>
            </h2>

            <div className="flex flex-col gap-2">
                {memories.map((memory) => (
                    <MemoryCard
                        key={memoryKey(memory)}
                        memory={memory}
                        href={`/diario/${memory.date}`}
                    />
                ))}
            </div>
        </section>
    );
}

function SearchBody({
    query,
    days,
    moments,
}: {
    query: string;
    days: JournalMemory[];
    moments: JournalMemory[];
}) {
    const total = days.length + moments.length;
    const tooShort = query.trim().length < 2;

    if (total === 0) {
        return (
            <section className="flex flex-col items-center gap-4 rounded-lg border border-dashed border-border px-4 py-14 text-center">
                <NotebookPen
                    aria-hidden="true"
                    className="size-5 text-muted-foreground"
                />
                <div>
                    <p className="text-sm font-semibold text-white/90">
                        Nada con esa palabra
                    </p>
                    <p className="mt-1 text-[13px] text-muted-foreground">
                        {tooShort
                            ? 'Probá con dos letras o más.'
                            : 'Probá con otra palabra de lo que escribiste.'}
                    </p>
                </div>
                <Button asChild variant="outline" className="min-h-11">
                    <Link href="/diario/todas">Ver todas</Link>
                </Button>
            </section>
        );
    }

    return (
        <>
            <ResultSection title="Días" memories={days} />
            <ResultSection title="Momentos" memories={moments} />
        </>
    );
}

function TimelineBody({ days }: { days: JournalTimelinePaginator }) {
    const groups = groupByMonth(days.data);

    if (days.data.length === 0) {
        return (
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
        );
    }

    return (
        <>
            {groups.map((group) => (
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
                            <MemoryCard key={memory.date} memory={memory} />
                        ))}
                    </div>
                </section>
            ))}

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
        </>
    );
}

export default function JournalAll({
    q,
    days,
    moments,
    total,
}: JournalAllProps) {
    const isSearch = q !== undefined;
    const query = q ?? '';
    const memories = isSearch ? (days as JournalMemory[]) : [];
    const results = isSearch ? (moments ?? []) : [];
    const found = memories.length + results.length;
    const tooShort = query.trim().length < 2;
    const paginator = isSearch ? null : (days as JournalTimelinePaginator);
    const memoryTotal = total ?? paginator?.total ?? 0;

    return (
        <>
            <Head title={isSearch ? 'Buscar memorias' : 'Todas las memorias'} />
            <div className="mx-auto w-full max-w-3xl p-4 md:p-6">
                <header className="mb-6 flex flex-col gap-2">
                    <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                        Diario
                    </p>
                    <div className="flex items-baseline justify-between gap-3">
                        <h1 className="text-xl font-semibold text-white/90">
                            {isSearch ? 'Buscar' : 'Todas las memorias'}
                        </h1>
                        <p className="text-[11px] text-muted-foreground tabular-nums">
                            {isSearch
                                ? tooShort
                                    ? 'Escribí al menos 2 letras'
                                    : `${found} ${found === 1 ? 'resultado' : 'resultados'}`
                                : `${memoryTotal} ${memoryTotal === 1 ? 'memoria' : 'memorias'}`}
                        </p>
                    </div>

                    <SearchField initialQuery={query} />

                    {isSearch && query.trim().length > 0 && (
                        <p className="text-[13px] text-muted-foreground">
                            Resultados para{' '}
                            <span className="text-white/90">
                                “{query.trim()}”
                            </span>
                        </p>
                    )}
                </header>

                <JournalSubnav />

                {isSearch ? (
                    <SearchBody
                        query={query}
                        days={memories}
                        moments={results}
                    />
                ) : (
                    <TimelineBody
                        days={paginator as JournalTimelinePaginator}
                    />
                )}
            </div>
        </>
    );
}
