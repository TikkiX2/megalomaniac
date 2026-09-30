import { Head, Link, router } from '@inertiajs/react';
import { Calendar, MessageCircle, Plus } from 'lucide-react';
import { useState } from 'react';
import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';
import EmptyState from '@/components/integrations/EmptyState';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import HealthLayout from '@/layouts/health-layout';
import health from '@/routes/health';
import type { ChatThread } from '@/types/chat';

interface HealthChatIndexProps {
    threads: ChatThread[];
    contextOptions: {
        conditions: { id: number; name: string }[];
        people: { id: number; first_name: string; last_name: string | null }[];
    };
}

function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('es-AR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

function contextLabel(thread: ChatThread): string {
    if (thread.context_label) return thread.context_label;
    if (thread.context_type?.includes('Person')) return 'Persona';
    if (thread.context_type?.includes('HealthCondition')) return 'Condición';
    return 'General';
}

export default function HealthChatsIndex({
    threads,
    contextOptions,
}: HealthChatIndexProps) {
    const [context, setContext] = useState('none');
    const [creating, setCreating] = useState(false);

    const { conditions, people } = contextOptions;

    const createChat = () => {
        const payload: { context_type?: string; context_id?: number } = {};

        if (context !== 'none') {
            const [type, id] = context.split(':');
            payload.context_type = type;
            payload.context_id = Number(id);
        }

        router.post(health.chats.store().url, payload, {
            onStart: () => setCreating(true),
            onFinish: () => setCreating(false),
        });
    };

    return (
        <HealthLayout>
            <Head title="Chats de salud" />
            <div className="flex h-full animate-in flex-col gap-6 p-4 duration-700 fade-in md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">
                            Chats de salud
                        </h1>
                        <p className="text-muted-foreground">
                            Consultas con el asistente vinculadas a tus
                            condiciones y personas.
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <Select value={context} onValueChange={setContext}>
                            <SelectTrigger className="border-border bg-card sm:w-72">
                                <SelectValue placeholder="Contexto" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    Sin contexto (general)
                                </SelectItem>
                                {conditions.length > 0 && (
                                    <SelectGroup>
                                        <SelectLabel>Condiciones</SelectLabel>
                                        {conditions.map((condition) => (
                                            <SelectItem
                                                key={`health_condition:${condition.id}`}
                                                value={`health_condition:${condition.id}`}
                                            >
                                                {condition.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                )}
                                {people.length > 0 && (
                                    <SelectGroup>
                                        <SelectLabel>Personas</SelectLabel>
                                        {people.map((person) => (
                                            <SelectItem
                                                key={`person:${person.id}`}
                                                value={`person:${person.id}`}
                                            >
                                                {`${person.first_name} ${person.last_name ?? ''}`.trim()}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                )}
                            </SelectContent>
                        </Select>
                        <Button
                            onClick={createChat}
                            disabled={creating}
                            className="bg-primary font-bold text-white"
                        >
                            <Plus className="mr-2 h-4 w-4" /> Nuevo chat de
                            salud
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-card">
                    {threads.length === 0 ? (
                        <div className="p-4">
                            <EmptyState
                                title="Sin chats de salud"
                                description="Crea un chat y vincúlalo a una condición o persona para consultar tu expediente."
                                action={
                                    <Button
                                        onClick={createChat}
                                        disabled={creating}
                                        className="bg-primary font-bold text-white"
                                    >
                                        <Plus className="mr-2 h-4 w-4" /> Nuevo
                                        chat de salud
                                    </Button>
                                }
                            />
                        </div>
                    ) : (
                        <ul className="divide-y divide-border">
                            {threads.map((thread) => (
                                <li key={thread.id}>
                                    <Link
                                        href={
                                            ChatController.show(thread.id).url
                                        }
                                        className="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-white/5"
                                    >
                                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary">
                                            <MessageCircle className="h-4 w-4" />
                                        </span>
                                        <span className="min-w-0 flex-1 truncate font-bold text-white">
                                            {thread.title}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className="shrink-0 border-primary/30 text-[10px] font-black text-primary uppercase"
                                        >
                                            {contextLabel(thread)}
                                        </Badge>
                                        <span className="hidden shrink-0 items-center gap-1 text-xs text-muted-foreground md:flex">
                                            <Calendar className="h-3 w-3" />{' '}
                                            {formatDateTime(thread.updated_at)}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </HealthLayout>
    );
}
