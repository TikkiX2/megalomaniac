import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';

interface Set {
    id: number;
    weight: string | number | null;
    reps: string | number | null;
}

interface WorkoutExercise {
    id: number;
    sets: Set[];
}

interface Workout {
    id: number;
    started_at: string;
    ended_at: string;
    routine?: { name: string; focus?: string } | null;
    exercises: WorkoutExercise[];
}

interface PaginatedWorkouts {
    data: Workout[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    workouts: PaginatedWorkouts;
}

function calcVolume(w: Workout): number {
    let vol = 0;
    for (const ex of w.exercises ?? []) {
        for (const s of ex.sets ?? []) {
            const weight = parseFloat(String(s.weight ?? '0'));
            const reps = parseInt(String(s.reps ?? '0'), 10);
            if (!isNaN(weight) && !isNaN(reps) && weight > 0 && reps > 0) {
                vol += weight * reps;
            }
        }
    }
    return vol;
}

function calcSets(w: Workout): number {
    return (w.exercises ?? []).reduce((acc, ex) => acc + (ex.sets?.length ?? 0), 0);
}

function formatDate(dt: string): string {
    if (!dt) return '—';
    try {
        return new Date(dt).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch {
        return dt.slice(0, 10);
    }
}

export default function History({ workouts }: Props) {
    const data = workouts?.data ?? [];

    return (
        <MainLayout>
            <Head title="Historial — Entrenamientos" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6 lg:p-8 animate-in fade-in duration-500">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-black tracking-tight text-white flex items-center gap-3">
                            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/15 border border-primary/30 text-primary">
                                <span className="material-symbols-outlined">history</span>
                            </span>
                            Historial
                        </h1>
                        <p className="mt-2 text-sm font-medium text-[#e8b4b4]">
                            {workouts.total} entrenamientos completados · paginado 10 por página
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/fitness/gym" className="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-black text-white shadow-[0_0_15px_rgba(239,68,68,0.25)] hover:bg-primary/90 transition">
                            <span className="material-symbols-outlined text-lg">arrow_back</span>
                            Volver a Entrenamiento
                        </Link>
                    </div>
                </div>

                <div className="overflow-hidden rounded-2xl border border-[#3e2121] bg-[#2b1a1a] shadow-xl">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-[#1c0f0f] border-b border-[#3e2121]">
                                <tr className="text-[11px] font-black uppercase tracking-widest text-[#e8b4b4]">
                                    <th className="px-5 py-4 whitespace-nowrap">Fecha</th>
                                    <th className="px-5 py-4 whitespace-nowrap">Rutina</th>
                                    <th className="px-5 py-4 whitespace-nowrap text-right">Volumen</th>
                                    <th className="px-5 py-4 whitespace-nowrap text-center">Sets</th>
                                    <th className="px-5 py-4 whitespace-nowrap text-center">Ejercicios</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#3e2121]/60">
                                {data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-16 text-center">
                                            <div className="flex flex-col items-center gap-3">
                                                <span className="material-symbols-outlined text-4xl text-[#3e2121]">fitness_center</span>
                                                <p className="text-sm font-bold text-white">Aún sin entrenamientos finalizados</p>
                                                <p className="text-xs font-medium text-[#e8b4b4] max-w-sm">Completa un workout (Finish Workout) para verlo aquí. El historial solo muestra workouts con <code className="px-1 py-0.5 rounded bg-[#1c0f0f] border border-[#3e2121]">ended_at</code> no nulo.</p>
                                                <Link href="/fitness/gym" className="mt-2 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-xs font-black uppercase tracking-widest text-white hover:bg-primary/90">
                                                    Ir a Entrenar
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    data.map((w) => {
                                        const vol = calcVolume(w);
                                        const sets = calcSets(w);
                                        return (
                                            <tr key={w.id} className="hover:bg-white/[0.04] transition">
                                                <td className="px-5 py-4 whitespace-nowrap">
                                                    <div className="flex flex-col">
                                                        <span className="font-bold text-white text-[13px]">{formatDate(w.ended_at || w.started_at)}</span>
                                                        <span className="text-[11px] font-medium text-[#e8b4b4]">ID #{w.id}</span>
                                                    </div>
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap">
                                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-[#1c0f0f] border border-[#3e2121] px-3 py-1 text-xs font-bold text-white">
                                                        <span className="material-symbols-outlined text-sm text-primary">exercise</span>
                                                        {w.routine?.name ?? 'Quick Session'}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap text-right">
                                                    <span className="font-black text-white tabular-nums">{vol.toLocaleString()} <span className="text-xs font-bold text-[#e8b4b4]">kg</span></span>
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap text-center">
                                                    <span className="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-primary/15 border border-primary/20 px-2 text-xs font-black text-primary">{sets}</span>
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap text-center">
                                                    <span className="text-xs font-bold text-[#e8b4b4]">{w.exercises.length}</span>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Paginación Ember */}
                    {workouts.links && workouts.links.length > 3 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[#3e2121] bg-[#1c0f0f]/50 px-4 py-3">
                            <span className="text-xs font-bold text-[#e8b4b4]">
                                Página {workouts.current_page} de {workouts.last_page} · {workouts.total} total
                            </span>
                            <div className="flex flex-wrap gap-1.5">
                                {workouts.links.map((link, idx) => {
                                    const isDisabled = !link.url;
                                    const label = link.label.replace('&laquo;', '«').replace('&raquo;', '»');
                                    return (
                                        <button
                                            key={`${link.label}-${idx}`}
                                            disabled={isDisabled}
                                            onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                                            className={`min-w-9 rounded-lg px-3 py-1.5 text-xs font-black border transition ${link.active ? 'bg-primary border-primary text-white shadow' : isDisabled ? 'bg-[#2b1a1a] border-[#3e2121] text-[#e8b4b4]/40 cursor-not-allowed' : 'bg-[#2b1a1a] border-[#3e2121] text-[#e8b4b4] hover:bg-[#3e2121] hover:text-white'}`}
                                            dangerouslySetInnerHTML={{ __html: label }}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>

                <p className="text-[11px] font-bold uppercase tracking-widest text-[#e8b4b4]/60 text-center">
                    Historial vía WorkoutController@history · Inertia paginate(10) · Ember palette #EF4444
                </p>
            </div>
        </MainLayout>
    );
}
