import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export default function ArchivoMasivo() {
    const [filtro, setFiltro] = useState('mas_n_dias');
    const [preview, setPreview] = useState<{ total: number; primeros: string[] } | null>(null);
    const ver = async () => {
        const r = await fetch('/hoy/archivo-masivo/preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content ?? '' },
            body: JSON.stringify({ filtro, dias: 30 }),
        });
        if (r.ok) setPreview(await r.json());
    };
    return (
        <MainLayout>
            <Head title="Archivo masivo" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
                <h2 className="text-xl font-black text-white">Archivo masivo</h2>
                <Select value={filtro} onValueChange={setFiltro}>
                    <SelectTrigger className="bg-card border-border"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="nunca">archivadas: nunca</SelectItem>
                        <SelectItem value="mas_n_dias">más de N días</SelectItem>
                        <SelectItem value="todas">todas las pendientes</SelectItem>
                    </SelectContent>
                </Select>
                <Button variant="outline" onClick={ver}>Ver preview</Button>
                {preview && (
                    <div className="rounded-xl border border-border bg-card p-4">
                        <p className="text-sm font-bold text-foreground">Archivar {preview.total} tareas</p>
                        {preview.primeros.map((t) => <p key={t} className="text-xs text-muted-foreground">{t}</p>)}
                        <Button onClick={() => router.post('/hoy/archivo-masivo/ejecutar', { filtro, dias: 30, confirmado: true })} className="mt-3 bg-primary font-black">Archivar {preview.total} tareas</Button>
                    </div>
                )}
            </div>
        </MainLayout>
    );
}
