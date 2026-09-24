import { Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';
import { ThreadItem } from '@/components/ai/chat/ThreadItem';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { THREAD_GROUP_ORDER, threadGroup } from '@/lib/relative-date';
import type { ChatThread } from '@/types/chat';

interface ThreadRailProps {
    threads: ChatThread[];
    activeThreadId: string | null;
}

export function ThreadRail({ threads, activeThreadId }: ThreadRailProps) {
    const [query, setQuery] = useState('');

    const groups = useMemo(() => {
        const normalized = query.trim().toLowerCase();
        const filtered =
            normalized === ''
                ? threads
                : threads.filter((thread) => thread.title.toLowerCase().includes(normalized));

        return THREAD_GROUP_ORDER.map((group) => ({
            group,
            items: filtered.filter((thread) => threadGroup(thread.updated_at, thread.is_pinned) === group),
        })).filter((entry) => entry.items.length > 0);
    }, [threads, query]);

    return (
        <div className="flex h-full flex-col">
            <div className="space-y-2 p-3">
                <Button asChild className="w-full justify-start gap-2 bg-primary text-primary-foreground hover:bg-primary/90">
                    <Link href={ChatController.index().url}>
                        <Plus className="h-4 w-4" />
                        Nuevo hilo
                    </Link>
                </Button>

                <div className="relative">
                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Buscar hilos…"
                        aria-label="Buscar hilos"
                        className="h-8 border-border bg-card pl-8 text-xs"
                    />
                </div>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-3">
                {groups.length === 0 && (
                    <p className="px-2 py-6 text-center text-xs text-muted-foreground">
                        {threads.length === 0 ? 'Aún no tienes hilos. Empieza una conversación.' : 'Sin resultados.'}
                    </p>
                )}

                {groups.map(({ group, items }) => (
                    <div key={group} className="mb-3">
                        <p className="px-2 py-1 text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                            {group}
                        </p>
                        <div className="space-y-0.5">
                            {items.map((thread) => (
                                <ThreadItem key={thread.id} thread={thread} active={thread.id === activeThreadId} />
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
