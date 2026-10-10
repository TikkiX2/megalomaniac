import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ANCHOR_LABELS } from '@/components/today/DayItemRow';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import MainLayout from '@/layouts/main-layout';

const ANCHORS = ['wake_up', 'after_meal', 'after_gym', 'after_shower', 'before_sleep', 'no_anchor'];

interface PoolItem { id: number; title: string; }
interface PendingItem { id: number; title: string; task_id: number | null; anchor: string; }
interface DayItemShape { id: number; title: string; task_id: number | null; anchor: string; position: number; state?: string; }
interface SelectedItem { title: string; anchor: string; task_id: number | null; }
interface Props {
    fecha: string;
    pool: PoolItem[];
    pendientesAyer: PendingItem[];
    diaManana: { visible_items?: DayItemShape[]; visibleItems?: DayItemShape[] } | null;
}

export default function Tomorrow({ fecha, pool, pendientesAyer, diaManana }: Props) {
    const existentes: DayItemShape[] = diaManana?.visible_items ?? diaManana?.visibleItems ?? [];
    const [sel, setSel] = useState<Record<number, SelectedItem>>(() => {
        const m: Record<number, SelectedItem> = {};
        existentes.forEach((it, i) => { m[i] = { title: it.title, anchor: it.anchor, task_id: it.task_id }; });
        return m;
    });
    const count = Object.keys(sel).filter((k) => sel[Number(k)]?.title).length;

    const toggle = (t: PoolItem) => {
        const entries = Object.entries(sel);
        const found = entries.find(([, v]) => v.task_id === t.id);
        if (found) {
            const n = { ...sel };
            delete n[Number(found[0])];
            setSel(n);
        } else if (count < 3) {
            const idx = [0, 1, 2].find((i) => !(i in sel)) ?? 0;
            setSel({ ...sel, [idx]: { title: t.title, anchor: 'no_anchor', task_id: t.id } });
        }
    };

    const guardar = () => {
        const items = Object.entries(sel).map(([k, v], i) => ({
            task_id: v.task_id, title: v.title, anchor: v.anchor, position: Number(k) + 1 > 3 ? i + 1 : Number(k) + 1,
        }));
        router.post('/today/tomorrow', { items });
    };

    const dejarVacio = () => router.post('/today/tomorrow', { items: [] });

    return (
        <MainLayout>
            <Head title="Mañana" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-5 p-4 md:p-6">
                <div className="flex items-baseline justify-between">
                    <h2 className="text-xl font-black text-white">Elegí hasta 3 <span className="text-sm font-bold text-muted-foreground">· {fecha}</span></h2>
                    <span className="text-xs font-bold text-muted-foreground">{count} de 3 elegidas</span>
                </div>

                {pendientesAyer.length > 0 && (
                    <div className="flex flex-col gap-2 rounded-xl bg-card border border-border p-4">
                        <p className="text-xs font-black uppercase tracking-widest text-muted-foreground">Ayer quedó pendiente</p>
                        {pendientesAyer.map((p) => (
                            <div key={p.id} className="flex items-center justify-between gap-2">
                                <span className="text-sm font-bold text-foreground">{p.title}</span>
                                <div className="flex gap-2">
                                    <Button size="sm" variant="outline" onClick={() => router.post(`/today/tomorrow/${p.id}/put-today`)}>Ponerlo hoy</Button>
                                    <Button size="sm" variant="ghost" onClick={() => router.post(`/today/items/${p.id}/release`)}>Soltarlo</Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <div className="flex flex-col gap-2 rounded-xl bg-card border border-border p-4">
                    {pool.map((t) => {
                        const active = Object.values(sel).some((v) => v.task_id === t.id);
                        return (
                            <button key={t.id} onClick={() => toggle(t)} className={`flex items-center justify-between rounded-lg border px-3 py-2 text-left ${active ? 'border-primary bg-primary/10' : 'border-border'}`}>
                                <span className="text-sm font-bold text-foreground">{t.title}</span>
                                <span className="text-xs text-muted-foreground">{active ? 'Elegida' : 'Elegir'}</span>
                            </button>
                        );
                    })}
                    {pool.length === 0 && <p className="text-sm text-muted-foreground">No hay tareas en la semana. Agregalas desde Semana.</p>}
                </div>

                {Object.entries(sel).map(([k, v]) => (
                    <div key={k} className="flex gap-2">
                        <span className="text-xs font-black text-muted-foreground pt-2">{Number(k) + 1}</span>
                        <span className="flex-1 text-sm font-bold text-foreground">{v.title}</span>
                        <Select value={v.anchor} onValueChange={(a) => setSel({ ...sel, [k]: { ...v, anchor: a } })}>
                            <SelectTrigger className="w-44 bg-card border-border"><SelectValue /></SelectTrigger>
                            <SelectContent>{ANCHORS.map((a) => <SelectItem key={a} value={a}>{ANCHOR_LABELS[a] ?? a}</SelectItem>)}</SelectContent>
                        </Select>
                    </div>
                ))}

                <div className="flex gap-3">
                    <Button onClick={guardar} className="bg-primary font-black">Guardar mañana</Button>
                    <Button variant="ghost" onClick={dejarVacio}>Dejarlo vacío</Button>
                </div>
            </div>
        </MainLayout>
    );
}
