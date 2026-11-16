import { useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import type { FormEvent, KeyboardEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import MoodPicker from './MoodPicker';

export interface JournalDayPayload {
    id: number;
    body: string | null;
    mood: number | null;
    tags: string[];
    is_private: boolean;
    locked: boolean;
}

interface DayFormData {
    body: string;
    mood: number | null;
    tags: string;
    is_private: boolean;
}

interface DayEditorProps {
    date: string;
    day: JournalDayPayload | null;
}

function tagsToInput(tags: string[] | null | undefined): string {
    return (tags ?? []).join(', ');
}

function tagsFromInput(value: string): string[] {
    return value
        .split(',')
        .map((tag) => tag.trim())
        .filter((tag) => tag !== '');
}

export default function DayEditor({ date, day }: DayEditorProps) {
    const locked = day?.locked === true;

    const form = useForm<DayFormData>({
        body: day?.body ?? '',
        mood: day?.mood ?? null,
        tags: tagsToInput(day?.tags),
        is_private: day?.is_private ?? false,
    });

    const submit = () => {
        if (locked || form.processing) {
            return;
        }

        form.transform((data) => ({
            body: data.body,
            mood: data.mood,
            tags: tagsFromInput(data.tags),
            is_private: data.is_private,
        }));

        form.put(`/diario/${date}`, {
            preserveScroll: true,
            onSuccess: (page) => {
                const saved = (page.props.day ??
                    null) as JournalDayPayload | null;

                form.setData({
                    body: saved?.body ?? '',
                    mood: saved?.mood ?? null,
                    tags: tagsToInput(saved?.tags),
                    is_private: saved?.is_private ?? false,
                });
                form.transform((data) => data);
            },
        });
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        submit();
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            submit();
        }
    };

    const error = Object.values(form.errors)[0];

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-5">
            {error && (
                <p
                    role="alert"
                    className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    {error}
                </p>
            )}

            {day === null && (
                <div className="flex flex-col gap-1">
                    <h2 className="text-lg font-semibold text-white/90">
                        ¿Qué querés recordar de hoy?
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Escribí una línea, elegí un ánimo o sumá etiquetas.
                    </p>
                </div>
            )}

            {locked && (
                <div className="flex items-center gap-2 rounded-md border border-border bg-card px-3 py-2">
                    <Lock
                        aria-hidden="true"
                        className="size-3.5 text-muted-foreground"
                    />
                    <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                        Privado — desbloqueá para leer
                    </p>
                </div>
            )}

            <div className="flex flex-col gap-2">
                <Label
                    htmlFor="journal-body"
                    className="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                >
                    Lo que pasó
                </Label>
                <Textarea
                    id="journal-body"
                    value={form.data.body}
                    readOnly={locked}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                    onKeyDown={handleKeyDown}
                    placeholder={
                        locked
                            ? 'Contenido privado'
                            : 'Escribí lo que quieras recordar…'
                    }
                    className="min-h-40 resize-y border-border bg-card text-[15px] leading-relaxed text-white/90"
                />
                {!locked && (
                    <p className="text-[11px] text-muted-foreground">
                        Ctrl + Enter para guardar.
                    </p>
                )}
            </div>

            <div className="flex flex-col gap-2">
                <span className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                    Ánimo
                </span>
                <fieldset
                    disabled={locked}
                    className="m-0 min-w-0 border-0 p-0"
                >
                    <MoodPicker
                        value={form.data.mood}
                        onChange={(mood) => form.setData('mood', mood)}
                    />
                </fieldset>
            </div>

            <div className="flex flex-col gap-2">
                <Label
                    htmlFor="journal-tags"
                    className="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                >
                    Etiquetas
                </Label>
                <Input
                    id="journal-tags"
                    value={form.data.tags}
                    disabled={locked}
                    onChange={(event) =>
                        form.setData('tags', event.target.value)
                    }
                    placeholder="casa, viaje, familia"
                    className="border-border bg-card text-sm"
                />
                <p className="text-[11px] text-muted-foreground">
                    Separadas por comas. Se guardan hasta ocho.
                </p>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                <label className="flex min-h-11 cursor-pointer items-center gap-2">
                    <Checkbox
                        checked={form.data.is_private}
                        disabled={locked}
                        onCheckedChange={(checked) =>
                            form.setData('is_private', checked === true)
                        }
                    />
                    <span className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                        Privado
                    </span>
                    <span className="text-[11px] text-muted-foreground">
                        Se guarda cifrado.
                    </span>
                </label>

                <div className="flex items-center gap-3">
                    {form.recentlySuccessful && day !== null && !locked && (
                        <span className="text-[11px] font-bold tracking-widest text-primary uppercase">
                            Guardado
                        </span>
                    )}
                    <Button
                        type="submit"
                        disabled={locked || form.processing}
                        className="min-h-11"
                    >
                        {form.processing ? 'Guardando…' : 'Guardar'}
                    </Button>
                </div>
            </div>
        </form>
    );
}
