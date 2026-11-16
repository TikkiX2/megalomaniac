import { useForm } from '@inertiajs/react';
import { ChevronDown, Sparkles } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';

/**
 * A weekly reflection exactly as `ReviewController` serializes it.
 */
export interface JournalReflectionData {
    body: string;
    source: 'ai' | 'fallback';
    model: string | null;
    generated_at: string | null;
    week_start: string;
}

interface ReflectionCardProps {
    reflection: JournalReflectionData | null;
    weekStart: string;
}

const MONTHS = [
    'ene',
    'feb',
    'mar',
    'abr',
    'may',
    'jun',
    'jul',
    'ago',
    'sep',
    'oct',
    'nov',
    'dic',
];

function weekRange(weekStart: string): string {
    const [year, month, day] = weekStart.split('-').map(Number);

    if (!year || !month || !day) {
        return weekStart;
    }

    const start = new Date(year, month - 1, day);
    const end = new Date(year, month - 1, day + 6);
    const startDay = start.getDate();
    const endDay = end.getDate();

    if (start.getMonth() === end.getMonth()) {
        return `${startDay}–${endDay} ${MONTHS[end.getMonth()]}`;
    }

    return `${startDay} ${MONTHS[start.getMonth()]}–${endDay} ${MONTHS[end.getMonth()]}`;
}

function generatedLabel(generatedAt: string | null): string | null {
    if (generatedAt === null) {
        return null;
    }

    const date = new Date(generatedAt);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleDateString('es-AR', {
        day: 'numeric',
        month: 'short',
    });
}

function SourceBadge({ reflection }: { reflection: JournalReflectionData }) {
    const isAi = reflection.source === 'ai';

    return (
        <Badge
            variant="outline"
            className={cn(
                'text-[10px] font-black tracking-widest uppercase',
                isAi
                    ? 'border-primary/40 bg-primary/10 text-primary'
                    : 'border-border bg-background/60 text-muted-foreground',
            )}
        >
            {isAi
                ? reflection.model
                    ? `IA · ${reflection.model}`
                    : 'IA'
                : 'Sin IA'}
        </Badge>
    );
}

function ReflectionBody({ reflection }: { reflection: JournalReflectionData }) {
    return (
        <p className="mt-3 text-[15px] leading-relaxed whitespace-pre-line text-white/80">
            {reflection.body}
        </p>
    );
}

export default function ReflectionCard({
    reflection,
    weekStart,
}: ReflectionCardProps) {
    const { post, processing, errors } = useForm<{ week_start: string }>({
        week_start: weekStart,
    });

    const generate = () => {
        post('/diario/reflexiones', { preserveScroll: true });
    };

    const regenerate = () => {
        if (!window.confirm('¿Regenerar la reflexión de esta semana?')) {
            return;
        }

        generate();
    };

    const generated = reflection
        ? generatedLabel(reflection.generated_at)
        : null;

    return (
        <section aria-labelledby="review-reflection" className="mb-10">
            <h2
                id="review-reflection"
                className="mb-2 flex items-center gap-2 px-1 text-[10px] font-black tracking-widest text-muted-foreground uppercase"
            >
                <Sparkles aria-hidden="true" className="size-3.5" />
                Reflexión semanal
            </h2>

            <div className="rounded-lg border border-border bg-card/40 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-[11px] font-bold tracking-wide text-muted-foreground uppercase">
                        {weekRange(weekStart)}
                    </p>

                    {reflection !== null && (
                        <SourceBadge reflection={reflection} />
                    )}
                </div>

                {reflection === null ? (
                    <>
                        <p className="mt-2 text-[13px] text-muted-foreground">
                            Resumí lo que escribiste esta semana, con tus
                            memorias públicas.
                        </p>
                        <Button
                            type="button"
                            className="mt-3 min-h-11"
                            onClick={generate}
                            disabled={processing}
                        >
                            {processing ? 'Resumiendo…' : 'Resumir esta semana'}
                        </Button>
                    </>
                ) : (
                    <>
                        <ReflectionBody reflection={reflection} />

                        <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                            {generated !== null ? (
                                <p className="text-[11px] text-muted-foreground">
                                    Generada el {generated}
                                </p>
                            ) : (
                                <span />
                            )}

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="min-h-11"
                                onClick={regenerate}
                                disabled={processing}
                            >
                                {processing ? 'Regenerando…' : 'Regenerar'}
                            </Button>
                        </div>
                    </>
                )}

                {errors.week_start && (
                    <p className="mt-2 text-[13px] text-destructive">
                        {errors.week_start}
                    </p>
                )}
            </div>
        </section>
    );
}

export function ReflectionHistory({
    reflections,
}: {
    reflections: JournalReflectionData[];
}) {
    if (reflections.length === 0) {
        return null;
    }

    return (
        <Collapsible className="mb-10">
            <CollapsibleTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    className="min-h-11 w-full justify-between px-1 text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                >
                    {reflections.length === 1
                        ? 'Historial · última semana'
                        : `Historial · últimas ${reflections.length} semanas`}
                    <ChevronDown aria-hidden="true" className="size-4" />
                </Button>
            </CollapsibleTrigger>

            <CollapsibleContent>
                <ul className="mt-2 flex flex-col gap-2">
                    {reflections.map((reflection) => (
                        <li
                            key={reflection.week_start}
                            className="rounded-lg border border-border bg-card/40 p-3"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="text-[11px] font-bold tracking-wide text-muted-foreground uppercase">
                                    {weekRange(reflection.week_start)}
                                </p>
                                <SourceBadge reflection={reflection} />
                            </div>
                            <ReflectionBody reflection={reflection} />
                        </li>
                    ))}
                </ul>
            </CollapsibleContent>
        </Collapsible>
    );
}
