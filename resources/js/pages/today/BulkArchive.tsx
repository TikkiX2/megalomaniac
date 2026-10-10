import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { csrfHeaders } from '@/lib/csrf';

export default function BulkArchive() {
    const [filter, setFilter] = useState('older_than');
    const [days, setDays] = useState(30);
    const [projectId, setProjectId] = useState('');
    const [preview, setPreview] = useState<{ total: number; primeros: string[] } | null>(null);
    const [confirmed, setConfirmed] = useState(false);
    const [done, setDone] = useState(false);

    const payload = () => ({
        filter,
        days: filter === 'older_than' ? days : undefined,
        project_id: filter === 'project' && projectId ? Number(projectId) : undefined,
    });

    const ver = async () => {
        setDone(false);
        setConfirmed(false);
        const r = await fetch('/today/bulk-archive/preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() },
            body: JSON.stringify(payload()),
        });
        if (r.ok) setPreview(await r.json());
    };

    const ejecutar = () => {
        router.post('/today/bulk-archive/run', { ...payload(), confirmed: true }, {
            onSuccess: () => {
                setDone(true);
                setPreview(null);
                setConfirmed(false);
            },
        });
    };

    return (
        <MainLayout>
            <Head title="Archivo masivo" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
                <h2 className="text-xl font-black text-white">Archivo masivo</h2>
                <Select value={filter} onValueChange={setFilter}>
                    <SelectTrigger className="bg-card border-border"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="nunca">Nunca</SelectItem>
                        <SelectItem value="older_than">Más de N días</SelectItem>
                        <SelectItem value="project">Por proyecto</SelectItem>
                        <SelectItem value="all">Todas</SelectItem>
                    </SelectContent>
                </Select>

                {filter === 'older_than' && (
                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        Días
                        <Input
                            type="number"
                            min={1}
                            value={days}
                            onChange={(e) => setDays(Number(e.target.value))}
                            className="w-24 bg-card border-border"
                        />
                    </label>
                )}
                {filter === 'project' && (
                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        ID de proyecto
                        <Input
                            type="number"
                            min={1}
                            value={projectId}
                            onChange={(e) => setProjectId(e.target.value)}
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
                            <input type="checkbox" checked={confirmed} onChange={(e) => setConfirmed(e.target.checked)} />
                            Confirmo que quiero archivarlas
                        </label>
                        <Button
                            disabled={!confirmed || preview.total === 0}
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
                        <Button size="sm" variant="outline" onClick={() => router.post('/today/archived/undo')}>Deshacer</Button>
                    </div>
                )}
            </div>
        </MainLayout>
    );
}
