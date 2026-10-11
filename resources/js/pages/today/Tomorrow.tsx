import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ANCHOR_LABELS } from '@/components/today/DayItemRow';
import ProjectCards from '@/components/today/ProjectCards';
import TaskFilterBar from '@/components/today/TaskFilterBar';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import MainLayout from '@/layouts/main-layout';
import type { ActiveFilters, FilterOption, TaskRow } from '@/types/today';

const ANCHORS = ['wake_up', 'after_meal', 'after_gym', 'after_shower', 'before_sleep', 'no_anchor'];

const NO_FILTER_MATCHES = 'No hay tareas que coincidan con los filtros.';

interface PendingItem { id: number; title: string; task_id: number | null; anchor: string; }
interface DayItemShape { id: number; title: string; task_id: number | null; anchor: string; position: number; state?: string; }
interface SelectedItem { title: string; anchor: string; task_id: number | null; }
interface Props {
    fecha: string;
    pool: TaskRow[];
    pendientesAyer: PendingItem[];
    diaManana: { visible_items?: DayItemShape[]; visibleItems?: DayItemShape[] } | null;
    projects: FilterOption[];
    availableTags: string[];
    filters: ActiveFilters;
}

/** Quita claves undefined o vacías para no mandar params de filtro inútiles. */
function cleanFilters(params: ActiveFilters): Record<string, string | number> {
    const cleaned: Record<string, string | number> = {};
    (Object.keys(params) as (keyof ActiveFilters)[]).forEach((key) => {
        const value = params[key];
        if (value !== undefined && value !== '') {
            cleaned[key] = value;
        }
    });
    return cleaned;
}

export default function Tomorrow({
    fecha,
    pool,
    pendientesAyer,
    diaManana,
    projects,
    availableTags,
    filters,
}: Props) {
    const existentes: DayItemShape[] = diaManana?.visible_items ?? diaManana?.visibleItems ?? [];
    const [sel, setSel] = useState<Record<number, SelectedItem>>(() => {
        const m: Record<number, SelectedItem> = {};
        existentes.forEach((it, i) => { m[i] = { title: it.title, anchor: it.anchor, task_id: it.task_id }; });
        return m;
    });
    const count = Object.keys(sel).filter((k) => sel[Number(k)]?.title).length;

    const toggle = (t: TaskRow) => {
        const entries = Object.entries(sel);
        const found = entries.find(([, v]) => v.task_id === t.id);
        if (found) {
            const n = { ...sel };
            delete n[Number(found[0])];
            setSel(n);
        } else if (count < 3) {
            const idx = [0, 1, 2].find((i) => !(i in sel)) ?? 0;
            setSel({ ...sel, [idx]: { title: t.title, anchor: 'no_anchor', task_id: t.id } });
        }
    };

    const guardar = () => {
        const items = Object.entries(sel).map(([k, v], i) => ({
            task_id: v.task_id, title: v.title, anchor: v.anchor, position: Number(k) + 1 > 3 ? i + 1 : Number(k) + 1,
        }));
        router.post('/today/tomorrow', { items });
    };

    const dejarVacio = () => router.post('/today/tomorrow', { items: [] });

    const applyFilters = (next: ActiveFilters) => {
        const cleaned = cleanFilters(next);
        // Sin claves activas hay que mandar cleared=1: si mandamos params vacíos, el server
        // relee su memoria y los filtros viejos reaparecerían.
        router.get('/today/tomorrow', Object.keys(cleaned).length > 0 ? cleaned : { cleared: 1 }, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        router.get('/today/tomorrow', { cleared: 1 }, { preserveState: true, replace: true });
    };

    const hasActiveFilters = Object.keys(filters).length > 0;

    const renderPoolRow = (task: TaskRow) => {
        const active = Object.values(sel).some((v) => v.task_id === task.id);
        return (
            <button
                type="button"
                onClick={() => toggle(task)}
                className={`flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left ${active ? 'border-primary bg-primary/10' : 'border-border'}`}
            >
                <span className="min-w-0 flex-1 truncate text-sm font-bold text-foreground">{task.title}</span>
                <span className="text-xs text-muted-foreground">{active ? 'Elegida' : 'Elegir'}</span>
            </button>
        );
    };

    return (
        <MainLayout>
            <Head title="Mañana" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-5 p-4 md:p-6">
                <div className="flex items-baseline justify-between">
                    <h2 className="text-xl font-black text-white">Elegí hasta 3 <span className="text-sm font-bold text-muted-foreground">· {fecha}</span></h2>
                    <span className="text-xs font-bold text-muted-foreground">{count} de 3 elegidas</span>
                </div>

                <TaskFilterBar
                    filters={filters}
                    projects={projects}
                    tags={availableTags}
                    onChange={applyFilters}
                    onClear={clearFilters}
                />

                {pendientesAyer.length > 0 && (
                    <div className="flex flex-col gap-2 rounded-xl bg-card border border-border p-4">
                        <p className="text-xs font-black uppercase tracking-widest text-muted-foreground">Ayer quedó pendiente</p>
                        {pendientesAyer.map((p) => (
                            <div key={p.id} className="flex items-center justify-between gap-2">
                                <span className="text-sm font-bold text-foreground">{p.title}</span>
                                <div className="flex gap-2">
                                    <Button size="sm" variant="outline" onClick={() => router.post(`/today/tomorrow/${p.id}/put-today`)}>Ponerlo hoy</Button>
                                    <Button size="sm" variant="ghost" onClick={() => router.post(`/today/items/${p.id}/release`)}>Soltarlo</Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {pool.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        {hasActiveFilters ? NO_FILTER_MATCHES : 'No hay tareas en la semana. Agregalas desde Semana.'}
                    </p>
                )}
                {pool.length > 0 && <ProjectCards items={pool} renderRow={renderPoolRow} showCount collapsible order="count" />}

                {Object.entries(sel).map(([k, v]) => (
                    <div key={k} className="flex gap-2">
                        <span className="text-xs font-black text-muted-foreground pt-2">{Number(k) + 1}</span>
                        <span className="flex-1 text-sm font-bold text-foreground">{v.title}</span>
                        <Select value={v.anchor} onValueChange={(a) => setSel({ ...sel, [k]: { ...v, anchor: a } })}>
                            <SelectTrigger className="w-44 bg-card border-border"><SelectValue /></SelectTrigger>
                            <SelectContent>{ANCHORS.map((a) => <SelectItem key={a} value={a}>{ANCHOR_LABELS[a] ?? a}</SelectItem>)}</SelectContent>
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
