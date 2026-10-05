import { Bookmark, BookmarkCheck, ChevronLeft, ChevronRight, Download, ExternalLink, ImageOff, Loader2, RefreshCw, X } from 'lucide-react';
import { useEffect, useRef, useState, type KeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import inspiration from '@/routes/inspiration';
import {
    sourceColorClass,
    sourceLabel,
    type DownloadState,
    type LightboxItem,
} from './shared';

interface LightboxProps {
    /** Items of the grid that is currently visible; navigation stays inside it. */
    items: LightboxItem[];
    /** Index of the open item, or null when the viewer is closed. */
    index: number | null;
    onClose: () => void;
    onNavigate: (index: number) => void;
    /** Explore only: reopens the save dialog for the current item. */
    onSave?: (item: LightboxItem) => void;
    /** Explore only: moodboard id when the item is already saved. */
    savedBoardIdFor?: (item: LightboxItem) => number | undefined;
    /** Moodboard only: requests the full-size download of a saved item. */
    onDownload?: (item: LightboxItem) => void;
    /** Moodboard only: in-flight download states keyed by saved image id. */
    downloadStates?: Record<number, DownloadState>;
    /** Moodboard only: re-fetches the persisted download status. */
    onRefresh?: () => void;
    /** Element focused before opening; focus returns here on close. */
    returnFocusTo?: HTMLElement | null;
}

const FOCUSABLE =
    'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';

/**
 * Full-screen image viewer shared by the explore wall and the moodboard wall.
 *
 * Renders in a portal over a darkened page, locks body scroll and keeps focus
 * inside the dialog while it is open (Escape closes, ←/→ move within the grid).
 * On close, focus is returned to the card that opened it.
 */
export default function Lightbox({
    items,
    index,
    onClose,
    onNavigate,
    onSave,
    savedBoardIdFor,
    onDownload,
    downloadStates,
    onRefresh,
    returnFocusTo,
}: LightboxProps) {
    const open = index !== null && items.length > 0;

    const panelRef = useRef<HTMLDivElement>(null);
    const wasOpen = useRef(false);
    const [imageFailed, setImageFailed] = useState(false);

    const go = (delta: number) => {
        if (index === null || items.length < 2) {
            return;
        }

        // Clear a previous load error here so every navigation path (buttons and
        // arrow keys) lets the next image run its own `onError`.
        setImageFailed(false);
        onNavigate((index + delta + items.length) % items.length);
    };

    // Block page scroll while the viewer is mounted.
    useEffect(() => {
        if (!open) {
            return;
        }

        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, [open]);

    // Global keys: Escape closes, arrows navigate the current grid.
    useEffect(() => {
        if (!open) {
            return;
        }

        const handler = (event: globalThis.KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                go(-1);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                go(1);
            }
        };

        window.addEventListener('keydown', handler);

        return () => window.removeEventListener('keydown', handler);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, index, items.length, onClose, onNavigate]);

    // Move focus into the dialog on open and back to the origin on close.
    useEffect(() => {
        if (open && !wasOpen.current) {
            wasOpen.current = true;
            setImageFailed(false);
            requestAnimationFrame(() => panelRef.current?.focus());
        } else if (!open && wasOpen.current) {
            wasOpen.current = false;
            returnFocusTo?.focus();
        }
    }, [open, returnFocusTo]);

    if (index === null || items.length === 0) {
        return null;
    }

    const item = items[index];

    // The grid can shrink underneath an open viewer (e.g. a background reload):
    // never dereference a stale index.
    if (!item) {
        return null;
    }

    const savedBoardId = savedBoardIdFor?.(item);
    const downloadState: DownloadState =
        item.id !== undefined ? (downloadStates?.[item.id] ?? 'idle') : 'idle';
    const isFull = item.download_status === 'full' && item.full_url !== null;
    const isFailed = downloadState === 'failed' || item.download_status === 'failed';
    const maturityLabel =
        item.maturity && !['safe', 'sfw'].includes(item.maturity) ? item.maturity : null;

    const trapFocus = (event: KeyboardEvent<HTMLDivElement>) => {
        if (event.key !== 'Tab' || panelRef.current === null) {
            return;
        }

        const nodes = Array.from(panelRef.current.querySelectorAll<HTMLElement>(FOCUSABLE));

        if (nodes.length === 0) {
            event.preventDefault();

            return;
        }

        const first = nodes[0];
        const last = nodes[nodes.length - 1];
        const active = document.activeElement;

        // The dialog root itself receives focus on open but is not a focusable
        // node; a backward Tab from it (or any focus left outside) would escape
        // the aria-modal overlay. Pull it back to the matching edge.
        if (active === panelRef.current || !panelRef.current.contains(active)) {
            event.preventDefault();
            (event.shiftKey ? last : first).focus();

            return;
        }

        if (event.shiftKey && active === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    };

    return createPortal(
        <div
            ref={panelRef}
            role="dialog"
            aria-modal="true"
            aria-label={item.title ?? `Imagen de ${sourceLabel(item.source)}`}
            tabIndex={-1}
            onKeyDown={trapFocus}
            className="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-0 outline-none duration-200 sm:p-6"
        >
            <div className="relative flex h-full w-full max-w-6xl flex-col overflow-hidden sm:max-h-[90vh] sm:flex-row sm:rounded-2xl sm:border sm:border-border sm:bg-card sm:shadow-2xl">
                <button
                    type="button"
                    onClick={onClose}
                    className="absolute right-3 top-3 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-black/60 text-white transition-colors hover:bg-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    aria-label="Cerrar visor"
                >
                    <X className="h-5 w-5" />
                </button>

                <div className="relative flex min-h-0 flex-1 items-center justify-center bg-black/40 p-3 sm:p-6">
                    {imageFailed ? (
                        <div className="flex flex-col items-center gap-2 p-10 text-center">
                            <ImageOff className="h-8 w-8 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                No se pudo cargar la imagen completa.
                            </p>
                        </div>
                    ) : (
                        <img
                            key={`${item.source}:${item.source_id}`}
                            src={item.image_url}
                            alt={item.title ?? `${sourceLabel(item.source)} image`}
                            onError={() => setImageFailed(true)}
                            className="max-h-[45vh] w-auto max-w-full object-contain sm:max-h-[85vh]"
                        />
                    )}

                    {items.length > 1 && (
                        <>
                            <button
                                type="button"
                                onClick={() => go(-1)}
                                className="absolute left-2 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/60 text-white transition-colors hover:bg-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:left-4"
                                aria-label="Imagen anterior"
                            >
                                <ChevronLeft className="h-6 w-6" />
                            </button>
                            <button
                                type="button"
                                onClick={() => go(1)}
                                className="absolute right-2 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/60 text-white transition-colors hover:bg-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:right-4"
                                aria-label="Imagen siguiente"
                            >
                                <ChevronRight className="h-6 w-6" />
                            </button>
                            <span className="absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-3 py-0.5 text-[11px] font-bold tabular-nums text-white/90">
                                {index + 1} / {items.length}
                            </span>
                        </>
                    )}
                </div>

                <div className="w-full shrink-0 space-y-4 overflow-y-auto border-t border-border bg-card p-4 sm:w-80 sm:border-l sm:border-t-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span
                            className={`rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-widest ${sourceColorClass(item.source)}`}
                        >
                            {sourceLabel(item.source)}
                        </span>
                        {item.license && (
                            <span className="rounded-md border border-border px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                {item.license}
                            </span>
                        )}
                        {maturityLabel && (
                            <span className="rounded-md border border-destructive/40 px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-destructive">
                                {maturityLabel}
                            </span>
                        )}
                    </div>

                    <div className="space-y-1">
                        <h2 className="text-lg font-black leading-tight tracking-tight text-foreground">
                            {item.title ?? 'Sin título'}
                        </h2>
                        {item.author &&
                            (item.author_url ? (
                                <a
                                    href={item.author_url}
                                    target="_blank"
                                    rel="noopener"
                                    className="inline-block text-sm text-muted-foreground underline-offset-2 hover:text-primary hover:underline"
                                >
                                    {item.author}
                                </a>
                            ) : (
                                <p className="text-sm text-muted-foreground">{item.author}</p>
                            ))}
                    </div>

                    {item.tags.length > 0 && (
                        <div className="flex flex-wrap gap-1">
                            {item.tags.slice(0, 12).map((tag) => (
                                <span
                                    key={tag}
                                    className="rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground"
                                >
                                    {tag}
                                </span>
                            ))}
                        </div>
                    )}

                    <div className="flex flex-wrap gap-2 pt-1">
                        {onSave && savedBoardId === undefined && (
                            <button
                                type="button"
                                onClick={() => onSave(item)}
                                className="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-bold text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-card"
                            >
                                <Bookmark className="h-3.5 w-3.5" />
                                Guardar
                            </button>
                        )}

                        {savedBoardId !== undefined && (
                            <a
                                href={inspiration.moodboards.show(savedBoardId).url}
                                className="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-bold text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-card"
                            >
                                <BookmarkCheck className="h-3.5 w-3.5" />
                                Ver moodboard
                            </a>
                        )}

                        {isFull && item.full_url && (
                            <a
                                href={item.full_url}
                                target="_blank"
                                rel="noopener"
                                className="inline-flex items-center gap-1.5 rounded-md border border-emerald-500/40 px-3 py-1.5 text-xs font-bold text-emerald-300 transition-colors hover:bg-emerald-500/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-card"
                            >
                                <Download className="h-3.5 w-3.5" />
                                Full
                            </a>
                        )}

                        {onDownload && !isFull && !isFailed && downloadState === 'idle' && (
                            <button
                                type="button"
                                onClick={() => onDownload(item)}
                                className="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-bold text-muted-foreground transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-card"
                            >
                                <Download className="h-3.5 w-3.5" />
                                Descargar full
                            </button>
                        )}

                        {onDownload && downloadState === 'downloading' && (
                            <span className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs text-muted-foreground">
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                Descargando…
                            </span>
                        )}

                        {onDownload && downloadState === 'queued' && (
                            <span className="inline-flex items-center gap-1.5 rounded-md border border-amber-500/40 px-3 py-1.5 text-xs font-bold text-amber-300">
                                En cola
                                {onRefresh && (
                                    <button
                                        type="button"
                                        aria-label="Refrescar estado de descarga"
                                        onClick={onRefresh}
                                        className="inline-flex items-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                    >
                                        <RefreshCw className="h-3.5 w-3.5" />
                                    </button>
                                )}
                            </span>
                        )}

                        {onDownload && isFailed && (
                            <button
                                type="button"
                                onClick={() => onDownload(item)}
                                className="inline-flex items-center gap-1.5 rounded-md border border-destructive/50 px-3 py-1.5 text-xs font-bold text-destructive transition-colors hover:bg-destructive/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-card"
                            >
                                <RefreshCw className="h-3.5 w-3.5" />
                                Reintentar
                            </button>
                        )}

                        <a
                            href={item.page_url}
                            target="_blank"
                            rel="noopener"
                            className="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-bold text-muted-foreground transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-card"
                        >
                            <ExternalLink className="h-3.5 w-3.5" />
                            Abrir en fuente
                        </a>
                    </div>
                </div>
            </div>
        </div>,
        document.body,
    );
}
