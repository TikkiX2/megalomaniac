import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export default function Semana({ pool }: { pool: { id: number; title: string }[] }) {
    const [q, setQ] = useState('');
    const [res, setRes] = useState<{ id: number; title: string }[]>([]);
    const buscar = async () => {
        const r = await fetch(`/hoy/semana/buscar?q=${encodeURIComponent(q)}`);
        if (r.ok) setRes(await r.json());
    };
    return (
        <MainLayout>
            <Head title="Semana" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
                <h2 className="text-xl font-black text-white">Pool semanal</h2>
                {pool.length >= 10 && <p className="text-sm text-muted-foreground">El pool funciona con 5-7. ¿Sacamos alguna?</p>}
                {pool.map((t) => (
                    <div key={t.id} className="flex items-center justify-between rounded-lg border border-border bg-card px-3 py-2">
                        <span className="text-sm font-bold text-foreground">{t.title}</span>
                        <Button size="sm" variant="ghost" onClick={() => router.delete(`/hoy/semana/${t.id}`)}>Sacar</Button>
                    </div>
                ))}
                <div className="flex gap-2">
                    <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar en backlog…" className="bg-card border-border" />
                    <Button variant="outline" onClick={buscar}>Buscar</Button>
                </div>
                {res.map((t) => (
                    <div key={t.id} className="flex items-center justify-between rounded-lg border border-border px-3 py-2">
                        <span className="text-sm text-foreground">{t.title}</span>
                        <Button size="sm" onClick={() => router.post('/hoy/semana', { tarea_id: t.id })}>Agregar</Button>
                    </div>
                ))}
            </div>
        </MainLayout>
    );
}
