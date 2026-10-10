import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface DiaItem {
    id: number;
    titulo: string;
    ancla: string;
    posicion: number;
    estado: string;
    nota_cierre: string | null;
}

interface Props {
    fecha: string;
    dia: { id: number; items_visibles?: DiaItem[]; itemsVisibles?: DiaItem[] } | null;
    bloque: { etiqueta: string; hora_inicio: string; duracion_min: number } | null;
    rutina: { id: number; name: string; focus: string | null } | null;
    colaPrimero: { id: number; titulo: string; tipo: string } | null;
}

export default function HoyIndex({ fecha, dia, bloque, rutina, colaPrimero }: Props) {
    const items: DiaItem[] = (dia as any)?.items_visibles ?? (dia as any)?.itemsVisibles ?? (dia as any)?.items ?? [];
    const [notaId, setNotaId] = useState<number | null>(null);
    const notaForm = useForm({ estado: 'hecho', nota_cierre: '' });

    const marcar = (item: DiaItem, estado: string) => {
        if (estado === 'hecho' && notaId !== item.id) {
            setNotaId(item.id);
            return;
        }
        router.patch(`/hoy/items/${item.id}`, {
            estado,
            nota_cierre: notaId === item.id ? notaForm.data.nota_cierre : item.nota_cierre,
        }, { preserveScroll: true });
        setNotaId(null);
        notaForm.reset();
    };

    const soltar = (item: DiaItem) => {
        router.post(`/hoy/items/${item.id}/soltar`, {}, { preserveScroll: true });
    };

    const siguienteCola = () => {
        router.post('/hoy/cola/siguiente', {}, { preserveScroll: true });
    };

    return (
        <MainLayout>
            <Head title="Hoy" />
            <div className="mx-auto flex min-h-[calc(100vh-3rem)] w-full max-w-2xl flex-col gap-5 p-4 md:p-6">
                <p className="text-sm font-bold text-muted-foreground">{fecha}</p>

                {items.length === 0 ? (
                    <div className="flex flex-col gap-4 rounded-2xl bg-card border border-border p-8 text-center">
                        <p className="text-base font-medium text-foreground">Hoy no hay nada elegido.</p>
                        <Link href="/hoy/manana" className="mx-auto inline-flex min-h-[44px] items-center rounded-lg bg-primary px-5 py-2 text-sm font-black text-white hover:bg-primary/90">
                            Elegir
                        </Link>
                    </div>
                ) : (
                    <div className="flex flex-col gap-3 rounded-2xl bg-card border border-border p-4">
                        {items.map((item, i) => (
                            <div key={item.id} className="flex items-center gap-3 border-b border-border last:border-0 py-2">
                                <button
                                    onClick={() => marcar(item, item.estado === 'hecho' ? 'pendiente' : 'hecho')}
                                    aria-label={item.estado === 'hecho' ? 'Marcar pendiente' : 'Marcar hecho'}
                                    className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-md border ${item.estado === 'hecho' ? 'bg-primary border-primary text-white' : 'border-border text-transparent'}`}
                                >
                                    ✓
                                </button>
                                <span className="w-6 shrink-0 text-xs font-black text-muted-foreground">{String(i + 1).padStart(2, '0')}</span>
                                <div className="flex flex-1 flex-col min-w-0">
                                    <span className={`truncate text-base font-bold ${item.estado === 'hecho' ? 'line-through text-muted-foreground' : 'text-foreground'}`}>{item.titulo}</span>
                                    <span className="text-xs text-muted-foreground">{item.ancla}</span>
                                    {notaId === item.id && (
                                        <div className="mt-2 flex gap-2">
                                            <Input
                                                value={notaForm.data.nota_cierre}
                                                onChange={(e) => notaForm.setData('nota_cierre', e.target.value)}
                                                placeholder="¿cómo te sentiste?"
                                                className="bg-background border-border"
                                            />
                                            <Button size="sm" onClick={() => marcar(item, 'hecho')} className="bg-primary font-bold">OK</Button>
                                        </div>
                                    )}
                                </div>
                                {item.estado !== 'hecho' && (
                                    <button onClick={() => soltar(item)} className="shrink-0 text-xs font-bold text-muted-foreground hover:text-foreground">Soltar</button>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {(bloque || rutina) && (
                    <div className="rounded-xl bg-card/50 border border-border p-3 text-sm text-muted-foreground">
                        {bloque && <p>Bloque: {bloque.etiqueta} · {bloque.hora_inicio}</p>}
                        {rutina && <p>Gimnasio: {rutina.name}</p>}
                    </div>
                )}

                {colaPrimero && (
                    <div className="flex items-center justify-between rounded-xl bg-card/50 border border-border p-3">
                        <p className="text-sm text-muted-foreground">Hoy toca: <span className="font-bold text-foreground">{colaPrimero.titulo}</span></p>
                        <Button variant="outline" size="sm" onClick={siguienteCola}>Siguiente</Button>
                    </div>
                )}

                <Link href="/hoy/manana" className="text-xs font-bold text-muted-foreground hover:text-foreground">Elegir mañana →</Link>
            </div>
        </MainLayout>
    );
}
