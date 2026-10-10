import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface DayItem {
    id: number;
    title: string;
    anchor: string;
    position: number;
    state: string;
    closing_note: string | null;
}

interface Props {
    fecha: string;
    day: { id: number; visible_items?: DayItem[]; visibleItems?: DayItem[] } | null;
    block: { label: string; start_time: string; duration_min: number } | null;
    routine: { id: number; name: string; focus: string | null } | null;
    queueFirst: { id: number; title: string; type: string } | null;
}

export default function TodayIndex({ fecha, day, block, routine, queueFirst }: Props) {
    const items: DayItem[] = (day as any)?.visible_items ?? (day as any)?.visibleItems ?? (day as any)?.items ?? [];
    const [noteId, setNoteId] = useState<number | null>(null);
    const noteForm = useForm({ state: 'done', closing_note: '' });

    const mark = (item: DayItem, state: string) => {
        if (state === 'done' && noteId !== item.id) {
            setNoteId(item.id);
            return;
        }
        router.patch(`/today/items/${item.id}`, {
            state,
            closing_note: noteId === item.id ? noteForm.data.closing_note : item.closing_note,
        }, { preserveScroll: true });
        setNoteId(null);
        noteForm.reset();
    };

    const release = (item: DayItem) => {
        router.post(`/today/items/${item.id}/release`, {}, { preserveScroll: true });
    };

    const nextInQueue = () => {
        router.post('/today/queue/next', {}, { preserveScroll: true });
    };

    return (
        <MainLayout>
            <Head title="Hoy" />
            <div className="mx-auto flex min-h-[calc(100vh-3rem)] w-full max-w-2xl flex-col gap-5 p-4 md:p-6">
                <p className="text-sm font-bold text-muted-foreground">{fecha}</p>

                {items.length === 0 ? (
                    <div className="flex flex-col gap-4 rounded-2xl bg-card border border-border p-8 text-center">
                        <p className="text-base font-medium text-foreground">Hoy no hay nada elegido.</p>
                        <Link href="/today/tomorrow" className="mx-auto inline-flex min-h-[44px] items-center rounded-lg bg-primary px-5 py-2 text-sm font-black text-white hover:bg-primary/90">
                            Elegir
                        </Link>
                    </div>
                ) : (
                    <div className="flex flex-col gap-3 rounded-2xl bg-card border border-border p-4">
                        {items.map((item, i) => (
                            <div key={item.id} className="flex items-center gap-3 border-b border-border last:border-0 py-2">
                                <button
                                    onClick={() => mark(item, item.state === 'done' ? 'pending' : 'done')}
                                    aria-label={item.state === 'done' ? 'Marcar pendiente' : 'Marcar hecho'}
                                    className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-md border ${item.state === 'done' ? 'bg-primary border-primary text-white' : 'border-border text-transparent'}`}
                                >
                                    ✓
                                </button>
                                <span className="w-6 shrink-0 text-xs font-black text-muted-foreground">{String(i + 1).padStart(2, '0')}</span>
                                <div className="flex flex-1 flex-col min-w-0">
                                    <span className={`truncate text-base font-bold ${item.state === 'done' ? 'line-through text-muted-foreground' : 'text-foreground'}`}>{item.title}</span>
                                    <span className="text-xs text-muted-foreground">{item.anchor}</span>
                                    {noteId === item.id && (
                                        <div className="mt-2 flex gap-2">
                                            <Input
                                                value={noteForm.data.closing_note}
                                                onChange={(e) => noteForm.setData('closing_note', e.target.value)}
                                                placeholder="¿cómo te sentiste?"
                                                className="bg-background border-border"
                                            />
                                            <Button size="sm" onClick={() => mark(item, 'done')} className="bg-primary font-bold">OK</Button>
                                        </div>
                                    )}
                                </div>
                                {item.state !== 'done' && (
                                    <button onClick={() => release(item)} className="shrink-0 text-xs font-bold text-muted-foreground hover:text-foreground">Soltar</button>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {(block || routine) && (
                    <div className="rounded-xl bg-card/50 border border-border p-3 text-sm text-muted-foreground">
                        {block && <p>Bloque: {block.label} · {block.start_time}</p>}
                        {routine && <p>Gimnasio: {routine.name}</p>}
                    </div>
                )}

                {queueFirst && (
                    <div className="flex items-center justify-between rounded-xl bg-card/50 border border-border p-3">
                        <p className="text-sm text-muted-foreground">Hoy toca: <span className="font-bold text-foreground">{queueFirst.title}</span></p>
                        <Button variant="outline" size="sm" onClick={nextInQueue}>Siguiente</Button>
                    </div>
                )}

                <Link href="/today/tomorrow" className="text-xs font-bold text-muted-foreground hover:text-foreground">Elegir mañana →</Link>
            </div>
        </MainLayout>
    );
}
