import { Lock, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

export interface JournalMomentPerson {
    id: number;
    name: string;
}

export interface JournalMomentPhoto {
    id: number;
    url: string;
}

export interface JournalMomentPayload {
    id: number;
    occurred_at: string;
    title: string | null;
    body: string;
    tags: string[];
    is_private: boolean;
    people: JournalMomentPerson[];
    photos: JournalMomentPhoto[];
}

interface MomentCardProps {
    moment: JournalMomentPayload;
    onEdit: (moment: JournalMomentPayload) => void;
    onDelete: (moment: JournalMomentPayload) => void;
}

function formatTime(occurredAt: string): string {
    const date = new Date(occurredAt);

    return `${String(date.getHours()).padStart(2, '0')}:${String(
        date.getMinutes(),
    ).padStart(2, '0')}`;
}

export default function MomentCard({
    moment,
    onEdit,
    onDelete,
}: MomentCardProps) {
    return (
        <article className="group flex gap-2">
            <time
                dateTime={moment.occurred_at}
                className="w-12 shrink-0 pt-0.5 text-right text-[11px] font-bold tracking-widest text-muted-foreground tabular-nums"
            >
                {formatTime(moment.occurred_at)}
            </time>

            <div className="relative flex w-6 shrink-0 justify-center">
                <span
                    aria-hidden="true"
                    className="absolute inset-y-0 w-px bg-border"
                />
                <span
                    aria-hidden="true"
                    className="relative mt-1.5 size-2.5 rounded-full border-2 border-primary bg-background"
                />
            </div>

            <div className="min-w-0 flex-1 pb-6">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        {moment.title !== null && moment.title !== '' && (
                            <h3 className="text-sm font-semibold text-white/90">
                                {moment.title}
                            </h3>
                        )}

                        {moment.is_private && (
                            <p className="mt-0.5 flex items-center gap-1 text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                <Lock aria-hidden="true" className="size-3" />
                                Privado
                            </p>
                        )}
                    </div>

                    <div className="flex shrink-0 items-center">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-11 text-muted-foreground hover:text-white/90"
                            aria-label="Editar momento"
                            onClick={() => onEdit(moment)}
                        >
                            <Pencil aria-hidden="true" className="size-3.5" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-11 text-muted-foreground hover:text-destructive"
                            aria-label="Eliminar momento"
                            onClick={() => onDelete(moment)}
                        >
                            <Trash2 aria-hidden="true" className="size-3.5" />
                        </Button>
                    </div>
                </div>

                <p className="mt-1 text-[15px] leading-relaxed whitespace-pre-wrap text-white/80">
                    {moment.body}
                </p>

                {(moment.tags.length > 0 || moment.people.length > 0) && (
                    <ul className="mt-2 flex flex-wrap gap-1.5">
                        {moment.tags.map((tag) => (
                            <li
                                key={`tag-${tag}`}
                                className="rounded-full border border-border px-2 py-0.5 text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                #{tag}
                            </li>
                        ))}
                        {moment.people.map((person) => (
                            <li
                                key={`person-${person.id}`}
                                className="rounded-full border border-primary/30 bg-primary/10 px-2 py-0.5 text-[10px] font-black tracking-widest text-primary uppercase"
                            >
                                @{person.name}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </article>
    );
}
