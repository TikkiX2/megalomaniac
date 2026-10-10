import { DndContext, PointerSensor, closestCenter, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, arrayMove, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, router } from '@inertiajs/react';
import { GripVertical, Trash } from 'lucide-react';
import { useEffect, useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { csrfHeaders } from '@/lib/csrf';

const TYPES = ['serie', 'pelicula', 'disco', 'libro', 'juego'];

interface QueueItem {
    id: number;
    title: string;
    type: string;
}

function SortableRow({ item, onRemove }: { item: QueueItem; onRemove: (id: number) => void }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: item.id });
    const style = { transform: CSS.Transform.toString(transform), transition };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 ${isDragging ? 'opacity-60' : ''}`}
        >
            <button {...attributes} {...listeners} aria-label="Reordenar" className="cursor-grab text-muted-foreground">
                <GripVertical className="h-4 w-4" />
            </button>
            <span className="flex-1 truncate text-sm font-bold text-foreground">
                {item.title} <span className="text-xs font-normal text-muted-foreground">{item.type}</span>
            </span>
            <Button size="sm" variant="ghost" onClick={() => onRemove(item.id)} aria-label="Sacar">
                <Trash className="h-4 w-4" />
            </Button>
        </div>
    );
}

export default function Queue({ items }: { items: QueueItem[] }) {
    const [local, setLocal] = useState<QueueItem[]>(items);
    const [title, setTitle] = useState('');
    const [tipo, setTipo] = useState('serie');
    const sensors = useSensors(useSensor(PointerSensor));

    // Tras cada visita Inertia re-manda los items como los guardó el server.
    useEffect(() => setLocal(items), [items]);

    const reorder = async (event: DragEndEvent) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;
        const oldIndex = local.findIndex((i) => i.id === active.id);
        const newIndex = local.findIndex((i) => i.id === over.id);
        const next = arrayMove(local, oldIndex, newIndex);
        setLocal(next);
        await fetch('/today/queue/reorder', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() },
            body: JSON.stringify({ orden: next.map((i) => i.id) }),
        });
    };

    const remove = (id: number) => router.delete(`/today/queue/${id}`);

    return (
        <MainLayout>
            <Head title="Cola" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-3 p-4">
                <h2 className="text-xl font-black text-white">Cola</h2>
                <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={reorder}>
                    <SortableContext items={local.map((i) => i.id)} strategy={verticalListSortingStrategy}>
                        {local.map((it) => (
                            <SortableRow key={it.id} item={it} onRemove={remove} />
                        ))}
                    </SortableContext>
                </DndContext>
                <div className="flex gap-2">
                    <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Título…" className="bg-card border-border" />
                    <Select value={tipo} onValueChange={setTipo}>
                        <SelectTrigger className="w-36 bg-card border-border"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            {TYPES.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}
                        </SelectContent>
                    </Select>
                    <Button
                        onClick={() => {
                            if (!title.trim()) return;
                            router.post('/today/queue', { title: title.trim(), type: tipo });
                            setTitle('');
                        }}
                        className="bg-primary font-bold"
                    >
                        Agregar
                    </Button>
                </div>
                {local.length > 0 && <Button variant="outline" onClick={() => router.post('/today/queue/next')}>Siguiente</Button>}
            </div>
        </MainLayout>
    );
}
