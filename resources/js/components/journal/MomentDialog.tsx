import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import PhotoStrip from '@/components/journal/PhotoStrip';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { JournalMomentPayload, JournalMomentPerson } from './MomentCard';

interface MomentFormData {
    title: string;
    body: string;
    occurred_at: string;
    tags: string;
    is_private: boolean;
    person_ids: number[];
}

interface MomentDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    date: string;
    moment: JournalMomentPayload | null;
    people: JournalMomentPerson[];
}

function tagsFromInput(value: string): string[] {
    return value
        .split(',')
        .map((tag) => tag.trim())
        .filter((tag) => tag !== '');
}

function toLocalInput(occurredAt: string | null | undefined): string {
    if (occurredAt === null || occurredAt === undefined || occurredAt === '') {
        return '';
    }

    const date = new Date(occurredAt);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
        date.getDate(),
    )}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function MomentDialog({
    open,
    onOpenChange,
    date,
    moment,
    people,
}: MomentDialogProps) {
    const form = useForm<MomentFormData>({
        title: moment?.title ?? '',
        body: moment?.body ?? '',
        occurred_at: toLocalInput(moment?.occurred_at),
        tags: (moment?.tags ?? []).join(', '),
        is_private: moment?.is_private ?? false,
        person_ids: (moment?.people ?? []).map((person) => person.id),
    });

    const togglePerson = (personId: number, checked: boolean) => {
        const next = checked
            ? [...form.data.person_ids, personId]
            : form.data.person_ids.filter((id) => id !== personId);

        form.setData('person_ids', next);
    };

    const submit = () => {
        if (form.processing) {
            return;
        }

        form.transform((data) => ({
            title: data.title.trim() === '' ? null : data.title,
            body: data.body,
            occurred_at:
                data.occurred_at === ''
                    ? null
                    : new Date(data.occurred_at).toISOString(),
            tags: tagsFromInput(data.tags),
            is_private: data.is_private,
            person_ids: data.person_ids,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (moment === null) {
            form.post(`/diario/${date}/momentos`, options);
        } else {
            form.patch(`/diario/momentos/${moment.id}`, options);
        }
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        submit();
    };

    const error = Object.values(form.errors)[0];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-border bg-card sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {moment === null ? 'Nuevo momento' : 'Editar momento'}
                    </DialogTitle>
                    <DialogDescription>
                        Una escena suelta del día: qué pasó y a qué hora.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    {error && (
                        <p
                            role="alert"
                            className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                        >
                            {error}
                        </p>
                    )}

                    <div className="flex flex-col gap-2">
                        <Label
                            htmlFor="moment-title"
                            className="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                        >
                            Título
                        </Label>
                        <Input
                            id="moment-title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            placeholder="Café con Ana"
                            className="border-border bg-background text-sm"
                        />
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label
                            htmlFor="moment-body"
                            className="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                        >
                            Qué pasó
                        </Label>
                        <Textarea
                            id="moment-body"
                            required
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            placeholder="Escribí el momento…"
                            className="min-h-28 resize-y border-border bg-background text-[15px] leading-relaxed"
                        />
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label
                            htmlFor="moment-occurred-at"
                            className="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                        >
                            Hora
                        </Label>
                        <Input
                            id="moment-occurred-at"
                            type="datetime-local"
                            value={form.data.occurred_at}
                            onChange={(event) =>
                                form.setData('occurred_at', event.target.value)
                            }
                            className="border-border bg-background text-sm"
                        />
                        <p className="text-[11px] text-muted-foreground">
                            Si lo dejás vacío se usa ahora, o el mediodía en
                            días pasados.
                        </p>
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label
                            htmlFor="moment-tags"
                            className="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                        >
                            Etiquetas
                        </Label>
                        <Input
                            id="moment-tags"
                            value={form.data.tags}
                            onChange={(event) =>
                                form.setData('tags', event.target.value)
                            }
                            placeholder="casa, tarde, familia"
                            className="border-border bg-background text-sm"
                        />
                    </div>

                    <div className="flex flex-col gap-2">
                        <span className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                            Personas
                        </span>
                        {people.length === 0 ? (
                            <p className="text-[11px] text-muted-foreground">
                                Todavía no cargaste personas.{' '}
                                <Link
                                    href="/people"
                                    className="text-primary underline-offset-2 hover:underline"
                                >
                                    Agregar una
                                </Link>
                            </p>
                        ) : (
                            <div
                                role="group"
                                aria-label="Personas del momento"
                                className="max-h-44 overflow-y-auto rounded-md border border-border bg-background"
                            >
                                {people.map((person) => (
                                    <label
                                        key={person.id}
                                        className="flex min-h-11 cursor-pointer items-center gap-2 px-3 hover:bg-white/5"
                                    >
                                        <Checkbox
                                            checked={form.data.person_ids.includes(
                                                person.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                togglePerson(
                                                    person.id,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <span className="truncate text-sm text-white/90">
                                            {person.name}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        )}
                    </div>

                    <label className="flex min-h-11 cursor-pointer items-center gap-2">
                        <Checkbox
                            checked={form.data.is_private}
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

                    {moment !== null && (
                        <div className="flex flex-col gap-2">
                            <span className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                Fotos
                            </span>
                            <PhotoStrip
                                momentId={moment.id}
                                photos={moment.photos}
                            />
                        </div>
                    )}

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                            className="min-h-11"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="min-h-11"
                        >
                            {form.processing
                                ? 'Guardando…'
                                : moment === null
                                  ? 'Agregar'
                                  : 'Guardar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
