import { Head, Link, router } from '@inertiajs/react';
import DayEditor from '@/components/journal/DayEditor';
import type { JournalDayPayload } from '@/components/journal/DayEditor';
import { Button } from '@/components/ui/button';

interface JournalIndexProps {
    date: string;
    today: string;
    day: JournalDayPayload | null;
    prevDate: string;
    nextDate: string;
}

function formatLongDate(date: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString('es-AR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

export default function JournalIndex({
    date,
    today,
    day,
    prevDate,
    nextDate,
}: JournalIndexProps) {
    return (
        <>
            <Head title="Diario" />
            <div className="mx-auto w-full max-w-3xl p-4 md:p-6">
                <header className="mb-6 flex flex-col gap-2">
                    <div className="flex items-center gap-3">
                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className="size-11"
                        >
                            <Link
                                href={`/diario/${prevDate}`}
                                aria-label="Día anterior"
                            >
                                <span
                                    aria-hidden="true"
                                    className="text-2xl leading-none"
                                >
                                    ‹
                                </span>
                            </Link>
                        </Button>

                        <div className="flex-1 text-center">
                            <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                Diario
                            </p>
                            <h1 className="text-xl font-semibold text-white/90">
                                {formatLongDate(date)}
                            </h1>
                        </div>

                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className="size-11"
                        >
                            <Link
                                href={`/diario/${nextDate}`}
                                aria-label="Día siguiente"
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

                    {date !== today && (
                        <div className="flex justify-center">
                            <Button
                                variant="outline"
                                size="sm"
                                className="min-h-11"
                                onClick={() => router.get('/diario')}
                            >
                                Hoy
                            </Button>
                        </div>
                    )}
                </header>

                <DayEditor key={date} date={date} day={day} />
            </div>
        </>
    );
}
