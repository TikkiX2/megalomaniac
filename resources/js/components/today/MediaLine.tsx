export interface MediaPick {
    title: string;
    type: string;
    cover_url: string | null;
}

interface Props {
    pick: MediaPick | null;
    onNext: () => void;
}

/**
 * Línea de media del día.
 * El selector de tipo y "Sorprendeme" se cablean en la Tarea 5.
 */
export default function MediaLine({ pick, onNext }: Props) {
    if (!pick) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-background/60 px-4 py-3">
            <p className="min-w-0 flex-1 truncate text-sm text-muted-foreground">
                Hoy toca: <span className="font-bold text-foreground">{pick.title}</span>
            </p>

            {/* Tarea 5: selector de tipo (pelicula|serie|disco|libro|juego) + botón "Sorprendeme". */}
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    onClick={onNext}
                    className="flex h-11 items-center rounded-lg border border-border bg-card px-4 text-xs font-black text-foreground transition hover:border-primary/60 hover:text-primary"
                >
                    Siguiente
                </button>
            </div>
        </div>
    );
}
