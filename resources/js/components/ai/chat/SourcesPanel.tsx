import { ExternalLink, Globe } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import type { Citation } from '@/types/chat';

interface SourcesPanelProps {
    citations: Citation[];
    activeIndex?: number | null;
    className?: string;
}

function domain(url: string): string {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}

function Favicon({ url }: { url: string }) {
    const [failed, setFailed] = useState(false);

    if (failed) {
        return <Globe className="h-3 w-3 shrink-0 text-muted-foreground" aria-hidden="true" />;
    }

    return (
        <img
            src={`https://www.google.com/s2/favicons?domain=${encodeURIComponent(domain(url))}&sz=32`}
            alt=""
            aria-hidden="true"
            loading="lazy"
            referrerPolicy="no-referrer"
            onError={() => setFailed(true)}
            className="h-3 w-3 shrink-0 rounded-sm"
        />
    );
}

export function SourcesPanel({ citations, activeIndex = null, className }: SourcesPanelProps) {
    const [expanded, setExpanded] = useState(true);
    const [trackedActiveIndex, setTrackedActiveIndex] = useState<number | null>(activeIndex);

    if (activeIndex !== trackedActiveIndex) {
        setTrackedActiveIndex(activeIndex);

        if (activeIndex !== null) {
            setExpanded(true);
        }
    }

    useEffect(() => {
        if (activeIndex === null) return;

        document.getElementById(`cite-${activeIndex}`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, [activeIndex]);

    if (citations.length === 0) return null;

    return (
        <div className={cn('mt-3 rounded-xl border border-border bg-card/60', className)}>
            <button
                type="button"
                onClick={() => setExpanded((previous) => !previous)}
                className="flex w-full items-center justify-between px-3 py-2 text-left"
                aria-expanded={expanded}
            >
                <span className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                    Fuentes · {citations.length}
                </span>
                <span className="text-xs text-muted-foreground">{expanded ? 'Ocultar' : 'Mostrar'}</span>
            </button>

            {expanded && (
                <ol className="grid gap-2 border-t border-border px-3 py-3 sm:grid-cols-2">
                    {citations.map((citation, position) => {
                        const index = position + 1;

                        return (
                            <li key={citation.url}>
                                <a
                                    id={`cite-${index}`}
                                    href={citation.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={cn(
                                        'flex h-full gap-2 rounded-lg border border-transparent p-2 transition-colors hover:border-border hover:bg-muted/40',
                                        activeIndex === index && 'border-primary/40 bg-primary/5',
                                    )}
                                >
                                    <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/15 text-[10px] font-black text-primary">
                                        {index}
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block truncate text-xs font-medium text-foreground">
                                            {citation.title ?? domain(citation.url)}
                                        </span>
                                        {citation.snippet !== null && citation.snippet !== undefined && citation.snippet !== '' && (
                                            <span className="mt-1 line-clamp-2 text-[11px] leading-snug text-muted-foreground">
                                                {citation.snippet}
                                            </span>
                                        )}
                                        <span className="mt-1 flex items-center gap-1 text-[10px] text-muted-foreground">
                                            <Favicon url={citation.url} />
                                            {domain(citation.url)}
                                            <ExternalLink className="h-2.5 w-2.5 shrink-0" />
                                        </span>
                                    </span>
                                </a>
                            </li>
                        );
                    })}
                </ol>
            )}
        </div>
    );
}
