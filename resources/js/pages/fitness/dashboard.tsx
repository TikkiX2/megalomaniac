import { Head, usePage, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import type { SharedData } from '@/types';

interface WeeklyDay {
    date: string;
    label: string;
    volume: number;
}

interface StreakData {
    current: number;
    days: { date: string; label: string; hasWorkout: boolean }[];
}

interface Props {
    workoutCount: number;
    recentWorkouts: any[];
    caloriesToday: number;
    macrosToday?: { protein: number; carbs: number; fats: number };
    goals?: { calories: number; protein: number; carbs: number; fats: number };
    lowStockSupplements: any[];
    weeklyVolumeByDay?: WeeklyDay[];
    weeklyVolumes?: number[];
    weeklyVolumeTotal?: number;
    streak?: StreakData;
}

export default function Dashboard({ workoutCount, recentWorkouts, caloriesToday, macrosToday, goals, lowStockSupplements, weeklyVolumeByDay, weeklyVolumes, weeklyVolumeTotal, streak }: Props) {
    const { auth } = usePage<SharedData>().props;
    const calorieGoal = goals?.calories ?? 2400;
    const proteinGoal = goals?.protein ?? 180;
    const carbsGoal = goals?.carbs ?? 250;
    const fatsGoal = goals?.fats ?? 70;

    const proteinToday = Math.round(macrosToday?.protein ?? 0);
    const carbsToday = Math.round(macrosToday?.carbs ?? 0);
    const fatsToday = Math.round(macrosToday?.fats ?? 0);

    const caloriesRemaining = Math.max(0, calorieGoal - caloriesToday);
    const calorieProgress = Math.min(100, Math.round((caloriesToday / calorieGoal) * 100));
    const proteinProgress = Math.min(100, Math.round((proteinToday / proteinGoal) * 100));
    const carbsProgress = Math.min(100, Math.round((carbsToday / carbsGoal) * 100));
    const fatsProgress = Math.min(100, Math.round((fatsToday / fatsGoal) * 100));

    const fallbackWeekly = weeklyVolumeByDay ?? [];
    const hasVolumeData = fallbackWeekly.length > 0 && (weeklyVolumeTotal ?? 0) > 0;
    const maxVolume = Math.max(...(weeklyVolumes ?? fallbackWeekly.map((d) => d.volume) ?? [0]), 1);
    const streakCurrent = streak?.current ?? 0;
    const streakDays = streak?.days ?? [];

    const todayEs = new Date().toLocaleDateString('es-ES', { weekday: 'long', month: 'short', day: 'numeric' });

    return (
        <MainLayout>
            <Head title="Panel" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                            Bienvenido, {auth.user.name.split(' ')[0]}!</h2>
                        <p className="mt-1 text-base font-medium text-[#e8b4b4]">
                            Hoy es {todayEs}
                        </p>
                    </div>
                    <div className="flex gap-3">
                        <Link href="/fitness/nutrition" className="flex items-center gap-2 rounded-lg bg-[#3e2121] border border-[#3e2121] px-4 py-2 text-sm font-bold text-white transition hover:bg-white/10 active:scale-95">
                            <span className="material-symbols-outlined text-[20px]">add</span>
                            Registrar comida
                        </Link>
                        <Link href="/fitness/gym" className="flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-black text-white shadow-[0_0_20px_rgba(239,68,68,0.25)] transition hover:bg-primary/90 active:scale-95">
                            <span className="material-symbols-outlined font-bold" style={{ fontSize: '20px' }}>bolt</span>
                            Inicio rápido
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                    <div className="group relative overflow-hidden rounded-2xl bg-[#2b1a1a] p-6 shadow-xl border border-[#3e2121] md:col-span-2">
                        <div className="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-primary/5 blur-3xl group-hover:bg-primary/10 transition-colors duration-500"></div>
                        <div className="flex items-start justify-between relative z-10">
                            <div className="flex flex-col gap-1">
                                <span className="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-[#e8b4b4]">
                                    <span className="material-symbols-outlined text-primary text-[18px] fill-1">local_fire_department</span>
                                    Calorías restantes
                                </span>
                                <div className="flex items-baseline gap-2 mt-1">
                                    <span className="text-5xl font-black text-white">{caloriesRemaining.toLocaleString()}</span>
                                    <span className="text-sm font-bold text-[#e8b4b4]">kcal</span>
                                </div>
                            </div>
                            <div className="text-right flex flex-col gap-1">
                                <span className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Meta diaria</span>
                                <span className="text-sm font-black text-white">{calorieGoal.toLocaleString()}</span>
                            </div>
                        </div>
                        <div className="mt-8 flex flex-col gap-3 relative z-10">
                            <div className="flex justify-between text-[11px] font-black uppercase tracking-wider text-[#e8b4b4]">
                                <span>Progreso del día</span>
                                <span className="text-white">{calorieProgress}%</span>
                            </div>
                            <div className="h-3 w-full overflow-hidden rounded-full bg-[#1c0f0f] p-[2px]">
                                <div className="h-full rounded-full bg-gradient-to-r from-primary to-rose-300 shadow-[0_0_10px_rgba(239,68,68,0.3)] transition-all duration-1000 ease-out" style={{ width: `${calorieProgress}%` }}></div>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col gap-4 md:col-span-2 md:flex-row lg:col-span-2">
                        <div className="flex flex-1 flex-col justify-between rounded-2xl bg-[#2b1a1a] p-6 border border-[#3e2121] shadow-lg relative overflow-hidden">
                            <div className="absolute -bottom-10 -right-10 h-24 w-24 rounded-full bg-indigo-500/5 blur-2xl"></div>
                            <div className="flex items-center justify-between relative z-10">
                                <span className="text-[11px] font-bold uppercase tracking-widest text-[#e8b4b4]">Proteína</span>
                                <span className="text-[11px] font-black text-indigo-400">{proteinToday} / {proteinGoal}g</span>
                            </div>
                            <div className="mt-4 flex items-baseline gap-2 relative z-10">
                                <div className="text-3xl font-black text-white">{proteinProgress}%</div>
                                <span className={`text-[10px] font-bold uppercase tracking-tighter ${proteinProgress >= 100 ? 'text-primary' : proteinProgress >= 70 ? 'text-primary' : 'text-yellow-400'}`}>{proteinProgress >= 100 ? 'Completado' : proteinProgress >= 70 ? 'En camino' : 'Bajo'}</span>
                            </div>
                            <div className="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-[#1c0f0f] relative z-10">
                                <div className="h-full rounded-full bg-indigo-500 shadow-[0_0_8px_rgba(99,102,241,0.4)]" style={{ width: `${proteinProgress}%` }}></div>
                            </div>
                        </div>
                        <div className="flex flex-1 flex-col justify-between rounded-2xl bg-[#2b1a1a] p-6 border border-[#3e2121] shadow-lg relative overflow-hidden">
                            <div className="absolute -bottom-10 -right-10 h-24 w-24 rounded-full bg-sky-500/5 blur-2xl"></div>
                            <div className="flex items-center justify-between relative z-10">
                                <span className="text-[11px] font-bold uppercase tracking-widest text-[#e8b4b4]">Carbohidratos</span>
                                <span className="text-[11px] font-black text-sky-400">{carbsToday} / {carbsGoal}g</span>
                            </div>
                            <div className="mt-4 flex items-baseline gap-2 relative z-10">
                                <div className="text-3xl font-black text-white">{carbsProgress}%</div>
                                <span className="text-[10px] font-bold text-yellow-400 uppercase tracking-tighter">{carbsProgress >= 100 ? 'Completado' : 'Normal'}</span>
                            </div>
                            <div className="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-[#1c0f0f] relative z-10">
                                <div className="h-full rounded-full bg-sky-500 shadow-[0_0_8px_rgba(14,165,233,0.4)]" style={{ width: `${carbsProgress}%` }}></div>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col gap-4 lg:col-span-2 lg:row-span-1 xl:col-span-1">
                        <div className="flex flex-col justify-between rounded-2xl bg-[#2b1a1a] p-6 border border-[#3e2121] shadow-lg relative overflow-hidden flex-1">
                            <div className="flex items-center justify-between relative z-10">
                                <span className="text-[11px] font-bold uppercase tracking-widest text-[#e8b4b4]">Grasas</span>
                                <span className="text-[11px] font-black text-yellow-400">{fatsToday} / {fatsGoal}g</span>
                            </div>
                            <div className="mt-4 flex items-baseline gap-2 relative z-10">
                                <div className="text-3xl font-black text-white">{fatsProgress}%</div>
                                <span className="text-[10px] font-bold text-yellow-400 uppercase tracking-tighter">Normal</span>
                            </div>
                            <div className="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-[#1c0f0f] relative z-10">
                                <div className="h-full rounded-full bg-yellow-500 shadow-[0_0_8px_rgba(234,179,8,0.4)]" style={{ width: `${fatsProgress}%` }}></div>
                            </div>
                        </div>
                    </div>

                    <div className="group relative overflow-hidden rounded-2xl bg-[#2b1a1a] shadow-xl border border-[#3e2121] lg:col-span-2 lg:row-span-2">
                        <div className="absolute inset-0 z-0 bg-cover bg-center opacity-10 transition-transform duration-700 group-hover:scale-110" style={{ backgroundImage: "url('https://images.unsplash.com/photo-1540206351-d6465b3ac5c1?auto=format&fit=crop&q=80')" }}></div>
                        <div className="absolute inset-0 z-10 bg-gradient-to-t from-[#1c0f0f] via-[#1c0f0f]/60 to-transparent"></div>
                        <div className="relative z-20 flex h-full flex-col p-8">
                            <div className="mb-6 flex items-center justify-between">
                                <span className="flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-primary">
                                    <span className="h-1.5 w-1.5 rounded-full bg-primary animate-pulse"></span>
                                    {recentWorkouts[0] ? 'Última sesión' : '¿Listo?'}
                                </span>
                                <span className="text-xs font-bold text-[#e8b4b4]">{recentWorkouts[0] ? new Date(recentWorkouts[0].started_at).toLocaleDateString('es-ES') : '--'}</span>
                            </div>
                            <h3 className="mb-2 text-3xl font-black text-white drop-shadow-sm">{recentWorkouts[0]?.routine?.name || 'Fuerza Total'}</h3>
                            <p className="mb-8 text-sm font-semibold text-[#e8b4b4] leading-relaxed">{recentWorkouts[0] ? 'Tu última sesión fue intensa. ¡Sigue así!' : 'Empieza tu primera rutina y registra tus progresos hoy.'}</p>
                            <div className="mt-auto">
                                <Link href="/fitness/gym" className="group flex w-full items-center justify-center gap-3 rounded-xl bg-primary py-4 text-center font-black text-white shadow-[0_4px_25px_rgba(239,68,68,0.2)] transition-all hover:bg-primary/90 hover:shadow-[0_4px_30px_rgba(239,68,68,0.45)] active:scale-[0.98]">
                                    Entrenar ahora
                                    <span className="material-symbols-outlined font-bold transition-transform group-hover:translate-x-1">arrow_forward</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col rounded-2xl bg-[#2b1a1a] p-6 shadow-xl border border-[#3e2121] lg:col-span-1">
                        <div className="mb-5 flex items-center justify-between">
                            <h3 className="text-sm font-black uppercase tracking-widest text-white">Inventario</h3>
                            <div className="rounded-full bg-rose-500/10 px-2.5 py-1 text-[10px] font-black text-rose-500 border border-rose-500/20">{lowStockSupplements.length} bajo</div>
                        </div>
                        <div className="flex flex-col gap-4">
                            {lowStockSupplements.length > 0 ? (
                                lowStockSupplements.slice(0, 3).map((supp: any) => (
                                    <div key={supp.id} className="group flex flex-col gap-1.5">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm font-bold text-white group-hover:text-primary transition-colors">{supp.name}</span>
                                            <span className="text-[10px] font-black text-rose-400">{supp.stock_quantity} restantes</span>
                                        </div>
                                        <div className="h-1.5 w-full rounded-full bg-[#1c0f0f] p-[1px]">
                                            <div className="h-full rounded-full bg-rose-500" style={{ width: `${Math.max(15, (supp.stock_quantity / supp.low_stock_threshold) * 50)}%` }}></div>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="flex flex-col items-center justify-center py-6 text-center">
                                    <span className="material-symbols-outlined text-[#3e2121] text-4xl mb-2">check_circle</span>
                                    <p className="text-[11px] text-[#e8b4b4] font-bold uppercase tracking-tight italic">¡Todo abastecido!</p>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-col rounded-2xl bg-[#2b1a1a] p-6 shadow-xl border border-[#3e2121] lg:col-span-1">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-sm font-black uppercase tracking-widest text-white">Hidratación</h3>
                            <span className="material-symbols-outlined text-sky-400 fill-1">opacity</span>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="text-3xl font-black text-white leading-none tracking-tighter">1.8 <span className="text-xs text-[#e8b4b4]">litros</span></div>
                        </div>
                        <div className="mt-4 flex gap-1">
                            {[1, 2, 3, 4].map(i => <div key={i} className="h-6 w-2 rounded-full bg-sky-500/30"></div>)}
                            <div className="h-6 w-2 rounded-full bg-sky-500 animate-pulse"></div>
                            {[1, 2, 3].map(i => <div key={i} className="h-6 w-2 rounded-full bg-[#1c0f0f]"></div>)}
                        </div>
                    </div>

                    <Link href="/freelance/dashboard" className="flex group flex-col rounded-2xl bg-[#2b1a1a] p-6 shadow-xl border border-[#3e2121] lg:col-span-1 hover:border-primary/50 transition-all">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-sm font-black uppercase tracking-widest text-white group-hover:text-primary transition-colors">Freelance</h3>
                            <span className="material-symbols-outlined text-primary group-hover:rotate-12 transition-transform">work</span>
                        </div>
                        <div className="flex flex-col gap-1">
                            <span className="text-2xl font-black text-white tracking-widest leading-none">NEGOCIO</span>
                            <span className="text-[10px] font-bold text-[#e8b4b4] uppercase tracking-tighter italic">Gestiona proyectos y cotizaciones</span>
                        </div>
                        <div className="mt-auto pt-4 flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-widest">
                            Ir al panel
                            <span className="material-symbols-outlined text-xs">arrow_forward</span>
                        </div>
                    </Link>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2 rounded-2xl bg-card border border-border p-6 shadow-xl relative overflow-hidden">
                        <div className="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-primary/5 blur-2xl" />
                        <div className="relative z-10 flex items-center justify-between mb-5">
                            <div className="flex items-center gap-3">
                                <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 border border-primary/20 text-primary">
                                    <span className="material-symbols-outlined text-[20px]">monitoring</span>
                                </span>
                                <div>
                                    <h3 className="text-sm font-black uppercase tracking-widest text-white">Volumen semanal</h3>
                                    <p className="text-[11px] font-bold text-muted-foreground">kg × reps últimos 7 días</p>
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="text-2xl font-black text-white leading-none">{(weeklyVolumeTotal ?? 0).toLocaleString()}<span className="text-xs font-bold text-muted-foreground ml-1">kg</span></div>
                                <div className="text-[10px] font-black uppercase tracking-widest text-primary">{workoutCount} entrenos totales</div>
                            </div>
                        </div>
                        {hasVolumeData ? (
                            <div className="relative z-10">
                                <div className="flex items-end justify-between gap-2 h-[110px] px-1">
                                    {(weeklyVolumeByDay ?? []).map((d) => {
                                        const h = maxVolume > 0 ? Math.round((d.volume / maxVolume) * 100) : 0;
                                        const isToday = d.date === new Date().toISOString().slice(0, 10);
                                        return (
                                            <div key={d.date} className="flex flex-1 flex-col items-center gap-2">
                                                <span className="text-[10px] font-black text-muted-foreground tabular-nums">{d.volume > 0 ? `${(d.volume / 1000).toFixed(d.volume >= 1000 ? 1 : 0)}k` : ''}</span>
                                                <div className="flex w-full items-end justify-center h-[72px]">
                                                    <div className={`w-full max-w-[44px] rounded-t-lg transition-all duration-700 ${d.volume > 0 ? 'bg-primary shadow-[0_0_12px_rgba(239,68,68,0.35)]' : 'bg-[#1c0f0f] border border-border'} ${isToday ? 'ring-1 ring-primary/40' : ''}`} style={{ height: `${d.volume > 0 ? Math.max(12, h) : 8}%`, minHeight: d.volume > 0 ? 18 : 8 }} title={`${d.label} ${d.date}: ${d.volume} kg`} />
                                                </div>
                                                <span className={`text-[10px] font-black uppercase tracking-widest ${isToday ? 'text-primary' : 'text-muted-foreground'}`}>{d.label.slice(0, 2)}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                                <div className="mt-4 flex items-center justify-between text-[10px] font-bold uppercase tracking-widest text-muted-foreground border-t border-border pt-3">
                                    <span>Lun — Dom · barra roja = kg totales</span>
                                    <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-primary" /> chart-1 #EF4444</span>
                                </div>
                            </div>
                        ) : (
                            <div className="relative z-10 flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-[#1c0f0f]/60 py-10 text-center">
                                <span className="material-symbols-outlined text-3xl text-muted-foreground mb-2">bar_chart</span>
                                <p className="text-sm font-bold text-white">Aún sin volumen esta semana</p>
                                <p className="text-xs font-medium text-muted-foreground mt-1 max-w-sm">Registra un entreno para ver tu volumen kg×reps. Cada barra es un día en rojo Ember.</p>
                                <Link href="/fitness/gym" className="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-xs font-black uppercase tracking-widest text-white hover:bg-primary/90 transition">
                                    <span className="material-symbols-outlined text-sm">bolt</span> Ir a Entrenamiento
                                </Link>
                            </div>
                        )}
                    </div>
                    <div className="rounded-2xl bg-card border border-border p-6 shadow-xl flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between mb-5">
                                <div className="flex items-center gap-3">
                                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 border border-primary/20 text-primary">
                                        <span className="material-symbols-outlined text-[20px]">local_fire_department</span>
                                    </span>
                                    <div>
                                        <h3 className="text-sm font-black uppercase tracking-widest text-white">Racha</h3>
                                        <p className="text-[11px] font-bold text-muted-foreground">días consecutivos</p>
                                    </div>
                                </div>
                                <span className="text-3xl font-black text-white leading-none">{streakCurrent}<span className="text-xs text-muted-foreground ml-1">días</span></span>
                            </div>
                            {streakDays.length > 0 ? (
                                <div className="flex items-center justify-between gap-1.5">
                                    {streakDays.map((day) => (
                                        <div key={day.date} className="flex flex-col items-center gap-1.5 flex-1">
                                            <div className={`h-9 w-9 rounded-full border-2 flex items-center justify-center text-[10px] font-black transition-all ${day.hasWorkout ? 'bg-primary border-primary text-white shadow-[0_0_10px_rgba(239,68,68,0.4)]' : 'bg-[#1c0f0f] border-border text-muted-foreground'}`} title={`${day.label} ${day.date}${day.hasWorkout ? ' · entrenado' : ''}`}>
                                                {day.hasWorkout ? <span className="material-symbols-outlined text-sm font-bold">check</span> : <span className="h-1.5 w-1.5 rounded-full bg-muted-foreground/60" />}
                                            </div>
                                            <span className={`text-[9px] font-black uppercase tracking-widest ${day.hasWorkout ? 'text-primary' : 'text-muted-foreground'}`}>{day.label.slice(0, 2)}</span>
                                            <span className="text-[9px] font-bold text-muted-foreground">{day.date.slice(5)}</span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="flex gap-1.5">
                                    {Array.from({ length: 7 }).map((_, i) => (
                                        <div key={i} className="h-9 w-9 rounded-full bg-[#1c0f0f] border border-border flex-1" />
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="mt-6 rounded-xl bg-[#1c0f0f] border border-border p-3 flex items-center justify-between">
                            <span className="text-[11px] font-bold text-muted-foreground">{streakCurrent > 0 ? (streakCurrent >= 3 ? '¡En racha! Sigue así 🔥' : 'Buen comienzo, no pares') : 'Entrena hoy para iniciar tu racha'}</span>
                            <span className={`h-2 w-2 rounded-full ${streakCurrent > 0 ? 'bg-primary animate-pulse' : 'bg-border'}`} />
                        </div>
                        <div className="mt-3 text-[10px] font-bold uppercase tracking-widest text-muted-foreground/70 text-center">Últimos 7 días · punto rojo = día con entreno</div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
