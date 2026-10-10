import { useState } from 'react';
import type { TodayItem } from './TodayPanel';

const ANCHOR_LABELS: Record<string, string> = {
    wake_up: 'al levantarme',
    after_meal: 'después de comer',
    after_gym: 'después del gimnasio',
    after_shower: 'después de bañarme',
    before_sleep: 'antes de dormir',
    no_anchor: 'sin ancla',
};

interface Props {
    item: TodayItem;
    onPatch: (body: { state?: string; closing_note?: string | null }) => void;
    onRelease: () => void;
}

export default function DayItemRow({ item, onPatch, onRelease }: Props) {
    const isDone = item.state === 'done';
    const [noteOpen, setNoteOpen] = useState(false);
    const [note, setNote] = useState(item.closing_note ?? '');

    const saveNote = () => {
        onPatch({ state: 'done', closing_note: note.trim() === '' ? null : note.trim() });
        setNoteOpen(false);
    };

    return (
        <div className="flex items-start gap-3 py-2.5">
            <button
                type="button"
                onClick={() => onPatch({ state: isDone ? 'pending' : 'done', closing_note: isDone ? item.closing_note : null })}
                aria-label={isDone ? 'Marcar pendiente' : 'Marcar hecho'}
                aria-pressed={isDone}
                className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border text-sm font-black transition ${
                    isDone
                        ? 'border-primary bg-primary text-white'
                        : 'border-border bg-background text-transparent hover:border-primary/60'
                }`}
            >
                ✓
            </button>

            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <span className={`truncate text-sm font-bold sm:text-base ${isDone ? 'text-muted-foreground line-through' : 'text-foreground'}`}>
                    {item.title}
                </span>
                <span className="text-xs text-muted-foreground">{ANCHOR_LABELS[item.anchor] ?? item.anchor}</span>

                {isDone && !noteOpen && !item.closing_note && (
                    <button
                        type="button"
                        onClick={() => setNoteOpen(true)}
                        className="w-fit text-left text-xs font-bold text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                    >
                        Agregar nota
                    </button>
                )}

                {isDone && !noteOpen && item.closing_note && (
                    <p className="text-xs italic text-muted-foreground">“{item.closing_note}”</p>
                )}

                {isDone && noteOpen && (
                    <div className="flex flex-wrap items-center gap-2 pt-1">
                        <input
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') saveNote();
                            }}
                            placeholder="¿cómo te sentiste?"
                            autoFocus
                            className="h-11 min-w-0 flex-1 rounded-lg border border-border bg-background px-3 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none"
                        />
                        <button
                            type="button"
                            onClick={saveNote}
                            className="h-11 rounded-lg bg-primary px-4 text-xs font-black text-white transition hover:bg-primary/90"
                        >
                            Guardar
                        </button>
                    </div>
                )}
            </div>

            {!isDone && (
                <button
                    type="button"
                    onClick={onRelease}
                    className="flex h-11 shrink-0 items-center rounded-lg px-2 text-xs font-bold text-muted-foreground transition hover:text-foreground"
                >
                    Soltar
                </button>
            )}
        </div>
    );
}
