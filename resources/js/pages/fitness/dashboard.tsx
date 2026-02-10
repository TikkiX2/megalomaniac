import { Head, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import type { SharedData } from '@/types';

interface Props {
    workoutCount: number;
    recentWorkouts: any[];
    caloriesToday: number;
    lowStockSupplements: any[];
}

export default function Dashboard({ workoutCount, recentWorkouts, caloriesToday, lowStockSupplements }: Props) {
    const { auth } = usePage<SharedData>().props;
    const calorieGoal = 2800;
    const caloriesRemaining = Math.max(0, calorieGoal - caloriesToday);
    const calorieProgress = Math.min(100, Math.round((caloriesToday / calorieGoal) * 100));

    return (
        <MainLayout>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                {/* Welcome Section */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                            Welcome back, {auth.user.name.split(' ')[0]}!</h2>
                        <p className="mt-1 text-base font-medium text-[#92c9a4]">
                            Today is {new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' })}
                        </p>
                    </div>
                    <div className="flex gap-3">
                        <button
                            className="flex items-center gap-2 rounded-lg bg-[#23482f] border border-[#23482f] px-4 py-2 text-sm font-bold text-white transition hover:bg-white/10 active:scale-95">
                            <span className="material-symbols-outlined text-[20px]">add</span>
                            Log Meal
                        </button>
                        <button
                            className="flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-black text-[#102216] shadow-[0_0_20px_rgba(19,236,91,0.25)] transition hover:bg-green-400 active:scale-95">
                            <span className="material-symbols-outlined font-bold" style={{ fontSize: '20px' }}>bolt</span>
                            Quick Start
                        </button>
                    </div>
                </div>

                {/* Bento Grid Layout */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4">
                    {/* 1. Calories Card (Wide) */}
                    <div
                        className="group relative overflow-hidden rounded-2xl bg-[#193322] p-6 shadow-xl border border-[#23482f] md:col-span-2">
                        <div className="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-primary/5 blur-3xl group-hover:bg-primary/10 transition-colors duration-500"></div>
                        <div className="flex items-start justify-between relative z-10">
                            <div className="flex flex-col gap-1">
                                <span
                                    className="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-[#92c9a4]">
                                    <span className="material-symbols-outlined text-primary text-[18px] fill-1">local_fire_department</span>
                                    Calories Remaining
                                </span>
                                <div className="flex items-baseline gap-2 mt-1">
                                    <span className="text-5xl font-black text-white">{caloriesRemaining.toLocaleString()}</span>
                                    <span className="text-sm font-bold text-[#92c9a4]">kcal</span>
                                </div>
                            </div>
                            <div className="text-right flex flex-col gap-1">
                                <span className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Daily Goal</span>
                                <span className="text-sm font-black text-white">{calorieGoal.toLocaleString()}</span>
                            </div>
                        </div>
                        <div className="mt-8 flex flex-col gap-3 relative z-10">
                            <div className="flex justify-between text-[11px] font-black uppercase tracking-wider text-[#92c9a4]">
                                <span>Day Progress</span>
                                <span className="text-white">{calorieProgress}%</span>
                            </div>
                            <div className="h-3 w-full overflow-hidden rounded-full bg-[#102216] p-[2px]">
                                <div className="h-full rounded-full bg-gradient-to-r from-primary to-green-300 shadow-[0_0_10px_rgba(19,236,91,0.3)] transition-all duration-1000 ease-out"
                                    style={{ width: `${calorieProgress}%` }}></div>
                            </div>
                        </div>
                    </div>

                    {/* 2. Macros (Placeholder) */}
                    <div className="flex flex-col gap-6 md:col-span-2 md:flex-row lg:col-span-2">
                        <div className="flex flex-1 flex-col justify-between rounded-2xl bg-[#193322] p-6 border border-[#23482f] shadow-lg relative overflow-hidden">
                            <div className="absolute -bottom-10 -right-10 h-24 w-24 rounded-full bg-indigo-500/5 blur-2xl"></div>
                            <div className="flex items-center justify-between relative z-10">
                                <span className="text-[11px] font-bold uppercase tracking-widest text-[#92c9a4]">Protein</span>
                                <span className="text-[11px] font-black text-indigo-400">145 / 180g</span>
                            </div>
                            <div className="mt-4 flex items-baseline gap-2 relative z-10">
                                <div className="text-3xl font-black text-white">80%</div>
                                <span className="text-[10px] font-bold text-green-400 uppercase tracking-tighter">On Track</span>
                            </div>
                            <div className="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-[#102216] relative z-10">
                                <div className="h-full rounded-full bg-indigo-500 shadow-[0_0_8px_rgba(99,102,241,0.4)]" style={{ width: '80%' }}></div>
                            </div>
                        </div>
                        <div className="flex flex-1 flex-col justify-between rounded-2xl bg-[#193322] p-6 border border-[#23482f] shadow-lg relative overflow-hidden">
                            <div className="absolute -bottom-10 -right-10 h-24 w-24 rounded-full bg-sky-500/5 blur-2xl"></div>
                            <div className="flex items-center justify-between relative z-10">
                                <span className="text-[11px] font-bold uppercase tracking-widest text-[#92c9a4]">Carbs</span>
                                <span className="text-[11px] font-black text-sky-400">210 / 250g</span>
                            </div>
                            <div className="mt-4 flex items-baseline gap-2 relative z-10">
                                <div className="text-3xl font-black text-white">84%</div>
                                <span className="text-[10px] font-bold text-yellow-400 uppercase tracking-tighter">Normal</span>
                            </div>
                            <div className="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-[#102216] relative z-10">
                                <div className="h-full rounded-full bg-sky-500 shadow-[0_0_8px_rgba(14,165,233,0.4)]" style={{ width: '84%' }}></div>
                            </div>
                        </div>
                    </div>

                    {/* 3. Recent Workouts */}
                    <div
                        className="group relative overflow-hidden rounded-2xl bg-[#193322] shadow-xl border border-[#23482f] lg:col-span-2 lg:row-span-2">
                        <div className="absolute inset-0 z-0 bg-cover bg-center opacity-10 transition-transform duration-700 group-hover:scale-110"
                            style={{ backgroundImage: "url('https://images.unsplash.com/photo-1540206351-d6465b3ac5c1?auto=format&fit=crop&q=80')" }}>
                        </div>
                        <div
                            className="absolute inset-0 z-10 bg-gradient-to-t from-[#102216] via-[#102216]/60 to-transparent">
                        </div>
                        <div className="relative z-20 flex h-full flex-col p-8">
                            <div className="mb-6 flex items-center justify-between">
                                <span
                                    className="flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-primary">
                                    <span className="h-1.5 w-1.5 rounded-full bg-primary animate-pulse"></span>
                                    {recentWorkouts[0] ? 'Last Session' : 'Ready?'}
                                </span>
                                <span className="text-xs font-bold text-[#92c9a4]">
                                    {recentWorkouts[0] ? new Date(recentWorkouts[0].started_at).toLocaleDateString() : '--'}
                                </span>
                            </div>
                            <h3 className="mb-2 text-3xl font-black text-white drop-shadow-sm">
                                {recentWorkouts[0]?.routine?.name || 'Power Builder'}
                            </h3>
                            <p className="mb-8 text-sm font-semibold text-[#92c9a4] leading-relaxed">
                                {recentWorkouts[0] ? 'Your last session was intense. Keep pushing!' : 'Start your first routine and track your gains today.'}
                            </p>

                            <div className="mt-auto">
                                <button
                                    className="group flex w-full items-center justify-center gap-3 rounded-xl bg-primary py-4 text-center font-black text-[#102216] shadow-[0_4px_25px_rgba(19,236,91,0.2)] transition-all hover:bg-green-400 hover:shadow-[0_4px_30px_rgba(19,236,91,0.45)] active:scale-[0.98]">
                                    Start Workout
                                    <span className="material-symbols-outlined font-bold transition-transform group-hover:translate-x-1">arrow_forward</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* 4. Inventory Alert */}
                    <div className="flex flex-col rounded-2xl bg-[#193322] p-6 shadow-xl border border-[#23482f] lg:col-span-1">
                        <div className="mb-5 flex items-center justify-between">
                            <h3 className="text-sm font-black uppercase tracking-widest text-white">Inventory</h3>
                            <div className="rounded-full bg-rose-500/10 px-2.5 py-1 text-[10px] font-black text-rose-500 border border-rose-500/20">{lowStockSupplements.length} Low</div>
                        </div>
                        <div className="flex flex-col gap-4">
                            {lowStockSupplements.length > 0 ? (
                                lowStockSupplements.slice(0, 3).map((supp: any) => (
                                    <div key={supp.id} className="group flex flex-col gap-1.5">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm font-bold text-white group-hover:text-primary transition-colors">{supp.name}</span>
                                            <span className="text-[10px] font-black text-rose-400">{supp.stock_quantity} left</span>
                                        </div>
                                        <div className="h-1.5 w-full rounded-full bg-[#102216] p-[1px]">
                                            <div className="h-full rounded-full bg-rose-500" style={{ width: `${Math.max(15, (supp.stock_quantity / supp.low_stock_threshold) * 50)}%` }}></div>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="flex flex-col items-center justify-center py-6 text-center">
                                    <span className="material-symbols-outlined text-[#23482f] text-4xl mb-2">check_circle</span>
                                    <p className="text-[11px] text-[#92c9a4] font-bold uppercase tracking-tight italic">All stocked up!</p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* 5. Hydration (Small Card) */}
                    <div className="flex flex-col rounded-2xl bg-[#193322] p-6 shadow-xl border border-[#23482f] lg:col-span-1">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-sm font-black uppercase tracking-widest text-white">Hydration</h3>
                            <span className="material-symbols-outlined text-sky-400 fill-1">opacity</span>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="text-3xl font-black text-white leading-none tracking-tighter">1.8 <span className="text-xs text-[#92c9a4]">liters</span></div>
                        </div>
                        <div className="mt-4 flex gap-1">
                            {[1, 2, 3, 4].map(i => <div key={i} className="h-6 w-2 rounded-full bg-sky-500/30"></div>)}
                            <div className="h-6 w-2 rounded-full bg-sky-500 animate-pulse"></div>
                            {[1, 2, 3].map(i => <div key={i} className="h-6 w-2 rounded-full bg-[#102216]"></div>)}
                        </div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
