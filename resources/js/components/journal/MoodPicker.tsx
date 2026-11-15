import { cn } from '@/lib/utils';

export const MOODS = [
    { value: 1, label: 'Bajo' },
    { value: 2, label: 'Flojo' },
    { value: 3, label: 'Normal' },
    { value: 4, label: 'Bien' },
    { value: 5, label: 'Muy bien' },
] as const;

export type MoodValue = (typeof MOODS)[number]['value'];

interface MoodPickerProps {
    value: number | null;
    onChange: (value: number | null) => void;
}

export default function MoodPicker({ value, onChange }: MoodPickerProps) {
    return (
        <div
            className="flex flex-wrap gap-2"
            role="group"
            aria-label="Ánimo del día"
        >
            {MOODS.map((mood) => {
                const active = value === mood.value;

                return (
                    <button
                        key={mood.value}
                        type="button"
                        aria-pressed={active}
                        onClick={() => onChange(active ? null : mood.value)}
                        className={cn(
                            'min-h-11 rounded-full border px-3 text-xs font-black tracking-widest uppercase transition-colors outline-none focus-visible:ring-3 focus-visible:ring-primary/40',
                            active
                                ? 'border-primary bg-primary/15 text-primary ring-2 ring-primary/40'
                                : 'border-border text-muted-foreground hover:border-primary/40 hover:text-white/90',
                        )}
                    >
                        {mood.label}
                    </button>
                );
            })}
        </div>
    );
}
