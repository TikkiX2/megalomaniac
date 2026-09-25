import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import EmptyState from '@/components/integrations/EmptyState';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import MainLayout from '@/layouts/main-layout';
import type { SharedData } from '@/types';
import type { FlashProps } from '@/types/integrations';

interface SourceRow {
    id: number;
    kind: string;
    name: string;
    config: Record<string, unknown>;
    enabled: boolean;
    connection_id: number | null;
    fetch_error: string | null;
    last_fetched_at: string | null;
}

interface SettingsProps {
    sources: SourceRow[];
    connections: { id: number; name: string; kind: string }[];
    digestHour: number;
    flash: FlashProps;
}

interface SourceForm {
    kind: 'rss' | 'hackernews' | 'reddit' | 'youtube';
    name: string;
    url: string;
    subreddit: string;
    sort: string;
    channel_ids: string;
    connection_id: string;
}

function blankForm(): SourceForm {
    return { kind: 'rss', name: '', url: '', subreddit: '', sort: 'hot', channel_ids: '', connection_id: '' };
}

export default function FeedSettings() {
    const { sources, connections, digestHour, flash } = usePage<SharedData & SettingsProps>().props;
    const form = useForm<SourceForm>(blankForm());
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        const config =
            form.data.kind === 'rss'
                ? { url: form.data.url }
                : form.data.kind === 'hackernews'
                  ? { url: 'https://news.ycombinator.com/rss' }
                  : form.data.kind === 'reddit'
                    ? { subreddit: form.data.subreddit, sort: form.data.sort }
                    : { channel_ids: form.data.channel_ids.split(',').map((id) => id.trim()).filter(Boolean) };

        setProcessing(true);
        router.post(
            '/feed/sources',
            {
                kind: form.data.kind,
                name: form.data.name,
                config,
                connection_id: form.data.connection_id || null,
                enabled: true,
            },
            {
                preserveScroll: true,
                onSuccess: () => form.setData(blankForm()),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const toggle = (source: SourceRow) => {
        router.patch(`/feed/sources/${source.id}`, { enabled: !source.enabled }, { preserveScroll: true });
    };

    const remove = (source: SourceRow) => {
        if (!window.confirm(`¿Eliminar la fuente "${source.name}"?`)) {
            return;
        }

        router.delete(`/feed/sources/${source.id}`, { preserveScroll: true });
    };

    const needsConnection = form.data.kind === 'reddit' || form.data.kind === 'youtube';

    return (
        <MainLayout>
            <Head title="Fuentes del feed" />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading
                        title="Fuentes del feed"
                        description={`Ingesta cada 30 min · digest diario a las ${digestHour}:00.`}
                    />
                    <Button variant="outline" asChild>
                        <Link href="/feed">Volver al feed</Link>
                    </Button>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-primary/30 bg-primary/10 p-3 text-sm text-primary">
                        {flash.success}
                    </div>
                )}

                <Card className="border-border bg-card">
                    <CardContent className="space-y-4 pt-4">
                        <p className="text-xs font-black uppercase tracking-widest text-muted-foreground">
                            Nueva fuente
                        </p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1">
                                <Label>Tipo</Label>
                                <Select
                                    value={form.data.kind}
                                    onValueChange={(value) => form.setData('kind', value as SourceForm['kind'])}
                                >
                                    <SelectTrigger className="bg-background border-border">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="rss">RSS / Atom</SelectItem>
                                        <SelectItem value="hackernews">Hacker News</SelectItem>
                                        <SelectItem value="reddit">Reddit</SelectItem>
                                        <SelectItem value="youtube">YouTube</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="source-name">Nombre</Label>
                                <Input
                                    id="source-name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    className="bg-background border-border"
                                />
                                {form.errors.name && <p className="text-xs text-destructive">{form.errors.name}</p>}
                            </div>

                            {form.data.kind === 'rss' && (
                                <div className="space-y-1 sm:col-span-2">
                                    <Label htmlFor="source-url">URL del feed</Label>
                                    <Input
                                        id="source-url"
                                        value={form.data.url}
                                        onChange={(e) => form.setData('url', e.target.value)}
                                        placeholder="https://blog.example/feed.xml"
                                        className="bg-background border-border"
                                    />
                                </div>
                            )}

                            {form.data.kind === 'reddit' && (
                                <>
                                    <div className="space-y-1">
                                        <Label htmlFor="source-subreddit">Subreddit</Label>
                                        <Input
                                            id="source-subreddit"
                                            value={form.data.subreddit}
                                            onChange={(e) => form.setData('subreddit', e.target.value)}
                                            placeholder="laravel"
                                            className="bg-background border-border"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label>Orden</Label>
                                        <Select value={form.data.sort} onValueChange={(value) => form.setData('sort', value)}>
                                            <SelectTrigger className="bg-background border-border">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="hot">Hot</SelectItem>
                                                <SelectItem value="new">New</SelectItem>
                                                <SelectItem value="top">Top</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </>
                            )}

                            {form.data.kind === 'youtube' && (
                                <div className="space-y-1 sm:col-span-2">
                                    <Label htmlFor="source-channels">IDs de canales (separados por coma)</Label>
                                    <Input
                                        id="source-channels"
                                        value={form.data.channel_ids}
                                        onChange={(e) => form.setData('channel_ids', e.target.value)}
                                        placeholder="UC1, UC2"
                                        className="bg-background border-border"
                                    />
                                </div>
                            )}

                            {needsConnection && (
                                <div className="space-y-1 sm:col-span-2">
                                    <Label>Conexión ({form.data.kind})</Label>
                                    <Select
                                        value={form.data.connection_id}
                                        onValueChange={(value) => form.setData('connection_id', value)}
                                    >
                                        <SelectTrigger className="bg-background border-border">
                                            <SelectValue placeholder="Elegí una conexión" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {connections
                                                .filter((connection) => connection.kind === form.data.kind)
                                                .map((connection) => (
                                                    <SelectItem key={connection.id} value={String(connection.id)}>
                                                        {connection.name}
                                                    </SelectItem>
                                                ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                        </div>

                        <div className="flex justify-end">
                            <Button onClick={submit} disabled={processing} className="bg-primary font-bold">
                                Agregar fuente
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {sources.length === 0 ? (
                    <EmptyState
                        title="Sin fuentes"
                        description="Agregá la primera para empezar a recibir items."
                    />
                ) : (
                    <div className="space-y-2">
                        {sources.map((source) => (
                            <Card key={source.id} className="border-border bg-card">
                                <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-4">
                                    <div>
                                        <p className="text-sm font-bold text-foreground">{source.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {source.kind}
                                            {source.enabled ? '' : ' · pausada'}
                                            {source.fetch_error ? ` · error: ${source.fetch_error}` : ''}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button size="sm" variant="ghost" onClick={() => toggle(source)}>
                                            {source.enabled ? 'Pausar' : 'Activar'}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive"
                                            onClick={() => remove(source)}
                                        >
                                            Eliminar
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
