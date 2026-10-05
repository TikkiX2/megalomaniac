import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, ExternalLink, Loader2, Maximize2, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Lightbox from '@/components/inspiration/Lightbox';
import MasonryGrid from '@/components/inspiration/MasonryGrid';
import { sourceColorClass, sourceLabel, type DownloadState, type SavedMoodboardItem } from '@/components/inspiration/shared';
import { Button } from '@/components/ui/button';
import MainLayout from '@/layouts/main-layout';
import { csrfHeaders } from '@/lib/csrf';
import inspiration from '@/routes/inspiration';
import type { SharedData } from '@/types';

interface MoodboardProps {
    board: { id: number; name: string; project_name: string | null };
    items: SavedMoodboardItem[];
    total: number;
    sources: Record<string, number>;
}

export default function Moodboard() {
    const {
        board,
        items = [],
        total = 0,
        sources = {},
    } = usePage<SharedData & MoodboardProps>().props;

    const [downloads, setDownloads] = useState<Record<number, DownloadState>>({});
    const [error, setError] = useState<string | null>(null);
    const [lightbox, setLightbox] = useState<{ index: number } | null>(null);
    // Kept outside `lightbox` so the origin element survives the render that
    // closes the viewer and the focus can actually be restored.
    const [lightboxOrigin, setLightboxOrigin] = useState<HTMLElement | null>(null);

    const download = async (item: SavedMoodboardItem) => {
        setError(null);
        setDownloads((previous) => ({ ...previous, [item.id]: 'downloading' }));

        try {
            const response = await fetch(inspiration.saved.download.url(item.id), {
                method: 'POST',
                headers: { Accept: 'application/json', ...csrfHeaders() },
            });

            if (response.status === 200) {
                setDownloads((previous) => ({ ...previous, [item.id]: 'idle' }));
                router.reload({ only: ['items', 'total', 'sources'] });

                return;
            }

            if (response.status === 202) {
                setDownloads((previous) => ({ ...previous, [item.id]: 'queued' }));

                return;
            }

            if (response.status === 429) {
                const data = (await response.json()) as { message?: string };
                setError(data.message ?? 'Límite diario de descargas alcanzado.');
                setDownloads((previous) => ({ ...previous, [item.id]: 'failed' }));

                return;
            }

            setDownloads((previous) => ({ ...previous, [item.id]: 'failed' }));
        } catch {
            setDownloads((previous) => ({ ...previous, [item.id]: 'failed' }));
        }
    };

    const remove = async (item: SavedMoodboardItem) => {
        setError(null);

        try {
            const response = await fetch(inspiration.saved.destroy.url(item.id), {
                method: 'DELETE',
                headers: { Accept: 'application/json', ...csrfHeaders() },
            });

            if (response.ok || response.status === 204) {
                router.reload({ only: ['items', 'total', 'sources'] });
            } else {
                setError('No se pudo quitar la imagen.');
            }
        } catch {
            setError('No se pudo conectar con el servidor.');
        }
    };

    return (
        <MainLayout>
            <Head title={board.name} />

            <div className="mx-auto w-full max-w-6xl space-y-5 px-4 py-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div className="space-y-1">
                        <Link
                            href={inspiration.explore()}
                            className="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-widest text-muted-foreground hover:text-primary"
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            Inspiración
                        </Link>
                        <h1 className="text-2xl font-black tracking-tight text-foreground">{board.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {board.project_name ? `${board.project_name} · ` : ''}
                            {total} {total === 1 ? 'imagen' : 'imágenes'}
                        </p>
                    </div>

                    {Object.keys(sources).length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {Object.entries(sources).map(([key, count]) => (
                                <span
                                    key={key}
                                    className="rounded-full border border-border bg-card px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-muted-foreground"
                                >
                                    {sourceLabel(key)} · {count}
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                {error && (
                    <div className="rounded-xl border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
                        {error}
                    </div>
                )}

                {items.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border bg-card/50 px-6 py-12 text-center">
                        <p className="text-sm font-bold uppercase tracking-widest text-foreground">
                            Tu moodboard está vacío
                        </p>
                        <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                            Guardá referencias desde Explorar para armar el muro del proyecto.
                        </p>
                        <Button asChild className="mt-3">
                            <Link href={inspiration.explore()}>Volver a Explorar</Link>
                        </Button>
                    </div>
                ) : (
                    <MasonryGrid>
                        {items.map((item, itemIndex) => {
                            const state = downloads[item.id] ?? 'idle';
                            const isDownloading = state === 'downloading';
                            const isQueued = state === 'queued';
                            const isFailed = state === 'failed' || item.download_status === 'failed';
                            const isFull = item.download_status === 'full' && item.full_url !== null;

                            return (
                                <article
                                    key={item.id}
                                    className="group relative mb-3 break-inside-avoid overflow-hidden rounded-xl border border-border bg-card"
                                >
                                    <div className="relative">
                                        <img
                                            src={item.thumb_url}
                                            alt={item.title ?? `${sourceLabel(item.source)} image`}
                                            loading="lazy"
                                            className="h-auto w-full bg-muted object-cover"
                                        />

                                        <span
                                            className={`absolute left-2 top-2 rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-widest shadow-sm ${sourceColorClass(item.source)}`}
                                        >
                                            {sourceLabel(item.source)}
                                        </span>

                                        {item.download_status === 'full' && (
                                            <span className="absolute right-2 top-2 rounded-md bg-emerald-600/90 px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-white">
                                                Full
                                            </span>
                                        )}

                                        {item.download_status === 'failed' && (
                                            <span className="absolute right-2 top-2 rounded-md bg-destructive px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-white">
                                                Error
                                            </span>
                                        )}

                                        <button
                                            type="button"
                                            onClick={(event) => {
                                                setLightboxOrigin(event.currentTarget);
                                                setLightbox({ index: itemIndex });
                                            }}
                                            aria-label="Expandir imagen"
                                            className="absolute inset-0 flex cursor-zoom-in items-center justify-center opacity-0 transition-opacity focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary group-hover:opacity-100 group-focus-within:opacity-100"
                                        >
                                            <span className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-black/60 text-white">
                                                <Maximize2 className="h-5 w-5" />
                                            </span>
                                        </button>
                                    </div>

                                    <div className="space-y-2 p-3">
                                        {(item.title || item.author) && (
                                            <div>
                                                {item.title && (
                                                    <p className="line-clamp-2 text-xs font-semibold text-foreground">
                                                        {item.title}
                                                    </p>
                                                )}
                                                {item.author && (
                                                    <p className="truncate text-[10px] text-muted-foreground">
                                                        {item.author}
                                                    </p>
                                                )}
                                            </div>
                                        )}

                                        {item.tags.length > 0 && (
                                            <div className="flex flex-wrap gap-1">
                                                {item.tags.slice(0, 4).map((tag) => (
                                                    <span
                                                        key={tag}
                                                        className="rounded-full bg-muted px-1.5 py-0.5 text-[9px] text-muted-foreground"
                                                    >
                                                        {tag}
                                                    </span>
                                                ))}
                                            </div>
                                        )}

                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <a
                                                href={item.page_url}
                                                target="_blank"
                                                rel="noopener"
                                                className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-[11px] font-bold text-muted-foreground hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                aria-label="Abrir original"
                                            >
                                                <ExternalLink className="h-3.5 w-3.5" />
                                                Original
                                            </a>

                                            {isFull && item.full_url && (
                                                <a
                                                    href={item.full_url}
                                                    target="_blank"
                                                    rel="noopener"
                                                    className="inline-flex items-center gap-1 rounded-md border border-emerald-500/40 px-2 py-1 text-[11px] font-bold text-emerald-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                    aria-label="Abrir imagen en tamaño completo"
                                                >
                                                    <Download className="h-3.5 w-3.5" />
                                                    Full
                                                </a>
                                            )}

                                            {!isFull && !isDownloading && !isQueued && !isFailed && (
                                                <button
                                                    type="button"
                                                    onClick={() => download(item)}
                                                    className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-[11px] font-bold text-muted-foreground hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                    aria-label="Descargar en tamaño completo"
                                                >
                                                    <Download className="h-3.5 w-3.5" />
                                                    Descargar
                                                </button>
                                            )}

                                            {isDownloading && (
                                                <span className="inline-flex items-center gap-1 px-2 py-1 text-[11px] text-muted-foreground">
                                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                    Descargando…
                                                </span>
                                            )}

                                            {isQueued && (
                                                <span className="inline-flex items-center gap-1 rounded-md border border-amber-500/40 px-2 py-1 text-[11px] font-bold text-amber-300">
                                                    En cola
                                                    <button
                                                        type="button"
                                                        onClick={() => router.reload({ only: ['items', 'total', 'sources'] })}
                                                        aria-label="Refrescar estado de descarga"
                                                        className="focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                    >
                                                        <RefreshCw className="h-3.5 w-3.5" />
                                                    </button>
                                                </span>
                                            )}

                                            {isFailed && !isDownloading && (
                                                <button
                                                    type="button"
                                                    onClick={() => download(item)}
                                                    className="inline-flex items-center gap-1 rounded-md border border-destructive/50 px-2 py-1 text-[11px] font-bold text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                    aria-label="Reintentar descarga"
                                                >
                                                    <RefreshCw className="h-3.5 w-3.5" />
                                                    Reintentar
                                                </button>
                                            )}

                                            <button
                                                type="button"
                                                onClick={() => remove(item)}
                                                className="ml-auto inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                aria-label="Quitar del moodboard"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            );
                        })}
                    </MasonryGrid>
                )}
            </div>

            <Lightbox
                items={items}
                index={lightbox?.index ?? null}
                onClose={() => setLightbox(null)}
                onNavigate={(next) =>
                    setLightbox((current) => (current ? { ...current, index: next } : current))
                }
                onDownload={(item) => void download(item as SavedMoodboardItem)}
                downloadStates={downloads}
                onRefresh={() => router.reload({ only: ['items', 'total', 'sources'] })}
                returnFocusTo={lightboxOrigin}
            />
        </MainLayout>
    );
}
