import { Link, router } from '@inertiajs/react';
import { csrfHeaders } from '@/lib/csrf';
import DayItemRow from './DayItemRow';
import MediaLine, { type MediaPick } from './MediaLine';

export interface TodayItem {
    id: number;
    title: string;
    anchor: string;
    position: number;
    state: 'pending' | 'done' | 'released';
    closing_note: string | null;
}

export interface DayPayload {
    id: number;
    pick_type: string;
    items: TodayItem[];
}

export interface BlockPayload {
    label: string;
    start_time: string;
    duration_min: number;
}

export interface RoutinePayload {
    id: number;
    name: string;
    focus: string | null;
}

export interface TodayPanelProps {
    date: string;
    day: DayPayload | null;
    block: BlockPayload | null;
    routine: RoutinePayload | null;
    pick: MediaPick | null;
}

/** Se evalúa en cada request: la cookie XSRF rota en cada respuesta. */
const jsonHeaders = () => ({ 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() });

export default function TodayPanel({ date, day, block, routine, pick }: TodayPanelProps) {
    const items = day?.items ?? [];

    const refresh = (only: string[]) => router.reload({ only });

    const patchItem = async (id: number, body: Record<string, unknown>) => {
        await fetch(`/today/items/${id}`, {
            method: 'PATCH',
            headers: jsonHeaders(),
            body: JSON.stringify(body),
            redirect: 'manual',
        });
        await refresh(['day']);
    };

    const releaseItem = async (id: number) => {
        await fetch(`/today/items/${id}/release`, {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({}),
            redirect: 'manual',
        });
        await refresh(['day']);
    };

    const nextInQueue = async () => {
        await fetch('/today/queue/next', {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({}),
            redirect: 'manual',
        });
        await refresh(['pick']);
    };

    const fecha = new Date(`${date}T00:00:00`).toLocaleDateString('es-ES', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });

    return (
        <section className="flex w-full flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-xl sm:p-5">
            <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h2 className="text-lg font-black tracking-tight text-white">Hoy</h2>
                <span className="text-xs font-bold uppercase tracking-widest text-muted-foreground">{fecha}</span>
            </header>

            {items.length === 0 ? (
                <div className="flex flex-col items-center gap-3 rounded-xl border border-border bg-background/60 px-4 py-6 text-center">
                    <p className="text-sm font-medium text-muted-foreground">Hoy no hay nada elegido.</p>
                    <Link
                        href="/today/tomorrow"
                        className="flex h-11 items-center rounded-lg bg-primary px-5 text-sm font-black text-white transition hover:bg-primary/90 active:scale-95"
                    >
                        Elegir
                    </Link>
                </div>
            ) : (
                <div className="flex flex-col divide-y divide-border">
                    {items.map((item) => (
                        <DayItemRow
                            key={item.id}
                            item={item}
                            onPatch={(body) => patchItem(item.id, body)}
                            onRelease={() => releaseItem(item.id)}
                        />
                    ))}
                </div>
            )}

            <MediaLine pick={pick} onNext={nextInQueue} />

            {(block || routine) && (
                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                    {block && (
                        <span>
                            Bloque {block.label} · {block.start_time} · {block.duration_min} min
                        </span>
                    )}
                    {routine && <span>Gimnasio {routine.name}</span>}
                </div>
            )}
        </section>
    );
}
