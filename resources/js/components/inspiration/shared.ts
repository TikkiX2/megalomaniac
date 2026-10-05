/**
 * Shared types and small helpers for the inspiration surface.
 *
 * The explore/search endpoints degrade per source, so every prop that the
 * partial-reload payload may omit is typed optional at the page boundary and
 * defaulted there (never dereferenced blindly in a nested component).
 */

export interface InspirationItem {
    source: string;
    source_id: string;
    title: string | null;
    author: string | null;
    author_url: string | null;
    page_url: string;
    image_url: string;
    thumbnail_url: string | null;
    width: number | null;
    height: number | null;
    tags: string[];
    dominant_color: string | null;
    license: string | null;
    maturity: string | null;
}

export interface SourceStatus {
    enabled: boolean;
    configured: boolean;
    down: boolean;
    cache_age_minutes: number | null;
    error_at: string | null;
}

export interface ResultGroup {
    source: string;
    items: InspirationItem[];
    has_more: boolean;
    from_cache: boolean;
    age_minutes: number | null;
}

export interface BoardOption {
    id: number;
    name: string;
    project_name: string | null;
    count: number;
}

export interface ProjectOption {
    id: number;
    name: string;
}

export interface SavedMoodboardItem {
    id: number;
    source: string;
    source_id: string;
    title: string | null;
    author: string | null;
    page_url: string;
    tags: string[];
    license: string | null;
    maturity: string | null;
    download_status: 'thumb' | 'full' | 'failed';
    thumb_url: string;
    image_url: string;
    full_url: string | null;
    width: number | null;
    height: number | null;
}

/**
 * Human labels for the adapter keys. Mirrors `Source::label()` on the backend
 * without shipping the whole registry to the client.
 */
const SOURCE_LABELS: Record<string, string> = {
    deviantart: 'DeviantArt',
    artstation: 'ArtStation',
    wallhaven: 'Wallhaven',
    openverse: 'Openverse',
    zerochan: 'Zerochan',
    gelbooru: 'Gelbooru',
    arena: 'Are.na',
    met: 'The Met',
    aic: 'Art Institute of Chicago',
};

export function sourceLabel(key: string): string {
    return SOURCE_LABELS[key] ?? key.charAt(0).toUpperCase() + key.slice(1);
}

/**
 * Stable per-source accent. Hashed instead of a fixed map so a new adapter
 * registered in the backend still gets a color without a frontend change.
 */
const SOURCE_COLORS = [
    'bg-primary text-primary-foreground',
    'bg-sky-500/90 text-white',
    'bg-amber-500/90 text-black',
    'bg-emerald-600/90 text-white',
    'bg-violet-500/90 text-white',
    'bg-rose-500/90 text-white',
    'bg-cyan-600/90 text-white',
    'bg-orange-500/90 text-black',
    'bg-teal-600/90 text-white',
];

export function sourceColorClass(key: string): string {
    let hash = 0;

    for (let i = 0; i < key.length; i += 1) {
        hash = (hash * 31 + key.charCodeAt(i)) >>> 0;
    }

    return SOURCE_COLORS[hash % SOURCE_COLORS.length];
}

/**
 * Relative cache age for a source chip: `hace 3h` / `hace 25m`.
 */
export function formatCacheAge(minutes: number | null): string | null {
    if (minutes === null) {
        return null;
    }

    if (minutes < 60) {
        return `hace ${Math.max(1, Math.round(minutes))}m`;
    }

    return `hace ${Math.round(minutes / 60)}h`;
}
