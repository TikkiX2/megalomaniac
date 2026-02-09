import GymLayout from '@/layouts/gym-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Set {
    id: number;
    set_number: number;
    weight: string;
    reps: string;
    rpe: string;
    completed: boolean;
}

interface WorkoutExercise {
    id: number;
    exercise_id: number;
    exercise: {
        id: number;
        name: string;
        muscle_group: string;
        type: string;
    };
    sets: Set[];
    previous: Set[] | null;
}

interface Workout {
    id: number;
    started_at: string;
    routine?: {
        name: string;
        focus: string;
    };
    exercises: WorkoutExercise[];
}

interface Props {
    exercises: any[];
    routines: any[];
    activeWorkout: Workout | null;
    suggestedRoutine: any | null; // Keep it 'any' for simplicity, or define Routine interface if strict typing needed
}

export default function GymRoutine({ exercises: libraryExercises, routines, activeWorkout: initialActiveWorkout, suggestedRoutine }: Props) {
    const [activeWorkout, setActiveWorkout] = useState<Workout | null>(initialActiveWorkout);
    const [searchQuery, setSearchQuery] = useState('');
    const [time, setTime] = useState('00:00:00');

    // Timer logic
    useEffect(() => {
        if (!activeWorkout?.started_at) {
            setTime('00:00:00');
            return;
        }

        const startTime = new Date(activeWorkout.started_at).getTime();

        const updateTimer = () => {
            const now = new Date().getTime();
            const diff = now - startTime;

            if (diff < 0) {
                setTime('00:00:00');
                return;
            }

            const h = Math.floor(diff / (1000 * 60 * 60));
            const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((diff % (1000 * 60)) / 1000);

            setTime(
                `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
            );
        };

        const interval = setInterval(updateTimer, 1000);
        updateTimer();

        return () => clearInterval(interval);
    }, [activeWorkout?.started_at]);

    const handleFinishWorkout = () => {
        if (!activeWorkout) return;

        router.patch(`/gym/workouts/${activeWorkout.id}`, {
            ended_at: new Date().toISOString(),
        }, {
            onSuccess: () => {
                setActiveWorkout(null);
            }
        });
    };

    const handleLogSet = (workoutExerciseId: number, setNumber: number, data: Partial<Set>) => {
        router.post(`/gym/workout-exercises/${workoutExerciseId}/sets`, {
            set_number: setNumber,
            ...data
        }, {
            preserveScroll: true,
            onSuccess: (page) => {
                const updatedWorkout = (page.props as any).activeWorkout;
                if (updatedWorkout) setActiveWorkout(updatedWorkout);
            }
        });
    };

    const handleAddExercise = (exerciseId: number) => {
        if (!activeWorkout) {
            router.post('/gym/workouts', {
                started_at: new Date().toISOString()
            }, {
                onSuccess: (page) => {
                    const newWorkout = (page.props as any).activeWorkout;
                    if (newWorkout) {
                        router.post(`/gym/workouts/${newWorkout.id}/exercises`, { exercise_id: exerciseId });
                    }
                }
            });
            return;
        }

        router.post(`/gym/workouts/${activeWorkout.id}/exercises`, {
            exercise_id: exerciseId,
        }, {
            preserveScroll: true
        });
    };

    const currentRoutineName = activeWorkout?.routine?.name || 'Quick Session';
    const currentFocus = activeWorkout?.routine?.focus || 'Custom Training';
    const today = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });

    return (
        <GymLayout>
            <Head title="Today's Session - Gym Tracker" />

            <div className="flex h-full flex-col overflow-hidden relative">
                {/* Top Action Bar */}
                <header className="hidden lg:flex items-center justify-between px-8 py-5 border-b border-[#23482f] bg-[#102216] sticky top-0 z-20">
                    <div className="flex flex-col">
                        <h2 className="text-2xl font-bold">Today's Session</h2>
                        <p className="text-[#92c9a4] text-sm">Keep pushing, you're doing great.</p>
                    </div>
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2 text-[#92c9a4] bg-[#23482f]/50 px-3 py-1.5 rounded-lg border border-[#23482f]">
                            <span className="material-symbols-outlined text-lg">timer</span>
                            <span className="text-sm font-mono font-medium">{time}</span>
                        </div>
                        <button
                            onClick={handleFinishWorkout}
                            disabled={!activeWorkout}
                            className={`bg-primary hover:bg-primary-hover text-[#102216] px-6 py-2.5 rounded-lg font-bold flex items-center gap-2 transition-all shadow-[0_0_15px_rgba(19,236,91,0.3)] hover:scale-105 active:scale-95 ${!activeWorkout ? 'opacity-50 cursor-not-allowed' : ''}`}
                        >
                            <span className="material-symbols-outlined font-bold">check_circle</span>
                            Finish Workout
                        </button>
                    </div>
                </header>

                <div className="flex-1 flex overflow-hidden">
                    {/* Scrollable Workout Area */}
                    <div className="flex-1 overflow-y-auto p-4 lg:p-8 space-y-8 h-full custom-scrollbar">
                        {/* Routine Info */}
                        <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 animate-in fade-in slide-in-from-top-4 duration-700">
                            <div>
                                <div className="flex items-center gap-3 mb-2">
                                    <h1 className="text-3xl lg:text-4xl font-black text-white tracking-tight leading-tight">
                                        {today} <span className="text-primary italic">•</span> {currentRoutineName}
                                    </h1>
                                    <button
                                        onClick={() => router.get('/fitness/routines')}
                                        className="text-[#92c9a4] hover:text-primary transition-colors flex items-center gap-1 group"
                                        title="Set Routine"
                                    >
                                        <span className="material-symbols-outlined">edit_calendar</span>
                                        <span className="text-[10px] uppercase font-black tracking-widest opacity-0 group-hover:opacity-100 transition-opacity">Set Routine</span>
                                    </button>
                                </div>
                                <p className="text-[#92c9a4] text-lg font-medium">Focus: {currentFocus}</p>
                            </div>
                            <div className="flex gap-3">
                                <div className="relative group w-full md:w-96">
                                    <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-[#92c9a4]">
                                        <span className="material-symbols-outlined">search</span>
                                    </div>
                                    <input
                                        className="block w-full p-3 pl-10 text-sm bg-[#23482f] border-2 border-transparent rounded-xl placeholder-[#92c9a4] text-white focus:ring-0 focus:border-primary transition-all outline-none"
                                        placeholder="Search exercises to add..."
                                        type="text"
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                    />
                                    <div className="absolute right-3 top-2.5 hidden group-focus-within:block bg-[#102216] border border-[#23482f] rounded px-1.5 py-0.5 text-[10px] font-bold text-[#92c9a4]">
                                        ⌘K
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Exercise List */}
                        <div className="space-y-6 max-w-5xl animate-in fade-in slide-in-from-bottom-8 duration-700 delay-200">
                            {activeWorkout?.exercises.map((workoutExercise) => (
                                <div key={workoutExercise.id} className="bg-[#193322] rounded-2xl overflow-hidden border border-[#23482f] shadow-lg group hover:border-[#23482f] transition-all">
                                    {/* Card Header */}
                                    <div className="p-4 border-b border-[#23482f] flex flex-col sm:flex-row sm:items-center justify-between bg-white/5 gap-4">
                                        <div className="flex items-center gap-4">
                                            <div className="size-12 rounded-xl bg-[#23482f] flex items-center justify-center text-primary border border-[#23482f]">
                                                <span className="material-symbols-outlined">fitness_center</span>
                                            </div>
                                            <div>
                                                <h3 className="text-lg font-bold text-white flex items-center gap-2">
                                                    {workoutExercise.exercise.name}
                                                    <span className="material-symbols-outlined text-[#92c9a4] text-sm cursor-help hover:text-primary transition-colors" title="View History">history</span>
                                                </h3>
                                                <div className="flex items-center gap-3 text-sm">
                                                    <span className="text-[#92c9a4] font-medium">{workoutExercise.exercise.muscle_group} • {workoutExercise.exercise.type}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <button className="p-2 text-[#92c9a4] hover:text-white hover:bg-white/5 rounded-lg transition-colors">
                                                <span className="material-symbols-outlined">videocam</span>
                                            </button>
                                            <button className="p-2 text-[#92c9a4] hover:text-red-400 hover:bg-red-400/10 rounded-lg transition-colors">
                                                <span className="material-symbols-outlined">delete</span>
                                            </button>
                                        </div>
                                    </div>

                                    {/* Card Body (Sets) */}
                                    <div className="p-4">
                                        <div className="grid grid-cols-[30px_1fr_1fr_1fr_1fr_40px] gap-4 mb-2 text-[10px] uppercase tracking-widest font-black text-[#92c9a4] px-2">
                                            <div className="text-center">Set</div>
                                            <div>Previous</div>
                                            <div>kg</div>
                                            <div>Reps</div>
                                            <div>RPE</div>
                                            <div className="text-center"><span className="material-symbols-outlined text-sm">check</span></div>
                                        </div>

                                        {/* Set Rows */}
                                        <div className="space-y-2">
                                            {workoutExercise.sets.map((set, setIndex) => (
                                                <div
                                                    key={set.id}
                                                    className={`grid grid-cols-[30px_1fr_1fr_1fr_1fr_40px] gap-4 items-center rounded-xl p-2 border transition-all ${set.completed
                                                        ? 'bg-primary/5 border-primary/20'
                                                        : 'border-transparent hover:bg-white/5'
                                                        }`}
                                                >
                                                    <div className={`text-center font-black ${set.completed ? 'text-primary' : 'text-white'}`}>{set.set_number}</div>
                                                    <div className="text-[#92c9a4] text-[10px] font-bold">
                                                        {workoutExercise.previous?.find(ps => ps.set_number === set.set_number)
                                                            ? `${workoutExercise.previous.find(ps => ps.set_number === set.set_number)?.weight}kg x ${workoutExercise.previous.find(ps => ps.set_number === set.set_number)?.reps}`
                                                            : '-'}
                                                    </div>
                                                    <input
                                                        className="bg-[#23482f] border-none rounded-lg text-white text-center font-bold focus:ring-2 focus:ring-primary py-1.5 h-9 w-full"
                                                        placeholder="-"
                                                        type="text"
                                                        defaultValue={set.weight || ''}
                                                        onBlur={(e) => handleLogSet(workoutExercise.id, set.set_number, { weight: e.target.value })}
                                                    />
                                                    <input
                                                        className="bg-[#23482f] border-none rounded-lg text-white text-center font-bold focus:ring-2 focus:ring-primary py-1.5 h-9 w-full"
                                                        placeholder="-"
                                                        type="text"
                                                        defaultValue={set.reps || ''}
                                                        onBlur={(e) => handleLogSet(workoutExercise.id, set.set_number, { reps: e.target.value })}
                                                    />
                                                    <input
                                                        className="bg-[#23482f] border-none rounded-lg text-white text-center font-bold focus:ring-2 focus:ring-primary py-1.5 h-9 w-full"
                                                        placeholder="-"
                                                        type="text"
                                                        defaultValue={set.rpe || ''}
                                                        onBlur={(e) => handleLogSet(workoutExercise.id, set.set_number, { rpe: e.target.value })}
                                                    />
                                                    <button
                                                        onClick={() => handleLogSet(workoutExercise.id, set.set_number, { completed: !set.completed })}
                                                        className={`flex items-center justify-center h-9 w-full rounded-lg transition-all ${set.completed
                                                            ? 'bg-primary text-[#102216]'
                                                            : 'bg-[#23482f] text-[#92c9a4] hover:bg-primary hover:text-[#102216]'
                                                            }`}
                                                    >
                                                        <span className="material-symbols-outlined text-lg font-black">check</span>
                                                    </button>
                                                </div>
                                            ))}
                                        </div>

                                        <button
                                            onClick={() => handleLogSet(workoutExercise.id, (workoutExercise.sets.length || 0) + 1, {})}
                                            className="w-full mt-4 py-3 border border-dashed border-[#23482f] rounded-xl text-[#92c9a4] hover:text-white hover:border-primary hover:bg-primary/5 transition-all flex items-center justify-center gap-2 text-xs font-black uppercase tracking-widest"
                                        >
                                            <span className="material-symbols-outlined text-lg">add</span> Add Set
                                        </button>
                                    </div>
                                </div>
                            ))}

                            {!activeWorkout && (
                                suggestedRoutine ? (
                                    <div className="bg-[#193322]/50 rounded-2xl border-2 border-dashed border-[#23482f] p-10 flex flex-col items-center justify-center text-center gap-4 hover:border-primary/50 transition-all cursor-pointer group"
                                        onClick={() => {
                                            router.post('/gym/workouts', {
                                                routine_id: suggestedRoutine.id,
                                                started_at: new Date().toISOString()
                                            }, {
                                                onSuccess: () => {
                                                    router.visit(window.location.pathname, { preserveScroll: false });
                                                }
                                            });
                                        }}
                                    >
                                        <div className="size-16 rounded-full bg-[#23482f] group-hover:bg-primary group-hover:text-[#102216] text-[#92c9a4] flex items-center justify-center transition-all shadow-lg group-hover:scale-110">
                                            <span className="material-symbols-outlined text-3xl font-bold">play_arrow</span>
                                        </div>
                                        <div>
                                            <h3 className="text-xl font-black text-white">Start {suggestedRoutine.name}</h3>
                                            <p className="text-[#92c9a4] font-medium">Scheduled for today. Let's get to work!</p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="bg-[#193322]/50 rounded-2xl border-2 border-dashed border-[#23482f] p-10 flex flex-col items-center justify-center text-center gap-4 hover:border-primary/50 transition-all cursor-pointer group" onClick={() => router.post('/gym/workouts')}>
                                        <div className="size-16 rounded-full bg-[#23482f] group-hover:bg-primary group-hover:text-[#102216] text-[#92c9a4] flex items-center justify-center transition-all shadow-lg group-hover:scale-110">
                                            <span className="material-symbols-outlined text-3xl font-bold">play_arrow</span>
                                        </div>
                                        <div>
                                            <h3 className="text-xl font-black text-white">Start a New Workout</h3>
                                            <p className="text-[#92c9a4] font-medium">No active session found. Ready to train?</p>
                                        </div>
                                    </div>
                                )
                            )}

                            {activeWorkout && activeWorkout.exercises.length === 0 && (
                                <div className="bg-[#193322]/50 rounded-2xl border-2 border-dashed border-[#23482f] p-10 flex flex-col items-center justify-center text-center gap-4">
                                    <div className="size-16 rounded-full bg-[#23482f] text-[#92c9a4] flex items-center justify-center">
                                        <span className="material-symbols-outlined text-3xl font-bold">fitness_center</span>
                                    </div>
                                    <div>
                                        <h3 className="text-xl font-black text-white">Empty Workout</h3>
                                        <p className="text-[#92c9a4] font-medium">Add some exercises from the library to get started!</p>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Footer Summary Widget */}
                        <div className="mt-12 pt-8 border-t border-[#23482f] flex flex-col lg:flex-row justify-between items-center gap-6 pb-12">
                            <p className="text-[#92c9a4] text-xs font-bold uppercase tracking-widest">© 2026 FitTrack Pro. All progress saved to profile.</p>
                            <div className="flex gap-10">
                                <div className="flex flex-col items-end">
                                    <span className="uppercase text-[10px] tracking-[0.2em] font-black text-primary mb-1">Total Volume</span>
                                    <span className="text-3xl font-black text-white leading-none">
                                        {activeWorkout?.exercises.reduce((acc, ex) => acc + ex.sets.reduce((sAcc, s) => sAcc + (parseFloat(s.weight) * parseInt(s.reps) || 0), 0), 0).toLocaleString()}
                                        <span className="text-sm font-medium text-[#92c9a4]">kg</span>
                                    </span>
                                </div>
                                <div className="flex flex-col items-end">
                                    <span className="uppercase text-[10px] tracking-[0.2em] font-black text-primary mb-1">Completed Sets</span>
                                    <span className="text-3xl font-black text-white leading-none">
                                        {activeWorkout?.exercises.reduce((acc, ex) => acc + ex.sets.filter(s => s.completed).length, 0)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right Sidebar (Quick Library) - Desktop Only */}
                    <aside className="hidden xl:flex flex-col w-80 bg-[#112217] border-l border-[#23482f] h-full animate-in slide-in-from-right-8 duration-700">
                        <div className="p-6 border-b border-[#23482f]">
                            <h3 className="font-black text-white mb-1 uppercase tracking-widest text-sm">Quick Library</h3>
                            <p className="text-xs font-medium text-[#92c9a4]">Tap to add to your session</p>
                        </div>

                        {/* Categories */}
                        <div className="p-4 flex gap-2 overflow-x-auto no-scrollbar border-b border-[#23482f]/50">
                            {['All', 'Chest', 'Back', 'Legs', 'Arms'].map((cat) => (
                                <button key={cat} className={`px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider whitespace-nowrap transition-all ${cat === 'All' ? 'bg-primary text-[#102216]' : 'bg-[#23482f] text-[#92c9a4] hover:text-white'}`}>
                                    {cat}
                                </button>
                            ))}
                        </div>

                        <div className="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                            {libraryExercises.filter(ex => ex.name.toLowerCase().includes(searchQuery.toLowerCase())).map((libEx) => (
                                <div
                                    key={libEx.id}
                                    onClick={() => handleAddExercise(libEx.id)}
                                    className="flex items-center gap-3 p-3 rounded-xl bg-white/5 hover:bg-white/10 cursor-pointer border border-transparent hover:border-[#23482f] transition-all group"
                                >
                                    <div className="size-11 rounded-lg bg-[#102216] flex items-center justify-center shrink-0 border border-[#23482f]">
                                        <span className="material-symbols-outlined text-[#92c9a4] group-hover:text-primary transition-colors">fitness_center</span>
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <h4 className="text-sm font-bold text-white truncate">{libEx.name}</h4>
                                        <p className="text-[10px] font-black uppercase tracking-tight text-[#92c9a4] truncate">{libEx.muscle_group} • {libEx.type}</p>
                                    </div>
                                    <button className="text-primary opacity-30 group-hover:opacity-100 transition-opacity">
                                        <span className="material-symbols-outlined text-2xl font-bold">add_circle</span>
                                    </button>
                                </div>
                            ))}

                            {/* Go Premium Promo */}
                            <div className="mt-8 p-6 rounded-2xl bg-gradient-to-br from-[#23482f] to-[#193322] border border-[#23482f] text-center relative overflow-hidden group">
                                <div className="absolute top-0 right-0 p-4 opacity-10 group-hover:scale-150 transition-transform duration-700">
                                    <span className="material-symbols-outlined text-6xl">bolt</span>
                                </div>
                                <div className="size-14 rounded-2xl bg-primary/20 text-primary mx-auto flex items-center justify-center mb-4 border border-primary/20">
                                    <span className="material-symbols-outlined text-2xl font-black">bolt</span>
                                </div>
                                <h5 className="font-black text-white mb-2 uppercase tracking-widest text-sm">Go Premium</h5>
                                <p className="text-xs font-medium text-[#92c9a4] mb-5 leading-relaxed">Unlock advanced analytics, AI coaching & custom plans.</p>
                                <button className="w-full py-3 rounded-xl bg-[#102216] text-white text-[10px] font-black uppercase tracking-[0.2em] border border-[#23482f] hover:bg-black transition-all active:scale-95 shadow-lg">
                                    Upgrade Now
                                </button>
                            </div>
                        </div>
                    </aside>
                </div>

                {/* Floating Mobile Finish Button */}
                <div className="lg:hidden fixed bottom-8 right-6 z-30">
                    <button
                        onClick={handleFinishWorkout}
                        disabled={!activeWorkout}
                        className="bg-primary hover:bg-primary-hover text-[#102216] size-16 rounded-full shadow-[0_8px_30px_rgba(19,236,91,0.4)] flex items-center justify-center transition-all active:scale-90 animate-bounce disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span className="material-symbols-outlined text-3xl font-black">check</span>
                    </button>
                </div>
            </div>

            <style dangerouslySetInnerHTML={{
                __html: `
                .custom-scrollbar::-webkit-scrollbar {
                    width: 4px;
                }
                .custom-scrollbar::-webkit-scrollbar-track {
                    background: transparent;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb {
                    background: #23482f;
                    border-radius: 10px;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                    background: #13ec5b;
                }
                .no-scrollbar::-webkit-scrollbar {
                    display: none;
                }
                .no-scrollbar {
                    -ms-overflow-style: none;
                    scrollbar-width: none;
                }
            `}} />
        </GymLayout>
    );
}
