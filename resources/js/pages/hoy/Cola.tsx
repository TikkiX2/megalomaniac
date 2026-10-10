import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export default function Cola({ items }: { items: { id: number; titulo: string; tipo: string }[] }) {
    const [titulo, setTitulo] = useState('');
    const [tipo, setTipo] = useState('serie');
    return (
        <MainLayout>
            <Head title="Cola" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-3 p-4">
                <h2 className="text-xl font-black text-white">Cola</h2>
                {items.map((it) => (
                    <div key={it.id} className="flex items-center justify-between rounded-lg border border-border bg-card px-3 py-2">
                        <span className="text-sm font-bold text-foreground">{it.titulo} <span className="text-xs text-muted-foreground">{it.tipo}</span></span>
                        <Button size="sm" variant="ghost" onClick={() => router.delete(`/hoy/cola/${it.id}`)}>Sacar</Button>
                    </div>
                ))}
                <div className="flex gap-2">
                    <Input value={titulo} onChange={(e) => setTitulo(e.target.value)} placeholder="Título…" className="bg-card border-border" />
                    <Select value={tipo} onValueChange={setTipo}>
                        <SelectTrigger className="w-36 bg-card border-border"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="serie">serie</SelectItem>
                            <SelectItem value="pelicula">pelicula</SelectItem>
                            <SelectItem value="libro">libro</SelectItem>
                            <SelectItem value="juego">juego</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button onClick={() => router.post('/hoy/cola', { titulo, tipo })} className="bg-primary font-bold">Agregar</Button>
                </div>
                {items.length > 0 && <Button variant="outline" onClick={() => router.post('/hoy/cola/siguiente')}>Siguiente</Button>}
            </div>
        </MainLayout>
    );
}
