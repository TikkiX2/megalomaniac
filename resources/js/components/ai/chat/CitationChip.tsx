import { cn } from '@/lib/utils';
import type { Citation } from '@/types/chat';

interface CitationChipProps {
    index: number;
    citation?: Citation;
    onClick?: () => void;
}

export function CitationChip({ index, citation, onClick }: CitationChipProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={citation?.title ?? citation?.url ?? `Fuente ${index}`}
            aria-label={`Ver fuente ${index}`}
            className={cn(
                'mx-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary/15 px-1 align-super text-[10px] font-black text-primary',
                'transition-colors hover:bg-primary/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            )}
        >
            {index}
        </button>
    );
}
