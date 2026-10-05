import { Loader2 } from 'lucide-react';
import { useEffect, useRef, type ReactNode } from 'react';

interface MasonryGridProps {
    children: ReactNode;
    /** Fires when the sentinel enters the viewport; only observed while `hasMore`. */
    onLoadMore?: () => void;
    hasMore?: boolean;
    loading?: boolean;
}

/**
 * Real CSS-columns masonry. Cards must opt into `break-inside-avoid`; the
 * infinite-scroll sentinel lives inside the last column and is simply skipped
 * when there is nothing more to fetch.
 */
export default function MasonryGrid({ children, onLoadMore, hasMore = false, loading = false }: MasonryGridProps) {
    const sentinel = useRef<HTMLDivElement>(null);
    const handler = useRef(onLoadMore);

    useEffect(() => {
        handler.current = onLoadMore;
    }, [onLoadMore]);

    useEffect(() => {
        const node = sentinel.current;

        if (!node || !hasMore || !handler.current) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0]?.isIntersecting) {
                    handler.current?.();
                }
            },
            { rootMargin: '600px 0px' },
        );

        observer.observe(node);

        return () => observer.disconnect();
    }, [hasMore]);

    return (
        <div className="columns-2 gap-3 sm:columns-3 xl:columns-4">
            {children}
            {hasMore && (
                <div ref={sentinel} className="flex items-center justify-center py-6" aria-hidden={!loading}>
                    {loading && <Loader2 className="h-5 w-5 animate-spin text-primary" />}
                </div>
            )}
        </div>
    );
}
