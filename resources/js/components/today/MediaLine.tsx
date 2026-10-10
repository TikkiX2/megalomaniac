import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { csrfHeaders } from '@/lib/csrf';

export interface MediaPick {
    title: string;
    type: string;
    cover_url: string | null;
}

export interface PickTypeOption {
    value: string;
    label: string;
}

interface Props {
    pick: MediaPick | null;
    pickType: string;
    pickTypes: PickTypeOption[];
    onNext: () => void;
}

const EMPTY_NOTICE = 'No hay nada de ese tipo en la cola.';

/** Identidad del pick del server: si cambia, se descarta lo local (misma semántica que un effect de sync). */
const pickKey = (pick: MediaPick | null) => (pick ? `${pick.type}|${pick.title}|${pick.cover_url ?? ''}` : '');

/** Se evalúa en cada request: la cookie XSRF rota en cada respuesta. */
const jsonHeaders = () => ({ 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() });

/**
 * Línea de media del día: selector de tipo (persiste en el día), "Sorprendeme"
 * (solo click explícito, sin persistir ni notificar) y "Siguiente".
 */
export default function MediaLine({ pick, pickType, pickTypes, onNext }: Props) {
    const [local, setLocal] = useState<{ key: string; surprise: MediaPick | null; notice: string | null }>({
        key: pickKey(pick),
        surprise: null,
        notice: null,
    });

    // Cada reload de 'pick' trae la verdad del servidor: se descarta lo local.
    const stale = local.key !== pickKey(pick);
    const surprise = stale ? null : local.surprise;
    const notice = stale ? null : local.notice;

    const changeType = async (type: string) => {
        await fetch('/today/pick-type', {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({ type }),
            redirect: 'manual',
        });
        await router.reload({ only: ['day', 'pick'] });
    };

    const surpriseMe = async () => {
        const res = await fetch(`/today/surprise?type=${encodeURIComponent(pickType)}`, {
            headers: { Accept: 'application/json', ...csrfHeaders() },
            redirect: 'manual',
        });

        if (res.status === 204) {
            setLocal({ key: pickKey(pick), surprise: null, notice: EMPTY_NOTICE });
            return;
        }

        if (!res.ok) {
            return;
        }

        setLocal({ key: pickKey(pick), surprise: (await res.json()) as MediaPick, notice: null });
    };

    const shown = surprise ?? pick;

    return (
        <div className="flex flex-col gap-3 rounded-xl border border-border bg-background/60 px-4 py-3">
            {notice ? (
                <p className="text-sm text-muted-foreground">{notice}</p>
            ) : shown ? (
                <p className="truncate text-sm text-muted-foreground">
                    Hoy toca: <span className="font-bold text-foreground">{shown.title}</span>
                </p>
            ) : (
                <p className="text-sm text-muted-foreground">{EMPTY_NOTICE}</p>
            )}

            <div className="flex flex-wrap items-center gap-2">
                <Select value={pickType} onValueChange={changeType}>
                    <SelectTrigger
                        aria-label="Tipo de media"
                        className="h-11 min-w-0 flex-1 border-border bg-card sm:w-40 sm:flex-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {pickTypes.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <button
                    type="button"
                    onClick={surpriseMe}
                    className="flex h-11 items-center rounded-lg border border-border bg-card px-4 text-xs font-black text-foreground transition hover:border-primary/60 hover:text-primary"
                >
                    Sorprendeme
                </button>

                {shown && (
                    <button
                        type="button"
                        onClick={onNext}
                        className="flex h-11 items-center rounded-lg border border-border bg-card px-4 text-xs font-black text-foreground transition hover:border-primary/60 hover:text-primary"
                    >
                        Siguiente
                    </button>
                )}
            </div>
        </div>
    );
}
