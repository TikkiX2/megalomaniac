import { Head, Link, router } from '@inertiajs/react';
import { Dices, History } from 'lucide-react';
import { useState } from 'react';
import MemoryCard from '@/components/journal/MemoryCard';
import type { JournalMemoryRef } from '@/components/journal/MemoryCard';
import { Button } from '@/components/ui/button';

interface JournalReviewProps {
    onThisDay: JournalMemoryRef[];
    random: JournalMemoryRef | null;
    today: string;
}

function formatLongDate(date: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString('es-AR', {
        day: 'numeric',
        month: 'long',
    });
}

export default function JournalReview({
    onThisDay,
    random,
    today,
}: JournalReviewProps) {
    const [shuffling, setShuffling] = useState(false);

    const shuffle = () => {
        router.reload({
            only: ['random'],
            onStart: () => setShuffling(true),
            onFinish: () => setShuffling(false),
        });
    };

    return (
        <>
            <Head title="Repaso" />
            <div className="mx-auto w-full max-w-3xl p-4 md:p-6">
                <header className="mb-6 flex flex-col gap-1">
                    <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                        Diario
                    </p>
                    <div className="flex items-baseline justify-between gap-3">
                        <h1 className="text-xl font-semibold text-white/90">
                            Repaso
                        </h1>
                        <p className="text-[11px] text-muted-foreground">
                            {formatLongDate(today)}
                        </p>
                    </div>
                </header>

                <section aria-labelledby="review-on-this-day" className="mb-10">
                    <h2
                        id="review-on-this-day"
                        className="mb-2 flex items-center gap-2 px-1 text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                    >
                        <History aria-hidden="true" className="size-3.5" />
                        Tal día como hoy
                    </h2>

                    {onThisDay.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-border px-4 py-10 text-center">
                            <p className="text-sm font-semibold text-white/90">
                                Todavía no hay memorias de este día
                            </p>
                            <p className="mt-1 text-[13px] text-muted-foreground">
                                Lo que escribas hoy va a volver el año que
                                viene.
                            </p>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-2">
                            {onThisDay.map((memory) => (
                                <MemoryCard
                                    key={`${memory.type}-${memory.id}`}
                                    memory={memory}
                                    href={`/diario/${memory.date}`}
                                />
                            ))}
                        </div>
                    )}
                </section>

                <section aria-labelledby="review-random">
                    <div className="mb-2 flex items-center justify-between gap-2 px-1">
                        <h2
                            id="review-random"
                            className="flex items-center gap-2 text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                        >
                            <Dices aria-hidden="true" className="size-3.5" />
                            Una memoria al azar
                        </h2>
                        {random !== null && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={shuffle}
                                disabled={shuffling}
                                className="min-h-11"
                            >
                                Otra
                            </Button>
                        )}
                    </div>

                    {random === null ? (
                        <div className="rounded-lg border border-dashed border-border px-4 py-10 text-center">
                            <p className="text-sm font-semibold text-white/90">
                                Nada para revivir todavía
                            </p>
                            <p className="mt-1 text-[13px] text-muted-foreground">
                                Después de un mes, lo que escribas empieza a
                                aparecer acá.
                            </p>
                            <Button asChild className="mt-4 min-h-11">
                                <Link href="/diario">Escribir hoy</Link>
                            </Button>
                        </div>
                    ) : (
                        <MemoryCard
                            key={`${random.type}-${random.id}`}
                            memory={random}
                            href={`/diario/${random.date}`}
                        />
                    )}
                </section>
            </div>
        </>
    );
}
