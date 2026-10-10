import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { csrfHeaders } from '@/lib/csrf';

interface Props {
    pool: { id: number; title: string }[];
    overloaded: boolean;
}

export default function Week({ pool, overloaded }: Props) {
    const [q, setQ] = useState('');
    const [res, setRes] = useState<{ id: number; title: string }[]>([]);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

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

    return (
        <MainLayout>
            <Head title="Week" />
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
            </div>
        </MainLayout>
    );
}
