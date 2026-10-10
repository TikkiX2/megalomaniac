import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import MainLayout from '@/layouts/main-layout';
import { csrfHeaders } from '@/lib/csrf';

interface BlockPayload {
    id: number;
    label: string;
    weekday: number;
    start_time: string;
    duration_min: number;
    active: boolean;
}

interface Props {
    pool: { id: number; title: string }[];
    overloaded: boolean;
    blocks: BlockPayload[];
}

const WEEKDAYS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

const EMPTY_BLOCK = { label: '', weekday: 0, start_time: '19:00', duration_min: 60, active: true };

export default function Week({ pool, overloaded, blocks }: Props) {
    const [q, setQ] = useState('');
    const [res, setRes] = useState<{ id: number; title: string }[]>([]);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [form, setForm] = useState(EMPTY_BLOCK);
    const [editingId, setEditingId] = useState<number | null>(null);

    useEffect(() => {
        if (timer.current) clearTimeout(timer.current);
        timer.current = setTimeout(async () => {
            if (!q.trim()) {
                setRes([]);
                return;
            }
            const r = await fetch(`/today/week/search?q=${encodeURIComponent(q)}`, { headers: csrfHeaders() });
            if (r.ok) setRes(await r.json());
        }, 300);
        return () => {
            if (timer.current) clearTimeout(timer.current);
        };
    }, [q]);

    const add = async (taskId: number) => {
        await fetch('/today/week', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() },
            body: JSON.stringify({ tarea_id: taskId }),
        });
        setQ('');
        setRes([]);
        router.reload({ only: ['pool', 'overloaded'] });
    };

    const submitBlock = async () => {
        const url = editingId === null ? '/today/blocks' : `/today/blocks/${editingId}`;
        await fetch(url, {
            method: editingId === null ? 'POST' : 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() },
            body: JSON.stringify(form),
        });
        setForm(EMPTY_BLOCK);
        setEditingId(null);
        router.reload({ only: ['blocks'] });
    };

    const editBlock = (block: BlockPayload) => {
        setEditingId(block.id);
        setForm({
            label: block.label,
            weekday: block.weekday,
            start_time: block.start_time.slice(0, 5),
            duration_min: block.duration_min,
            active: block.active,
        });
    };

    const deleteBlock = async (id: number) => {
        await fetch(`/today/blocks/${id}`, { method: 'DELETE', headers: csrfHeaders() });
        if (editingId === id) {
            setForm(EMPTY_BLOCK);
            setEditingId(null);
        }
        router.reload({ only: ['blocks'] });
    };

    const toggleActive = async (block: BlockPayload) => {
        await fetch(`/today/blocks/${block.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() },
            body: JSON.stringify({ active: !block.active }),
        });
        router.reload({ only: ['blocks'] });
    };

    return (
        <MainLayout>
            <Head title="Semana" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
                <h2 className="text-xl font-black text-white">Pool semanal</h2>
                {overloaded && <p className="text-sm text-muted-foreground">El pool funciona con 5-7. ¿Sacamos alguna?</p>}
                {pool.map((t) => (
                    <div key={t.id} className="flex items-center justify-between rounded-lg border border-border bg-card px-3 py-2">
                        <span className="text-sm font-bold text-foreground">{t.title}</span>
                        <Button size="sm" variant="ghost" onClick={() => router.delete(`/today/week/${t.id}`)}>Sacar</Button>
                    </div>
                ))}
                <div className="flex gap-2">
                    <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar en backlog…" className="bg-card border-border" />
                </div>
                {res.map((t) => (
                    <div key={t.id} className="flex items-center justify-between rounded-lg border border-border px-3 py-2">
                        <span className="text-sm text-foreground">{t.title}</span>
                        <Button size="sm" onClick={() => add(t.id)}>Agregar</Button>
                    </div>
                ))}

                <h2 className="mt-4 text-xl font-black text-white">Bloques</h2>
                {blocks.length === 0 && <p className="text-sm text-muted-foreground">No hay bloques cargados.</p>}
                {blocks.map((b) => (
                    <div key={b.id} className="flex items-center gap-3 rounded-lg border border-border bg-card px-3 py-2">
                        <input type="checkbox" checked={b.active} onChange={() => toggleActive(b)} title="Activo" />
                        <span className={`flex-1 text-sm ${b.active ? 'font-bold text-foreground' : 'text-muted-foreground line-through'}`}>
                            {b.label}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {WEEKDAYS[b.weekday]} · {b.start_time.slice(0, 5)} · {b.duration_min} min
                        </span>
                        <Button size="sm" variant="ghost" onClick={() => editBlock(b)}>Editar</Button>
                        <Button size="sm" variant="ghost" onClick={() => deleteBlock(b.id)}>Borrar</Button>
                    </div>
                ))}

                <div className="flex flex-col gap-2 rounded-xl border border-border bg-card p-4">
                    <p className="text-sm font-bold text-foreground">{editingId === null ? 'Nuevo bloque' : 'Editar bloque'}</p>
                    <Input
                        value={form.label}
                        onChange={(e) => setForm({ ...form, label: e.target.value })}
                        placeholder="Etiqueta (ej. Proyecto)"
                        className="bg-background border-border"
                    />
                    <Select value={String(form.weekday)} onValueChange={(v) => setForm({ ...form, weekday: Number(v) })}>
                        <SelectTrigger className="bg-background border-border"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            {WEEKDAYS.map((d, i) => <SelectItem key={d} value={String(i)}>{d}</SelectItem>)}
                        </SelectContent>
                    </Select>
                    <div className="flex flex-wrap items-center gap-3">
                        <label className="flex items-center gap-2 text-sm text-muted-foreground">
                            Hora
                            <Input
                                type="time"
                                value={form.start_time}
                                onChange={(e) => setForm({ ...form, start_time: e.target.value })}
                                className="w-28 bg-background border-border"
                            />
                        </label>
                        <label className="flex items-center gap-2 text-sm text-muted-foreground">
                            Duración (min)
                            <Input
                                type="number"
                                min={5}
                                value={form.duration_min}
                                onChange={(e) => setForm({ ...form, duration_min: Number(e.target.value) })}
                                className="w-24 bg-background border-border"
                            />
                        </label>
                        <label className="flex items-center gap-2 text-sm text-muted-foreground">
                            <input
                                type="checkbox"
                                checked={form.active}
                                onChange={(e) => setForm({ ...form, active: e.target.checked })}
                            />
                            Activo
                        </label>
                    </div>
                    <div className="flex gap-2">
                        <Button onClick={submitBlock} disabled={!form.label.trim()} className="bg-primary font-bold">
                            {editingId === null ? 'Agregar' : 'Guardar'}
                        </Button>
                        {editingId !== null && (
                            <Button variant="outline" onClick={() => { setEditingId(null); setForm(EMPTY_BLOCK); }}>Cancelar</Button>
                        )}
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
