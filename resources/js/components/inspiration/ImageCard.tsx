import { Link } from '@inertiajs/react';
import { Bookmark, BookmarkCheck, ExternalLink, Maximize2 } from 'lucide-react';
import inspiration from '@/routes/inspiration';
import { sourceColorClass, sourceLabel, type InspirationItem } from './shared';

interface ImageCardProps {
    item: InspirationItem;
    /** Present when this source:source_id is already on a moodboard. */
    savedBoardId?: number;
    onSave: (item: InspirationItem) => void;
    /** Opens the full-screen viewer on this item, within the visible grid. */
    onExpand: (origin: HTMLElement) => void;
}

/**
 * Masonry tile for one remote image. Natural aspect (`h-auto`), source badge,
 * and a hover/focus overlay with save / expand / open-in-source.
 */
export default function ImageCard({ item, savedBoardId, onSave, onExpand }: ImageCardProps) {
    const alt = item.title ?? `${sourceLabel(item.source)} image`;

    return (
        <article className="group relative mb-3 break-inside-avoid overflow-hidden rounded-xl border border-border bg-card">
            <img
                src={item.thumbnail_url ?? item.image_url}
                alt={alt}
                loading="lazy"
                className="h-auto w-full bg-muted object-cover"
            />

            <span
                className={`absolute left-2 top-2 rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-widest shadow-sm ${sourceColorClass(item.source)}`}
            >
                {sourceLabel(item.source)}
            </span>

            {item.license && (
                <span className="absolute right-2 top-2 rounded-md bg-black/60 px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-white/90">
                    {item.license}
                </span>
            )}

            <div className="pointer-events-none absolute inset-0 flex flex-col justify-end bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100">
                <div className="pointer-events-auto p-3">
                    {(item.title || item.author) && (
                        <div className="mb-2">
                            {item.title && (
                                <p className="line-clamp-2 text-xs font-semibold text-white">{item.title}</p>
                            )}
                            {item.author && (
                                <p className="truncate text-[10px] text-white/70">{item.author}</p>
                            )}
                        </div>
                    )}

                    <div className="flex items-center gap-1.5">
                        {savedBoardId ? (
                            <Link
                                href={inspiration.moodboards.show(savedBoardId)}
                                className="inline-flex items-center gap-1 rounded-md bg-primary px-2 py-1 text-[11px] font-bold text-primary-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-black"
                                aria-label="Guardado — ver moodboard"
                            >
                                <BookmarkCheck className="h-3.5 w-3.5" />
                                Guardado
                            </Link>
                        ) : (
                            <button
                                type="button"
                                onClick={() => onSave(item)}
                                className="inline-flex items-center gap-1 rounded-md bg-white/95 px-2 py-1 text-[11px] font-bold text-black hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-black"
                                aria-label="Guardar en moodboard"
                            >
                                <Bookmark className="h-3.5 w-3.5" />
                                Guardar
                            </button>
                        )}

                        <button
                            type="button"
                            onClick={(event) => onExpand(event.currentTarget)}
                            className="inline-flex h-7 w-7 items-center justify-center rounded-md bg-white/15 text-white hover:bg-white/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-black"
                            aria-label="Expandir imagen"
                        >
                            <Maximize2 className="h-3.5 w-3.5" />
                        </button>

                        <a
                            href={item.page_url}
                            target="_blank"
                            rel="noopener"
                            className="inline-flex h-7 w-7 items-center justify-center rounded-md bg-white/15 text-white hover:bg-white/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-black"
                            aria-label="Abrir en la fuente original"
                        >
                            <ExternalLink className="h-3.5 w-3.5" />
                        </a>
                    </div>
                </div>
            </div>
        </article>
    );
}
