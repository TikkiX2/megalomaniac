import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { Button } from '@/components/ui/button';

export default function Archivadas({ items }: { items: { id: number; title: string }[] }) {
    const [sel, setSel] = useState<number[]>([]);
    const toggle = (id: number) => setSel((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
    return (
        <MainLayout>
            <Head title="Archivadas" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-3 p-4">
                <h2 className="text-xl font-black text-white">Archivadas</h2>
                {items.map((t) => (
                    <label key={t.id} className="flex items-center gap-3 rounded-lg border border-border bg-card px-3 py-2">
                        <input type="checkbox" checked={sel.includes(t.id)} onChange={() => toggle(t.id)} />
                        <span className="text-sm text-foreground">{t.title}</span>
                    </label>
                ))}
                <Button disabled={sel.length === 0} onClick={() => router.post('/hoy/archivadas/restaurar', { ids: sel })} className="bg-primary font-bold">Restaurar {sel.length > 0 ? sel.length : ''}</Button>
            </div>
        </MainLayout>
    );
}
