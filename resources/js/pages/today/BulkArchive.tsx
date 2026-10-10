import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { csrfHeaders } from '@/lib/csrf';

export default function BulkArchive() {
    const [filtro, setFiltro] = useState('mas_n_dias');
    const [dias, setDias] = useState(30);
    const [proyectoId, setProyectoId] = useState('');
    const [preview, setPreview] = useState<{ total: number; primeros: string[] } | null>(null);
    const [confirmado, setConfirmado] = useState(false);
    const [done, setDone] = useState(false);

    const payload = () => ({
        filtro,
        dias: filtro === 'mas_n_dias' ? dias : undefined,
        proyecto_id: filtro === 'proyecto' && proyectoId ? Number(proyectoId) : undefined,
    });

    const ver = async () => {
        setDone(false);
        setConfirmado(false);
        const r = await fetch('/today/bulk-archive/preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() },
            body: JSON.stringify(payload()),
        });
        if (r.ok) setPreview(await r.json());
    };

    const ejecutar = () => {
        router.post('/today/bulk-archive/run', { ...payload(), confirmado: true }, {
            onSuccess: () => {
                setDone(true);
                setPreview(null);
                setConfirmado(false);
            },
        });
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
                        <SelectItem value="proyecto">por proyecto</SelectItem>
                        <SelectItem value="todas">todas las pendientes</SelectItem>
                    </SelectContent>
                </Select>

                {filtro === 'mas_n_dias' && (
                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        Días
                        <Input
                            type="number"
                            min={1}
                            value={dias}
                            onChange={(e) => setDias(Number(e.target.value))}
                            className="w-24 bg-card border-border"
                        />
                    </label>
                )}
                {filtro === 'proyecto' && (
                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        ID de proyecto
                        <Input
                            type="number"
                            min={1}
                            value={proyectoId}
                            onChange={(e) => setProyectoId(e.target.value)}
                            className="w-32 bg-card border-border"
                        />
                    </label>
                )}

                <Button variant="outline" onClick={ver}>Ver preview</Button>

                {preview && (
                    <div className="rounded-xl border border-border bg-card p-4">
                        <p className="text-sm font-bold text-foreground">{preview.total} tareas para archivar</p>
                        {preview.primeros.map((t) => <p key={t} className="text-xs text-muted-foreground">{t}</p>)}
                        <label className="mt-3 flex items-center gap-2 text-sm text-muted-foreground">
                            <input type="checkbox" checked={confirmado} onChange={(e) => setConfirmado(e.target.checked)} />
                            Confirmo que quiero archivarlas
                        </label>
                        <Button
                            disabled={!confirmado || preview.total === 0}
                            onClick={ejecutar}
                            className="mt-3 bg-primary font-black"
                        >
                            Archivar {preview.total} tareas
                        </Button>
                    </div>
                )}

                {done && (
                    <div className="flex items-center justify-between rounded-xl border border-border bg-card p-4">
                        <p className="text-sm text-foreground">Listo. Tareas archivadas.</p>
                        <Button size="sm" variant="outline" onClick={() => router.post('/today/bulk-archive/undo')}>Deshacer</Button>
                    </div>
                )}
            </div>
        </MainLayout>
    );
}
