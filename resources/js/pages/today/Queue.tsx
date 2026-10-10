import {
    DndContext,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import {
    SortableContext,
    arrayMove,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, router } from '@inertiajs/react';
import { GripVertical, Plus, Search, Trash } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { csrfHeaders } from '@/lib/csrf';

const TYPES = ['pelicula', 'serie', 'disco', 'libro', 'juego'];

const TYPE_LABELS: Record<string, string> = {
    pelicula: 'Película',
    serie: 'Serie',
    disco: 'Disco',
    libro: 'Libro',
    juego: 'Juego',
};

interface QueueItem {
    id: number;
    title: string;
    type: string;
}

interface MediaResult {
    title: string;
    creator: string | null;
    year: number | null;
    cover_url: string | null;
    source: string;
    external_id: string;
}

function SortableRow({
    item,
    onRemove,
}: {
    item: QueueItem;
    onRemove: (id: number) => void;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: item.id });
    const style = { transform: CSS.Transform.toString(transform), transition };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 ${isDragging ? 'opacity-60' : ''}`}
        >
            <button
                {...attributes}
                {...listeners}
                aria-label="Reordenar"
                className="cursor-grab text-muted-foreground"
            >
                <GripVertical className="h-4 w-4" />
            </button>
            <span className="flex-1 truncate text-sm font-bold text-foreground">
                {item.title}{' '}
                <span className="text-xs font-normal text-muted-foreground">
                    {TYPE_LABELS[item.type] ?? item.type}
                </span>
            </span>
            <Button
                size="sm"
                variant="ghost"
                onClick={() => onRemove(item.id)}
                aria-label="Sacar"
            >
                <Trash className="h-4 w-4" />
            </Button>
        </div>
    );
}

export default function Queue({ items }: { items: QueueItem[] }) {
    const [local, setLocal] = useState<QueueItem[]>(items);
    const [title, setTitle] = useState('');
    const [tipo, setTipo] = useState('pelicula');
    const [q, setQ] = useState('');
    const [results, setResults] = useState<MediaResult[]>([]);
    const [loading, setLoading] = useState(false);
    const [searchError, setSearchError] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const sensors = useSensors(useSensor(PointerSensor));

    // Tras cada visita Inertia re-manda los items como los guardó el server.
    useEffect(() => setLocal(items), [items]);

    // Buscador externo con debounce de 300ms (mismo patrón que el de Semana).
    useEffect(() => {
        if (timer.current) clearTimeout(timer.current);

        timer.current = setTimeout(async () => {
            if (q.trim().length < 2) {
                setResults([]);
                setLoading(false);
                setSearchError(false);
                return;
            }

            setLoading(true);
            try {
                const r = await fetch(
                    `/today/queue/search?type=${tipo}&q=${encodeURIComponent(q.trim())}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            ...csrfHeaders(),
                        },
                    },
                );
                if (!r.ok) throw new Error('search failed');
                setResults(await r.json());
                setSearchError(false);
            } catch {
                setResults([]);
                setSearchError(true);
            } finally {
                setLoading(false);
            }
        }, 300);

        return () => {
            if (timer.current) clearTimeout(timer.current);
        };
    }, [q, tipo]);

    const reorder = async (event: DragEndEvent) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;
        const oldIndex = local.findIndex((i) => i.id === active.id);
        const newIndex = local.findIndex((i) => i.id === over.id);
        const next = arrayMove(local, oldIndex, newIndex);
        setLocal(next);
        await fetch('/today/queue/reorder', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...csrfHeaders(),
            },
            body: JSON.stringify({ orden: next.map((i) => i.id) }),
        });
    };

    const addResult = async (result: MediaResult) => {
        await fetch('/today/queue', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...csrfHeaders(),
            },
            body: JSON.stringify({
                title: result.title,
                type: tipo,
                source: result.source,
                external_id: result.external_id,
                cover_url: result.cover_url,
                year: result.year,
                creator: result.creator,
            }),
        });
        setResults((prev) =>
            prev.filter(
                (r) =>
                    r.source !== result.source ||
                    r.external_id !== result.external_id,
            ),
        );
        setQ('');
        router.reload({ only: ['items'] });
    };

    const remove = (id: number) => router.delete(`/today/queue/${id}`);
    const showResults = q.trim().length >= 2;

    return (
        <MainLayout>
            <Head title="Cola" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-3 p-4">
                <h2 className="text-xl font-black text-foreground">Cola</h2>

                {/* Buscador externo */}
                <div className="flex flex-col gap-2 rounded-lg border border-border bg-card p-3 sm:flex-row sm:items-center">
                    <Select value={tipo} onValueChange={setTipo}>
                        <SelectTrigger className="w-full border-border bg-background sm:w-36">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {TYPES.map((t) => (
                                <SelectItem key={t} value={t}>
                                    {TYPE_LABELS[t]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Buscar en catálogo…"
                            className="border-border bg-background pl-9"
                        />
                    </div>
                </div>

                {showResults && (
                    <div className="flex flex-col gap-2">
                        {loading && (
                            <p className="text-sm text-muted-foreground">
                                Buscando…
                            </p>
                        )}
                        {!loading && searchError && (
                            <p className="text-sm text-muted-foreground">
                                No se pudo buscar. Cargalo a mano abajo.
                            </p>
                        )}
                        {!loading && !searchError && results.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Sin resultados. Cargalo a mano abajo.
                            </p>
                        )}
                        {!loading &&
                            results.map((r) => (
                                <div
                                    key={`${r.source}-${r.external_id}`}
                                    className="flex items-center gap-3 rounded-lg border border-border bg-card px-3 py-2"
                                >
                                    {r.cover_url && (
                                        <img
                                            src={r.cover_url}
                                            alt=""
                                            loading="lazy"
                                            className="h-12 w-9 shrink-0 rounded object-cover"
                                            onError={(e) => {
                                                e.currentTarget.style.display =
                                                    'none';
                                            }}
                                        />
                                    )}
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-bold text-foreground">
                                            {r.title}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {[r.creator, r.year]
                                                .filter(Boolean)
                                                .join(' · ') ||
                                                TYPE_LABELS[tipo]}
                                        </span>
                                    </span>
                                    <Button
                                        size="sm"
                                        className="shrink-0 bg-primary font-bold"
                                        onClick={() => addResult(r)}
                                    >
                                        <Plus className="h-4 w-4" /> Agregar
                                    </Button>
                                </div>
                            ))}
                    </div>
                )}

                {/* Alta manual */}
                <div className="flex flex-col gap-2 sm:flex-row">
                    <Input
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        placeholder="Título…"
                        className="border-border bg-card"
                    />
                    <Button
                        onClick={() => {
                            if (!title.trim()) return;
                            router.post('/today/queue', {
                                title: title.trim(),
                                type: tipo,
                            });
                            setTitle('');
                        }}
                        className="bg-primary font-bold"
                    >
                        Agregar a mano
                    </Button>
                </div>

                <p className="text-xs text-muted-foreground">
                    Tipo seleccionado:{' '}
                    <span className="font-bold">{TYPE_LABELS[tipo]}</span>
                </p>

                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={reorder}
                >
                    <SortableContext
                        items={local.map((i) => i.id)}
                        strategy={verticalListSortingStrategy}
                    >
                        {local.map((it) => (
                            <SortableRow
                                key={it.id}
                                item={it}
                                onRemove={remove}
                            />
                        ))}
                    </SortableContext>
                </DndContext>
                {local.length > 0 && (
                    <Button
                        variant="outline"
                        onClick={() => router.post('/today/queue/next')}
                    >
                        Siguiente
                    </Button>
                )}
            </div>
        </MainLayout>
    );
}
