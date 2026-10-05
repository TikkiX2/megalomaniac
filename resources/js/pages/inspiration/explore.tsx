import { Head, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import ImageCard from '@/components/inspiration/ImageCard';
import MasonryGrid from '@/components/inspiration/MasonryGrid';
import SaveModal from '@/components/inspiration/SaveModal';
import { sourceLabel, type BoardOption, type InspirationItem, type ResultGroup, type SourceStatus } from '@/components/inspiration/shared';
import SourceChips from '@/components/inspiration/SourceChips';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import MainLayout from '@/layouts/main-layout';
import inspiration from '@/routes/inspiration';
import type { SharedData } from '@/types';

interface ExploreProps {
    sources?: Record<string, SourceStatus>;
    boards?: BoardOption[];
    results?: ResultGroup[];
    saved?: Record<string, number>;
    search?: string;
    source?: string;
}

const PARTIAL_PROPS = ['results', 'search', 'source', 'sources', 'boards', 'saved'];
const PAGINATION_PROPS = ['results', 'search', 'source'];
const SKELETON_HEIGHTS = [220, 320, 180, 280, 360, 240, 300, 200];

function SkeletonGrid() {
    return (
        <div className="columns-2 gap-3 sm:columns-3 xl:columns-4" aria-hidden>
            {SKELETON_HEIGHTS.map((height, index) => (
                <Skeleton key={index} className="mb-3 w-full break-inside-avoid rounded-xl" style={{ height }} />
            ))}
        </div>
    );
}

export default function Explore() {
    const {
        sources = {},
        boards = [],
        results = [],
        saved = {},
        search = '',
        source = 'all',
    } = usePage<SharedData & ExploreProps>().props;

    const [query, setQuery] = useState(search);
    const [base, setBase] = useState<ResultGroup[]>(results);
    const [appended, setAppended] = useState<Record<string, InspirationItem[]>>({});
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(
        () => results.find((group) => group.source === source)?.has_more ?? false,
    );
    const [searching, setSearching] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);
    const [saveItem, setSaveItem] = useState<InspirationItem | null>(null);

    // A brand-new search/source visit replaces the page props; pagination does
    // not. Reset the snapshot only when the query signature actually changes.
    const signature = `${source}::${search}`;
    const signatureRef = useRef(signature);

    useEffect(() => {
        if (signatureRef.current === signature) {
            return;
        }

        signatureRef.current = signature;
        setBase(results);
        setAppended({});
        setPage(1);
        setHasMore(results.find((group) => group.source === source)?.has_more ?? false);
    }, [signature, results, source]);

    const runSearch = (q: string, target: string) => {
        setSearching(true);
        router.get(
            inspiration.search().url,
            { q, source: target },
            {
                preserveState: false,
                preserveScroll: true,
                only: PARTIAL_PROPS,
                onFinish: () => setSearching(false),
            },
        );
    };

    const selectSource = (target: string) => {
        if (target === source) {
            return;
        }

        runSearch(query, target);
    };

    const loadMore = () => {
        if (loadingMore || !hasMore || source === 'all') {
            return;
        }

        const next = page + 1;
        setLoadingMore(true);

        router.get(
            inspiration.search().url,
            { q: search, source, page: next },
            {
                preserveState: true,
                preserveScroll: true,
                only: PAGINATION_PROPS,
                onSuccess: (response) => {
                    const group = (response.props.results as ResultGroup[] | undefined)?.find(
                        (candidate) => candidate.source === source,
                    );

                    if (!group) {
                        return;
                    }

                    setAppended((previous) => ({
                        ...previous,
                        [source]: [...(previous[source] ?? []), ...group.items],
                    }));
                    setHasMore(group.has_more);
                    setPage(next);
                },
                onFinish: () => setLoadingMore(false),
            },
        );
    };

    const itemsFor = (group: ResultGroup): InspirationItem[] => [
        ...group.items,
        ...(appended[group.source] ?? []),
    ];

    const isDown = (key: string): boolean => sources[key]?.down ?? false;

    return (
        <MainLayout>
            <Head title="Inspiración" />

            <div className="mx-auto w-full max-w-6xl space-y-5 px-4 py-6">
                <Heading
                    title="Inspiración"
                    description="Explorá y buscá referencias visuales en todas tus fuentes. Guardalas en moodboards por proyecto."
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        runSearch(query, source);
                    }}
                    className="flex gap-2"
                >
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Buscar referencias…"
                            className="border-border bg-card pl-9"
                            aria-label="Buscar referencias"
                        />
                    </div>
                    <Button type="submit">Buscar</Button>
                </form>

                <SourceChips sources={sources} active={source} onSelect={selectSource} />

                {searching ? (
                    <SkeletonGrid />
                ) : base.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border bg-card/50 px-6 py-12 text-center">
                        <p className="text-sm font-bold uppercase tracking-widest text-foreground">
                            Sin resultados
                        </p>
                        <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                            Configurá fuentes en Ajustes o probá otro término de búsqueda.
                        </p>
                    </div>
                ) : source === 'all' ? (
                    <div className="space-y-8">
                        {base.map((group) => {
                            const items = itemsFor(group);

                            return (
                                <section key={group.source} className="space-y-3">
                                    <div className="flex items-center gap-2">
                                        <h3 className="text-sm font-black uppercase tracking-widest text-foreground">
                                            {sourceLabel(group.source)}
                                        </h3>
                                        <span className="text-[10px] tabular-nums text-muted-foreground">
                                            {items.length}
                                        </span>
                                        {group.from_cache && (
                                            <span className="rounded-full border border-border px-2 py-0.5 text-[10px] text-muted-foreground">
                                                caché
                                            </span>
                                        )}
                                    </div>

                                    {items.length === 0 ? (
                                        <p className="rounded-lg border border-border bg-card/50 px-3 py-2 text-xs text-muted-foreground">
                                            {isDown(group.source)
                                                ? `${sourceLabel(group.source)} está caída — probá más tarde.`
                                                : `Sin contenido en ${sourceLabel(group.source)}.`}
                                        </p>
                                    ) : (
                                        <MasonryGrid>
                                            {items.map((item) => (
                                                <ImageCard
                                                    key={`${item.source}:${item.source_id}`}
                                                    item={item}
                                                    savedBoardId={saved[`${item.source}:${item.source_id}`]}
                                                    onSave={setSaveItem}
                                                />
                                            ))}
                                        </MasonryGrid>
                                    )}
                                </section>
                            );
                        })}
                    </div>
                ) : (
                    (() => {
                        const group = base.find((candidate) => candidate.source === source);
                        const items = group ? itemsFor(group) : [];
                        const down = isDown(source);

                        if (items.length === 0) {
                            return (
                                <div className="rounded-xl border border-dashed border-border bg-card/50 px-6 py-12 text-center">
                                    <p className="text-sm font-bold uppercase tracking-widest text-foreground">
                                        {down ? 'Fuente caída' : `Sin resultados en ${sourceLabel(source)}`}
                                    </p>
                                    <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                                        {down
                                            ? `${sourceLabel(source)} no está respondiendo ahora. Probá más tarde o elegí otra fuente.`
                                            : 'Probá otro término o cambiá de fuente.'}
                                    </p>
                                </div>
                            );
                        }

                        return (
                            <MasonryGrid onLoadMore={loadMore} hasMore={hasMore} loading={loadingMore}>
                                {items.map((item) => (
                                    <ImageCard
                                        key={`${item.source}:${item.source_id}`}
                                        item={item}
                                        savedBoardId={saved[`${item.source}:${item.source_id}`]}
                                        onSave={setSaveItem}
                                    />
                                ))}
                            </MasonryGrid>
                        );
                    })()
                )}
            </div>

            <SaveModal
                item={saveItem}
                boards={boards}
                open={saveItem !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSaveItem(null);
                    }
                }}
                onSaved={() => router.reload({ only: ['saved', 'boards'] })}
            />
        </MainLayout>
    );
}
