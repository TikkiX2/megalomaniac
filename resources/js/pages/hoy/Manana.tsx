import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const ANCLAS = ['al levantarme', 'después de comer', 'después del gimnasio', 'después de bañarme', 'antes de dormir', 'sin ancla'];

interface PoolItem { id: number; title: string; }
interface Pendiente { id: number; titulo: string; tarea_id: number | null; ancla: string; }
interface Props {
    fecha: string;
    pool: PoolItem[];
    pendientesAyer: Pendiente[];
    diaManana: { items_visibles?: any[]; itemsVisibles?: any[] } | null;
}

export default function Manana({ fecha, pool, pendientesAyer, diaManana }: Props) {
    const existentes: any[] = (diaManana as any)?.items_visibles ?? (diaManana as any)?.itemsVisibles ?? [];
    const [sel, setSel] = useState<Record<number, { titulo: string; ancla: string; tarea_id: number | null }>>(() => {
        const m: any = {};
        existentes.forEach((it: any, i: number) => { m[i] = { titulo: it.titulo, ancla: it.ancla, tarea_id: it.tarea_id }; });
        return m;
    });
    const count = Object.keys(sel).filter((k) => (sel as any)[k]?.titulo).length;

    const toggle = (t: PoolItem) => {
        const entries = Object.entries(sel);
        const found = entries.find(([, v]: any) => v.tarea_id === t.id);
        if (found) {
            const n = { ...sel };
            delete (n as any)[found[0]];
            setSel(n);
        } else if (count < 3) {
            const idx = [0, 1, 2].find((i) => !(i in sel)) ?? 0;
            setSel({ ...sel, [idx]: { titulo: t.title, ancla: 'sin ancla', tarea_id: t.id } });
        }
    };

    const guardar = () => {
        const items = Object.entries(sel).map(([k, v]: any, i) => ({
            tarea_id: v.tarea_id, titulo: v.titulo, ancla: v.ancla, posicion: Number(k) + 1 > 3 ? i + 1 : Number(k) + 1,
        }));
        router.post('/hoy/manana', { items });
    };

    const dejarVacio = () => router.post('/hoy/manana', { items: [] });

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
                                <span className="text-sm font-bold text-foreground">{p.titulo}</span>
                                <div className="flex gap-2">
                                    <Button size="sm" variant="outline" onClick={() => router.post(`/hoy/manana/${p.id}/poner-hoy`)}>Ponerlo hoy</Button>
                                    <Button size="sm" variant="ghost" onClick={() => router.post(`/hoy/items/${p.id}/soltar`)}>Soltarlo</Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <div className="flex flex-col gap-2 rounded-xl bg-card border border-border p-4">
                    {pool.map((t) => {
                        const active = Object.values(sel).some((v: any) => v.tarea_id === t.id);
                        return (
                            <button key={t.id} onClick={() => toggle(t)} className={`flex items-center justify-between rounded-lg border px-3 py-2 text-left ${active ? 'border-primary bg-primary/10' : 'border-border'}`}>
                                <span className="text-sm font-bold text-foreground">{t.title}</span>
                                <span className="text-xs text-muted-foreground">{active ? 'Elegida' : 'Elegir'}</span>
                            </button>
                        );
                    })}
                    {pool.length === 0 && <p className="text-sm text-muted-foreground">Pool vacío. Agregalo desde Semana.</p>}
                </div>

                {Object.entries(sel).map(([k, v]: any) => (
                    <div key={k} className="flex gap-2">
                        <span className="text-xs font-black text-muted-foreground pt-2">{Number(k) + 1}</span>
                        <span className="flex-1 text-sm font-bold text-foreground">{v.titulo}</span>
                        <Select value={v.ancla} onValueChange={(a) => setSel({ ...sel, [k]: { ...v, ancla: a } })}>
                            <SelectTrigger className="w-44 bg-card border-border"><SelectValue /></SelectTrigger>
                            <SelectContent>{ANCLAS.map((a) => <SelectItem key={a} value={a}>{a}</SelectItem>)}</SelectContent>
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
