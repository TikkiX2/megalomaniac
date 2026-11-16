import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { JournalCalendarDay } from '@/components/journal/CalendarGrid';
import DayEditor from '@/components/journal/DayEditor';
import type { JournalDayPayload } from '@/components/journal/DayEditor';
import JournalRail from '@/components/journal/JournalRail';
import type { JournalMemory } from '@/components/journal/MemoryCard';
import MomentCard from '@/components/journal/MomentCard';
import type {
    JournalMomentPayload,
    JournalMomentPerson,
} from '@/components/journal/MomentCard';
import MomentDialog from '@/components/journal/MomentDialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface FlashProps {
    success?: string | null;
    error?: string | null;
}

interface JournalCalendarRailProps {
    month: string;
    days: JournalCalendarDay[];
    streak: number;
}

interface JournalIndexProps {
    date: string;
    today: string;
    day: JournalDayPayload | null;
    moments: JournalMomentPayload[];
    people: JournalMomentPerson[];
    calendar: JournalCalendarRailProps;
    prevDate: string;
    nextDate: string;
    onThisDay: JournalMemory | null;
    flash?: FlashProps;
}

interface MomentEditorState {
    moment: JournalMomentPayload | null;
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
    moments,
    people,
    calendar,
    prevDate,
    nextDate,
    onThisDay,
    flash,
}: JournalIndexProps) {
    const [editor, setEditor] = useState<MomentEditorState | null>(null);
    const [pendingDelete, setPendingDelete] =
        useState<JournalMomentPayload | null>(null);
    const [deleting, setDeleting] = useState(false);

    const editorMoment =
        editor === null || editor.moment === null
            ? null
            : (moments.find((moment) => moment.id === editor.moment?.id) ??
              editor.moment);

    const confirmDelete = () => {
        if (pendingDelete === null) {
            return;
        }

        router.delete(`/diario/momentos/${pendingDelete.id}`, {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => {
                setDeleting(false);
                setPendingDelete(null);
            },
        });
    };

    return (
        <>
            <Head title="Diario" />
            <div className="mx-auto flex w-full max-w-6xl flex-col p-4 md:p-6 lg:flex-row lg:items-start lg:justify-center lg:gap-8">
                <div className="mx-auto w-full max-w-3xl min-w-0">
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

                    {flash?.success && (
                        <div
                            role="status"
                            className="mb-4 rounded-md border border-primary/30 bg-primary/10 px-3 py-2 text-sm text-primary"
                        >
                            {flash.success}
                        </div>
                    )}

                    <DayEditor key={date} date={date} day={day} />

                    <section className="mt-10">
                        <div className="mb-4 flex items-baseline justify-between">
                            <h2 className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                Momentos del día
                            </h2>
                            {moments.length > 0 && (
                                <span className="text-[11px] text-muted-foreground tabular-nums">
                                    {moments.length}
                                </span>
                            )}
                        </div>

                        {moments.length === 0 ? (
                            <button
                                type="button"
                                onClick={() => setEditor({ moment: null })}
                                className="flex min-h-24 w-full items-center justify-center gap-2 rounded-lg border border-dashed border-border text-sm text-muted-foreground transition-colors outline-none hover:border-primary/40 hover:text-white/90 focus-visible:ring-3 focus-visible:ring-primary/40"
                            >
                                <Plus aria-hidden="true" className="size-4" />
                                Agregar el primer momento
                            </button>
                        ) : (
                            <div>
                                {moments.map((moment) => (
                                    <MomentCard
                                        key={moment.id}
                                        moment={moment}
                                        onEdit={(target) =>
                                            setEditor({ moment: target })
                                        }
                                        onDelete={(target) =>
                                            setPendingDelete(target)
                                        }
                                    />
                                ))}

                                <div className="flex gap-2">
                                    <span
                                        aria-hidden="true"
                                        className="w-12 shrink-0"
                                    />
                                    <div className="relative flex w-6 shrink-0 justify-center">
                                        <span
                                            aria-hidden="true"
                                            className="absolute top-0 bottom-1/2 w-px bg-border"
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() =>
                                            setEditor({ moment: null })
                                        }
                                        className="min-h-11 justify-start px-0 text-sm text-muted-foreground hover:bg-transparent hover:text-white/90"
                                    >
                                        <Plus
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        Agregar un momento
                                    </Button>
                                </div>
                            </div>
                        )}
                    </section>
                </div>

                <div className="mt-8 hidden lg:sticky lg:top-6 lg:mt-0 lg:block lg:w-64 lg:shrink-0">
                    <JournalRail
                        month={calendar.month}
                        days={calendar.days}
                        streak={calendar.streak}
                        today={today}
                        onThisDay={onThisDay}
                    />
                </div>
            </div>

            {editor !== null && (
                <MomentDialog
                    key={editorMoment?.id ?? 'new'}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditor(null);
                        }
                    }}
                    date={date}
                    moment={editorMoment}
                    people={people}
                />
            )}

            <Dialog
                open={pendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setPendingDelete(null);
                    }
                }}
            >
                <DialogContent className="border-border bg-card sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Eliminar momento</DialogTitle>
                        <DialogDescription>
                            Se borra este momento del día. No se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="ghost"
                            onClick={() => setPendingDelete(null)}
                            disabled={deleting}
                            className="min-h-11"
                        >
                            Cancelar
                        </Button>
                        <Button
                            onClick={confirmDelete}
                            disabled={deleting}
                            className="min-h-11 bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {deleting ? 'Eliminando…' : 'Eliminar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
