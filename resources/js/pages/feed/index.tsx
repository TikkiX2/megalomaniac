import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bookmark, ExternalLink, EyeOff, Newspaper, ThumbsDown, ThumbsUp } from 'lucide-react';
import EmptyState from '@/components/integrations/EmptyState';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import MainLayout from '@/layouts/main-layout';
import type { SharedData } from '@/types';
import type { FlashProps } from '@/types/integrations';

interface FeedItemRow {
    id: number;
    title: string;
    url: string;
    summary: string | null;
    source: string | null;
    source_kind: string | null;
    published_at: string | null;
    is_saved: boolean;
    score: number;
}

interface FeedIndexProps {
    digest: { content: string; sent_at: string | null } | null;
    items: FeedItemRow[];
    sources: { id: number; name: string; kind: string; enabled: boolean; fetch_error: string | null; last_fetched_at: string | null }[];
    tab: string;
    hasEmbeddings: boolean;
    flash: FlashProps;
}

function relative(value: string | null): string {
    if (!value) {
        return '';
    }

    const minutes = Math.round((Date.now() - new Date(value).getTime()) / 60000);

    if (minutes < 60) {
        return `hace ${Math.max(1, minutes)} min`;
    }

    return `hace ${Math.round(minutes / 60)} h`;
}

export default function FeedIndex() {
    const { digest, items, sources, tab, hasEmbeddings, flash } = usePage<SharedData & FeedIndexProps>().props;

    const signal = (item: FeedItemRow, type: 'like' | 'dislike' | 'save' | 'hide' | 'open') => {
        router.post(`/feed/items/${item.id}/signal`, { signal: type }, { preserveScroll: true, preserveState: true });
    };

    const open = (item: FeedItemRow) => {
        signal(item, 'open');
        window.open(item.url, '_blank', 'noopener');
    };

    return (
        <MainLayout>
            <Head title="Feed" />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading
                        title="Feed"
                        description={
                            hasEmbeddings
                                ? 'Rankeado con embeddings de tus gustos.'
                                : 'Rankeado por tus intereses (léxico + IA). Configurá un modelo de embeddings en Settings → IA para más precisión.'
                        }
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/feed/settings">Fuentes</Link>
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => router.post('/feed/digest', {}, { preserveScroll: true })}
                        >
                            Generar digest
                        </Button>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-primary/30 bg-primary/10 p-3 text-sm text-primary">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-xl border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
                        {flash.error}
                    </div>
                )}

                {digest && (
                    <Card className="border-primary/30 bg-primary/5">
                        <CardContent className="space-y-2 pt-4">
                            <p className="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-primary">
                                <Newspaper className="h-4 w-4" /> Digest del día
                            </p>
                            <pre className="whitespace-pre-wrap text-sm text-foreground">{digest.content}</pre>
                        </CardContent>
                    </Card>
                )}

                <div className="flex gap-2">
                    <Button size="sm" variant={tab === 'all' ? 'default' : 'ghost'} asChild>
                        <Link href="/feed">Todos</Link>
                    </Button>
                    <Button size="sm" variant={tab === 'saved' ? 'default' : 'ghost'} asChild>
                        <Link href="/feed?tab=saved">Guardados</Link>
                    </Button>
                </div>

                {sources.length === 0 ? (
                    <EmptyState
                        title="Sin fuentes"
                        description="Agregá un feed RSS, un subreddit, un canal de YouTube o Hacker News para empezar."
                        action={
                            <Button asChild className="bg-primary font-bold">
                                <Link href="/feed/settings">Configurar fuentes</Link>
                            </Button>
                        }
                    />
                ) : items.length === 0 ? (
                    <EmptyState
                        title="Sin items todavía"
                        description="Cuando la ingesta corra (cada 30 min) o generes el digest, los items van a aparecer acá."
                    />
                ) : (
                    <div className="space-y-3">
                        {items.map((item) => (
                            <Card key={item.id} className="border-border bg-card">
                                <CardContent className="space-y-2 pt-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <button
                                            type="button"
                                            onClick={() => open(item)}
                                            className="text-left text-sm font-bold text-foreground hover:text-primary"
                                        >
                                            {item.title}
                                        </button>
                                        <span className="shrink-0 text-[10px] tabular-nums text-muted-foreground">
                                            {item.score.toFixed(2)}
                                        </span>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {item.source} · {relative(item.published_at)}
                                    </p>
                                    {item.summary && (
                                        <p className="line-clamp-3 text-xs text-muted-foreground">{item.summary}</p>
                                    )}
                                    <div className="flex flex-wrap gap-1">
                                        <Button size="sm" variant="ghost" onClick={() => signal(item, 'like')} aria-label="Me gusta">
                                            <ThumbsUp className="h-4 w-4" />
                                        </Button>
                                        <Button size="sm" variant="ghost" onClick={() => signal(item, 'dislike')} aria-label="No me gusta">
                                            <ThumbsDown className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className={item.is_saved ? 'text-primary' : ''}
                                            onClick={() => signal(item, 'save')}
                                            aria-label="Guardar"
                                        >
                                            <Bookmark className="h-4 w-4" />
                                        </Button>
                                        <Button size="sm" variant="ghost" onClick={() => signal(item, 'hide')} aria-label="Ocultar">
                                            <EyeOff className="h-4 w-4" />
                                        </Button>
                                        <Button size="sm" variant="ghost" onClick={() => open(item)} aria-label="Abrir">
                                            <ExternalLink className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </MainLayout>
    );
}
