import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState, useMemo } from 'react';
import { ModuleAiButton } from '@/components/ai/module-ai-button';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import GymLayout from '@/layouts/gym-layout';

interface Set {
    id: number;
    set_number: number;
    weight: string;
    reps: string;
    rpe: string;
    completed: boolean;
    is_pr?: boolean;
}

interface WorkoutExercise {
    id: number;
    exercise_id: number;
    exercise: {
        id: number;
        name: string;
        muscle_group: string;
        type: string;
        video_url?: string | null;
    };
    sets: Set[];
    previous: Set[] | null;
    best_weight?: number | null;
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

interface WeeklyDay {
    date: string;
    label: string;
    volume: number;
}

interface StreakData {
    current: number;
    days: { date: string; label: string; hasWorkout: boolean }[];
}

interface RoutineOption {
    id: number;
    name: string;
    focus: string;
    scheduled_date: string | null;
    exercises: {
        name: string;
        pivot?: {
            target_sets?: number;
            target_reps?: string;
            target_weight?: string;
        };
    }[];
}

interface ProgressionRow {
    workout_id: number;
    date: string;
    best_weight: number;
    best_1rm: number;
    volume: number;
    total_reps: number;
    completed_sets: number;
}

interface Props {
    exercises: any[];
    routines: RoutineOption[];
    activeWorkout: Workout | null;
    suggestedRoutine: any | null;
    weeklyVolumeByDay?: WeeklyDay[];
    weeklyVolumes?: number[];
    weeklyVolumeTotal?: number;
    streak?: StreakData;
}

function getPrBadge(previous: Set[] | null): number | null {
    if (!previous || previous.length === 0) return null;
    let best = 0;
    for (const s of previous) {
        const w = parseFloat(String(s.weight));
        if (!isNaN(w) && w > best) best = w;
    }
    return best > 0 ? best : null;
}

function formatShortDate(iso: string): string {
    return `${iso.slice(8, 10)}/${iso.slice(5, 7)}`;
}

function ProgressionSparkline({ rows }: { rows: ProgressionRow[] }) {
    const width = 320;
    const height = 96;
    const padX = 8;
    const padY = 14;
    const innerW = width - padX * 2;
    const innerH = height - padY * 2;

    const weights = rows.map((r) => r.best_weight);
    const maxW = Math.max(...weights);
    const minW = Math.min(...weights);
    const span = maxW - minW;
    const stepX = rows.length === 1 ? innerW / 2 : innerW / (rows.length - 1);

    const points = rows.map((r, i) => ({
        x: padX + i * stepX,
        y: span === 0 ? padY + innerH / 2 : padY + innerH - ((r.best_weight - minW) / span) * innerH,
    }));

    const polyline = points.map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ');
    const area = `${padX},${height - padY} ${polyline} ${width - padX},${height - padY}`;
    const labelStep = Math.max(1, Math.ceil(rows.length / 5));

    return (
        <svg viewBox={`0 0 ${width} ${height}`} className="h-auto w-full" role="img" aria-label="Progresión de peso máximo por sesión">
            <polygon points={area} fill="#EF4444" fillOpacity="0.08" />
            <polyline points={polyline} fill="none" stroke="#EF4444" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
            {points.map((p, i) => (
                <circle key={`${rows[i].workout_id}-${i}`} cx={p.x} cy={p.y} r="3" fill="#EF4444" />
            ))}
            {rows.map((r, i) =>
                i % labelStep === 0 ? (
                    <text key={`${r.workout_id}-label`} x={Math.min(Math.max(points[i].x, 24), width - 24)} y={height - 4} textAnchor="middle" fontSize="8" fill="#E8B4B4" fontFamily="ui-monospace, SFMono-Regular, monospace">
                        {formatShortDate(r.date)}
                    </text>
                ) : null,
            )}
        </svg>
    );
}

export default function GymRoutine({ exercises: libraryExercises, routines, activeWorkout: initialActiveWorkout, suggestedRoutine, weeklyVolumeByDay, weeklyVolumes, streak }: Props) {
    const [activeWorkout, setActiveWorkout] = useState<Workout | null>(initialActiveWorkout);
    const [searchQuery, setSearchQuery] = useState('');
    const [activeFilter, setActiveFilter] = useState('All');
    const [showNewExercise, setShowNewExercise] = useState(false);
    const [time, setTime] = useState('00:00:00');
    const { props: pageProps } = usePage();
    const [routinePickerOpen, setRoutinePickerOpen] = useState(false);
    const [conflictWorkout, setConflictWorkout] = useState<Workout | null>(null);
    const [pendingRoutineId, setPendingRoutineId] = useState<number | null>(null);
    const [conflictError, setConflictError] = useState('');
    const [expandedRoutineId, setExpandedRoutineId] = useState<number | null>(null);
    const [progressionFor, setProgressionFor] = useState<{
        exerciseId: number;
        exerciseName: string;
        rows: ProgressionRow[];
        loading: boolean;
        error: string;
    } | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        muscle_group: 'Chest',
        type: 'Compound',
    });

    useEffect(() => {
        setActiveWorkout(initialActiveWorkout);
    }, [initialActiveWorkout]);

    // Timer logic — NO TOCAR
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

    const categories = useMemo(() => {
        const base = ['Chest', 'Back', 'Legs', 'Arms', 'Shoulders'];
        const dynamic = Array.from(new Set(libraryExercises.map((e: any) => e.muscle_group).filter(Boolean) as string[]));
        const merged = Array.from(new Set([...base, ...dynamic]));
        return ['All', ...merged];
    }, [libraryExercises]);

    const filteredExercises = useMemo(() => {
        const q = searchQuery.toLowerCase().trim();
        return libraryExercises.filter(
            (ex: any) =>
                (activeFilter === 'All' || ex.muscle_group === activeFilter) &&
                (ex.name.toLowerCase().includes(q) || ex.muscle_group.toLowerCase().includes(q))
        );
    }, [libraryExercises, activeFilter, searchQuery]);

    const handleCreateExercise = (e: React.FormEvent) => {
        e.preventDefault();
        post('/gym/exercises', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setShowNewExercise(false);
            },
        });
    };

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

    const handleRemoveExercise = (workoutExerciseId: number) => {
        if (confirm('Are you sure you want to delete this exercise?')) {
            router.delete(`/gym/workout-exercises/${workoutExerciseId}`, {
                preserveScroll: true,
                onSuccess: (page) => {
                    const updatedWorkout = (page.props as any).activeWorkout;
                    if (updatedWorkout) setActiveWorkout(updatedWorkout);
                }
            });
        }
    };

    const handleRemoveSet = (setId: number) => {
        if (confirm('Are you sure you want to delete this set?')) {
            router.delete(`/gym/workout-sets/${setId}`, {
                preserveScroll: true,
                onSuccess: (page) => {
                    const updatedWorkout = (page.props as any).activeWorkout;
                    if (updatedWorkout) setActiveWorkout(updatedWorkout);
                }
            });
        }
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

    const startRoutine = (routineId: number) => {
        router.post('/gym/workouts', { routine_id: routineId }, {
            onSuccess: () => {
                setRoutinePickerOpen(false);
                setConflictError('');
                router.visit('/fitness/gym', { preserveScroll: false });
            },
            onError: (visitErrors) => {
                const conflict = Boolean(
                    visitErrors?.workout ||
                    (pageProps.errors as Record<string, unknown> | undefined)?.workout,
                );

                if (conflict) {
                    setPendingRoutineId(routineId);
                    void resolveActiveWorkout();
                } else {
                    setPendingRoutineId(null);
                    setConflictError('');
                }
            },
        });
    };

    const resolveActiveWorkout = async () => {
        setConflictError('');

        try {
            const res = await fetch('/gym/workouts', {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) {
                throw new Error('Request failed');
            }

            const workouts = (await res.json()) as Record<string, unknown>[];
            const active = Array.isArray(workouts)
                ? workouts.find((w) => w.ended_at === null)
                : undefined;

            if (active) {
                setConflictWorkout(active as unknown as Workout);
                return;
            }

            // Hay conflicto pero no se localizó la sesión activa: feedback con reintento.
            setConflictWorkout(null);
            setConflictError(
                typeof pageProps.errors?.workout === 'string' && pageProps.errors.workout
                    ? pageProps.errors.workout
                    : 'Ya tenés un entrenamiento activo, pero no se pudo cargar la sesión. Reintentá.',
            );
        } catch {
            setConflictWorkout(null);
            setConflictError(
                typeof pageProps.errors?.workout === 'string' && pageProps.errors.workout
                    ? pageProps.errors.workout
                    : 'No se pudo consultar tu sesión activa. Reintentá.',
            );
        }
    };

    const handleContinueActive = () => {
        setConflictWorkout(null);
        setPendingRoutineId(null);
        setConflictError('');
        router.visit('/fitness/gym');
    };

    const handleFinishAndStartRoutine = () => {
        if (!conflictWorkout) return;

        const workoutId = conflictWorkout.id;
        const routineId = pendingRoutineId;

        router.patch(`/gym/workouts/${workoutId}`, {
            ended_at: new Date().toISOString(),
        }, {
            onSuccess: () => {
                if (routineId === null) {
                    setConflictWorkout(null);
                    return;
                }

                router.post('/gym/workouts', { routine_id: routineId }, {
                    onSuccess: () => {
                        setConflictWorkout(null);
                        setPendingRoutineId(null);
                        setRoutinePickerOpen(false);
                        router.visit('/fitness/gym');
                    },
                    onError: () => {
                        setConflictWorkout(null);
                        setPendingRoutineId(null);
                        setConflictError('');
                    },
                });
            },
        });
    };

    const loadProgression = async (exerciseId: number, exerciseName: string) => {
        setProgressionFor({ exerciseId, exerciseName, rows: [], loading: true, error: '' });

        try {
            const res = await fetch(`/gym/exercises/${exerciseId}/progression`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) {
                throw new Error('Progression request failed');
            }

            const rows = (await res.json()) as ProgressionRow[];
            setProgressionFor((prev) =>
                prev && prev.exerciseId === exerciseId
                    ? { ...prev, rows, loading: false }
                    : prev,
            );
        } catch {
            setProgressionFor((prev) =>
                prev && prev.exerciseId === exerciseId
                    ? { ...prev, loading: false, error: 'No se pudo cargar la progresión del ejercicio.' }
                    : prev,
            );
        }
    };

    const openProgression = (exerciseId: number, exerciseName: string) => {
        void loadProgression(exerciseId, exerciseName);
    };

    const retryProgression = () => {
        if (!progressionFor) return;
        void loadProgression(progressionFor.exerciseId, progressionFor.exerciseName);
    };

    const currentRoutineName = activeWorkout?.routine?.name || 'Quick Session';
    const currentFocus = activeWorkout?.routine?.focus || 'Custom Training';
    const today = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });

    return (
        <GymLayout>
            <Head title="Today's Session - Gym Tracker" />

            <div className="flex h-full flex-col overflow-hidden relative">
                {/* Top Action Bar */}
                <header className="hidden lg:flex items-center justify-between px-8 py-5 border-b border-[#3e2121] bg-[#1c0f0f] sticky top-0 z-20">
                    <div className="flex flex-col">
                        <h2 className="text-2xl font-bold">Today's Session</h2>
                        <p className="text-[#e8b4b4] text-sm">Keep pushing, you're doing great.</p>
                    </div>
                    <div className="flex items-center gap-4">
                        <ModuleAiButton module="gym" />
                        <div className="flex items-center gap-2 text-[#e8b4b4] bg-[#3e2121]/50 px-3 py-1.5 rounded-lg border border-[#3e2121]">
                            <span className="material-symbols-outlined text-lg">timer</span>
                            <span className="text-sm font-mono font-medium">{time}</span>
                        </div>
                        <Button
                            variant="outline"
                            onClick={() => setRoutinePickerOpen(true)}
                            className="border-[#3e2121] bg-[#2b1a1a] text-[#e8b4b4] hover:bg-[#3e2121] hover:text-white"
                        >
                            <span className="material-symbols-outlined text-lg">edit_calendar</span>
                            Rutina
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => router.get('/fitness/history')}
                            className="border-[#3e2121] bg-[#2b1a1a] text-[#e8b4b4] hover:bg-[#3e2121] hover:text-white"
                        >
                            <span className="material-symbols-outlined text-lg">history</span>
                            Historial
                        </Button>
                        <button
                            onClick={handleFinishWorkout}
                            disabled={!activeWorkout}
                            className={`bg-primary hover:bg-primary-hover text-white px-6 py-2.5 rounded-lg font-bold flex items-center gap-2 transition-all shadow-[0_0_15px_rgba(239,68,68,0.3)] hover:scale-105 active:scale-95 ${!activeWorkout ? 'opacity-50 cursor-not-allowed' : ''}`}
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
                                        className="text-[#e8b4b4] hover:text-primary transition-colors flex items-center gap-1 group"
                                        title="Set Routine"
                                    >
                                        <span className="material-symbols-outlined">edit_calendar</span>
                                        <span className="text-[10px] uppercase font-black tracking-widest opacity-0 group-hover:opacity-100 transition-opacity">Set Routine</span>
                                    </button>
                                </div>
                                <p className="text-[#e8b4b4] text-lg font-medium">Focus: {currentFocus}</p>
                            </div>
                            <div className="flex gap-3">
                                <div className="relative group w-full md:w-96">
                                    <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-[#e8b4b4]">
                                        <span className="material-symbols-outlined">search</span>
                                    </div>
                                    <input
                                        className="block w-full p-3 pl-10 text-sm bg-[#3e2121] border-2 border-transparent rounded-xl placeholder-[#e8b4b4] text-white focus:ring-0 focus:border-primary transition-all outline-none"
                                        placeholder="Search exercises to add..."
                                        type="text"
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                    />
                                    <div className="absolute right-3 top-2.5 hidden group-focus-within:block bg-[#1c0f0f] border border-[#3e2121] rounded px-1.5 py-0.5 text-[10px] font-bold text-[#e8b4b4]">
                                        ⌘K
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Streaks: 7 dots FASE 4 */}
                        {streak && streak.days.length > 0 && (
                            <div className="flex flex-wrap items-center gap-3 rounded-xl bg-[#2b1a1a] border border-[#3e2121] px-4 py-3 max-w-5xl">
                                <span className="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-widest text-primary">
                                    <span className="material-symbols-outlined text-sm fill-1">local_fire_department</span>
                                    Racha {streak.current} días
                                </span>
                                <span className="h-4 w-px bg-[#3e2121] hidden sm:block" />
                                <div className="flex items-center gap-1.5">
                                    {streak.days.map((d) => (
                                        <div key={d.date} className="flex flex-col items-center gap-1">
                                            <div
                                                className={`h-7 w-7 rounded-full border flex items-center justify-center text-[9px] font-black transition ${d.hasWorkout ? 'bg-primary border-primary text-white shadow-[0_0_8px_rgba(239,68,68,0.35)]' : 'bg-[#1c0f0f] border-[#3e2121] text-[#e8b4b4]'}`}
                                                title={`${d.label} ${d.date}${d.hasWorkout ? ' · entrenado' : ''}`}
                                            >
                                                {d.hasWorkout ? <span className="material-symbols-outlined text-[14px] font-bold">check</span> : <span className="h-1.5 w-1.5 rounded-full bg-[#3e2121]" />}
                                            </div>
                                            <span className={`text-[9px] font-black uppercase ${d.hasWorkout ? 'text-primary' : 'text-[#e8b4b4]'}`}>{d.label.slice(0, 2)}</span>
                                        </div>
                                    ))}
                                </div>
                                <span className="text-[10px] font-bold text-[#e8b4b4] ml-auto hidden sm:inline">Últimos 7 días · dot rojo = día con workout</span>
                            </div>
                        )}

                        {/* Exercise List */}
                        <div className="space-y-6 max-w-5xl animate-in fade-in slide-in-from-bottom-8 duration-700 delay-200">
                            {activeWorkout?.exercises.map((workoutExercise) => {
                                const pr = workoutExercise.best_weight ?? getPrBadge(workoutExercise.previous);
                                return (
                                <div key={workoutExercise.id} className="bg-[#2b1a1a] rounded-2xl overflow-hidden border border-[#3e2121] shadow-lg group hover:border-[#3e2121] transition-all">
                                    {/* Card Header */}
                                    <div className="p-4 border-b border-[#3e2121] flex flex-col sm:flex-row sm:items-center justify-between bg-white/5 gap-4">
                                        <div className="flex items-center gap-4">
                                            <div className="size-12 rounded-xl bg-[#3e2121] flex items-center justify-center text-primary border border-[#3e2121]">
                                                <span className="material-symbols-outlined">fitness_center</span>
                                            </div>
                                            <div>
                                                <h3 className="text-lg font-bold text-white flex flex-wrap items-center gap-2">
                                                    {workoutExercise.exercise.name}
                                                    <button
                                                        type="button"
                                                        onClick={() => openProgression(workoutExercise.exercise.id, workoutExercise.exercise.name)}
                                                        title="Ver progresión"
                                                        className="material-symbols-outlined text-[#e8b4b4] text-sm hover:text-primary transition-colors"
                                                    >
                                                        history
                                                    </button>
                                                    {pr !== null && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-primary/15 border border-primary/30 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-widest text-primary" title={`PR histórico: ${pr}kg`}>
                                                            <span className="material-symbols-outlined text-xs">emoji_events</span>
                                                            PR {pr}kg
                                                        </span>
                                                    )}
                                                </h3>
                                                <div className="flex items-center gap-3 text-sm">
                                                    <span className="text-[#e8b4b4] font-medium">{workoutExercise.exercise.muscle_group} • {workoutExercise.exercise.type}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => workoutExercise.exercise.video_url && window.open(workoutExercise.exercise.video_url, '_blank', 'noopener')}
                                                disabled={!workoutExercise.exercise.video_url}
                                                title={workoutExercise.exercise.video_url ? 'Ver video' : 'Sin video'}
                                                className="p-2 text-[#e8b4b4] hover:text-white hover:bg-white/5 rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                            >
                                                <span className="material-symbols-outlined">videocam</span>
                                            </button>
                                            <button
                                                onClick={() => handleRemoveExercise(workoutExercise.id)}
                                                title="Eliminar ejercicio"
                                                className="p-2 text-[#e8b4b4] hover:text-red-400 hover:bg-red-400/10 rounded-lg transition-colors"
                                            >
                                                <span className="material-symbols-outlined">delete</span>
                                            </button>
                                        </div>
                                    </div>

                                    {/* Card Body (Sets) */}
                                    <div className="p-4">
                                        <div className="grid grid-cols-[30px_1fr_1fr_1fr_1fr_40px_40px] gap-4 mb-2 text-[10px] uppercase tracking-widest font-black text-[#e8b4b4] px-2">
                                            <div className="text-center">Set</div>
                                            <div>Previous</div>
                                            <div>kg</div>
                                            <div>Reps</div>
                                            <div>RPE</div>
                                            <div className="text-center"><span className="material-symbols-outlined text-sm">check</span></div>
                                            <div className="text-center"><span className="material-symbols-outlined text-sm">delete</span></div>
                                        </div>

                                        {/* Set Rows */}
                                        <div className="space-y-2">
                                            {workoutExercise.sets.map((set, setIndex) => (
                                                <div
                                                    key={set.id}
                                                    className={`grid grid-cols-[30px_1fr_1fr_1fr_1fr_40px_40px] gap-4 items-center rounded-xl p-2 border transition-all ${set.completed
                                                        ? 'bg-primary/5 border-primary/20'
                                                        : 'border-transparent hover:bg-white/5'
                                                        }`}
                                                >
                                                    <div className={`flex flex-col items-center font-black ${set.completed ? 'text-primary' : 'text-white'}`}>
                                                        <span>{set.set_number}</span>
                                                        {set.is_pr && (
                                                            <span className="material-symbols-outlined text-[12px] text-primary" title="Nuevo PR">emoji_events</span>
                                                        )}
                                                    </div>
                                                    <div className="text-[#e8b4b4] text-[10px] font-bold">
                                                        {workoutExercise.previous?.find(ps => ps.set_number === set.set_number)
                                                            ? `${workoutExercise.previous.find(ps => ps.set_number === set.set_number)?.weight}kg x ${workoutExercise.previous.find(ps => ps.set_number === set.set_number)?.reps}`
                                                            : '-'}
                                                    </div>
                                                    <input
                                                        className="bg-[#3e2121] border-none rounded-lg text-white text-center font-bold focus:ring-2 focus:ring-primary py-1.5 h-9 w-full"
                                                        placeholder="-"
                                                        type="text"
                                                        defaultValue={set.weight || ''}
                                                        onBlur={(e) => handleLogSet(workoutExercise.id, set.set_number, { weight: e.target.value })}
                                                    />
                                                    <input
                                                        className="bg-[#3e2121] border-none rounded-lg text-white text-center font-bold focus:ring-2 focus:ring-primary py-1.5 h-9 w-full"
                                                        placeholder="-"
                                                        type="text"
                                                        defaultValue={set.reps || ''}
                                                        onBlur={(e) => handleLogSet(workoutExercise.id, set.set_number, { reps: e.target.value })}
                                                    />
                                                    <input
                                                        className="bg-[#3e2121] border-none rounded-lg text-white text-center font-bold focus:ring-2 focus:ring-primary py-1.5 h-9 w-full"
                                                        placeholder="-"
                                                        type="text"
                                                        defaultValue={set.rpe || ''}
                                                        onBlur={(e) => handleLogSet(workoutExercise.id, set.set_number, { rpe: e.target.value })}
                                                    />
                                                    <button
                                                        onClick={() => handleLogSet(workoutExercise.id, set.set_number, { completed: !set.completed })}
                                                        className={`flex items-center justify-center h-9 w-full rounded-lg transition-all ${set.completed
                                                            ? 'bg-primary text-white'
                                                            : 'bg-[#3e2121] text-[#e8b4b4] hover:bg-primary hover:text-white'
                                                            }`}
                                                    >
                                                        <span className="material-symbols-outlined text-lg font-black">check</span>
                                                    </button>
                                                    <button
                                                        onClick={() => handleRemoveSet(set.id)}
                                                        title="Eliminar serie"
                                                        className="flex items-center justify-center h-9 w-full rounded-lg bg-[#3e2121] text-[#e8b4b4] hover:bg-red-400/10 hover:text-red-400 transition-all"
                                                    >
                                                        <span className="material-symbols-outlined text-lg">delete</span>
                                                    </button>
                                                </div>
                                            ))}
                                        </div>

                                        <button
                                            onClick={() => handleLogSet(workoutExercise.id, (workoutExercise.sets.length || 0) + 1, {})}
                                            className="w-full mt-4 py-3 border border-dashed border-[#3e2121] rounded-xl text-[#e8b4b4] hover:text-white hover:border-primary hover:bg-primary/5 transition-all flex items-center justify-center gap-2 text-xs font-black uppercase tracking-widest"
                                        >
                                            <span className="material-symbols-outlined text-lg">add</span> Add Set
                                        </button>
                                    </div>
                                </div>
                                );
                            })}

                            {!activeWorkout && (
                                suggestedRoutine ? (
                                    <div className="bg-[#2b1a1a]/50 rounded-2xl border-2 border-dashed border-[#3e2121] p-10 flex flex-col items-center justify-center text-center gap-4 hover:border-primary/50 transition-all cursor-pointer group"
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
                                        <div className="size-16 rounded-full bg-[#3e2121] group-hover:bg-primary group-hover:text-white text-[#e8b4b4] flex items-center justify-center transition-all shadow-lg group-hover:scale-110">
                                            <span className="material-symbols-outlined text-3xl font-bold">play_arrow</span>
                                        </div>
                                        <div>
                                            <h3 className="text-xl font-black text-white">Start {suggestedRoutine.name}</h3>
                                            <p className="text-[#e8b4b4] font-medium">Scheduled for today. Let's get to work!</p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="grid sm:grid-cols-2 gap-4">
                                        <div
                                            onClick={() => setRoutinePickerOpen(true)}
                                            className="bg-[#2b1a1a] rounded-2xl border-2 border-primary/40 p-10 flex flex-col items-center justify-center text-center gap-4 hover:border-primary transition-all cursor-pointer group shadow-[0_0_20px_rgba(239,68,68,0.12)]"
                                        >
                                            <div className="size-16 rounded-full bg-primary/15 group-hover:bg-primary group-hover:text-white text-primary flex items-center justify-center transition-all shadow-lg group-hover:scale-110">
                                                <span className="material-symbols-outlined text-3xl font-bold">edit_calendar</span>
                                            </div>
                                            <div>
                                                <h3 className="text-xl font-black text-white">Elegir rutina</h3>
                                                <p className="text-[#e8b4b4] font-medium">Empezá una sesión con tu rutina programada.</p>
                                            </div>
                                        </div>
                                        <div
                                            onClick={() => router.post('/gym/workouts')}
                                            className="bg-[#2b1a1a]/50 rounded-2xl border-2 border-dashed border-[#3e2121] p-10 flex flex-col items-center justify-center text-center gap-4 hover:border-primary/50 transition-all cursor-pointer group"
                                        >
                                            <div className="size-16 rounded-full bg-[#3e2121] group-hover:bg-primary group-hover:text-white text-[#e8b4b4] flex items-center justify-center transition-all shadow-lg group-hover:scale-110">
                                                <span className="material-symbols-outlined text-3xl font-bold">play_arrow</span>
                                            </div>
                                            <div>
                                                <h3 className="text-xl font-black text-white">Sesión rápida</h3>
                                                <p className="text-[#e8b4b4] font-medium">Sin plan previo. Sumá ejercicios on the fly.</p>
                                            </div>
                                        </div>
                                    </div>
                                )
                            )}

                            {activeWorkout && activeWorkout.exercises.length === 0 && (
                                <div className="bg-[#2b1a1a]/50 rounded-2xl border-2 border-dashed border-[#3e2121] p-10 flex flex-col items-center justify-center text-center gap-4">
                                    <div className="size-16 rounded-full bg-[#3e2121] text-[#e8b4b4] flex items-center justify-center">
                                        <span className="material-symbols-outlined text-3xl font-bold">fitness_center</span>
                                    </div>
                                    <div>
                                        <h3 className="text-xl font-black text-white">Empty Workout</h3>
                                        <p className="text-[#e8b4b4] font-medium">Add some exercises from the library to get started!</p>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Footer Summary Widget */}
                        <div className="mt-12 pt-8 border-t border-[#3e2121] flex flex-col lg:flex-row justify-between items-center gap-6 pb-12">
                            <p className="text-[#e8b4b4] text-xs font-bold uppercase tracking-widest">© 2026 Megalomaniac Pro. All progress saved to profile.</p>
                            <div className="flex gap-10">
                                <div className="flex flex-col items-end">
                                    <span className="uppercase text-[10px] tracking-[0.2em] font-black text-primary mb-1">Total Volume</span>
                                    <span className="text-3xl font-black text-white leading-none">
                                        {activeWorkout?.exercises.reduce((acc, ex) => acc + ex.sets.reduce((sAcc, s) => sAcc + (parseFloat(s.weight) * parseInt(s.reps) || 0), 0), 0).toLocaleString()}
                                        <span className="text-sm font-medium text-[#e8b4b4]">kg</span>
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
                    <aside className="hidden xl:flex flex-col w-80 bg-[#1c0f0f] border-l border-[#3e2121] h-full animate-in slide-in-from-right-8 duration-700">
                        <div className="p-6 border-b border-[#3e2121]">
                            <div className="flex items-center justify-between mb-1">
                                <h3 className="font-black text-white uppercase tracking-widest text-sm">Quick Library</h3>
                                <button
                                    onClick={() => setShowNewExercise(true)}
                                    className="inline-flex items-center gap-1 rounded-full bg-primary px-3 py-1 text-[11px] font-black uppercase tracking-widest text-white hover:bg-primary/90 transition"
                                >
                                    <span className="material-symbols-outlined text-sm">add</span>
                                    Nuevo Ejercicio
                                </button>
                            </div>
                            <p className="text-xs font-medium text-[#e8b4b4]">Tap to add to your session</p>
                        </div>

                        {/* Categories */}
                        <div className="p-4 flex gap-2 overflow-x-auto no-scrollbar border-b border-[#3e2121]/50">
                            {categories.map((cat) => (
                                <button
                                    key={cat}
                                    onClick={() => setActiveFilter(cat)}
                                    className={`px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider whitespace-nowrap transition-all ${activeFilter === cat ? 'bg-primary text-white shadow' : 'bg-[#3e2121] text-[#e8b4b4] hover:text-white hover:bg-[#4a2a2a]'}`}
                                >
                                    {cat}
                                </button>
                            ))}
                        </div>

                        <div className="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                            {filteredExercises.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-10 text-center">
                                    <span className="material-symbols-outlined text-[#3e2121] text-4xl mb-2">search_off</span>
                                    <p className="text-sm font-bold text-[#e8b4b4]">Sin resultados</p>
                                    <p className="text-xs text-[#e8b4b4]/70 mt-1">Prueba otro filtro o búsqueda</p>
                                </div>
                            ) : (
                                filteredExercises.map((libEx: any) => (
                                <div
                                    key={libEx.id}
                                    onClick={() => handleAddExercise(libEx.id)}
                                    className="flex items-center gap-3 p-3 rounded-xl bg-white/5 hover:bg-white/10 cursor-pointer border border-transparent hover:border-[#3e2121] transition-all group"
                                >
                                    <div className="size-11 rounded-lg bg-[#1c0f0f] flex items-center justify-center shrink-0 border border-[#3e2121]">
                                        <span className="material-symbols-outlined text-[#e8b4b4] group-hover:text-primary transition-colors">fitness_center</span>
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <h4 className="text-sm font-bold text-white truncate">{libEx.name}</h4>
                                        <p className="text-[10px] font-black uppercase tracking-tight text-[#e8b4b4] truncate">{libEx.muscle_group} • {libEx.type}</p>
                                    </div>
                                    <button className="text-primary opacity-30 group-hover:opacity-100 transition-opacity">
                                        <span className="material-symbols-outlined text-2xl font-bold">add_circle</span>
                                    </button>
                                </div>
                                ))
                            )}
                        </div>
                    </aside>
                </div>

                {/* Floating Mobile Finish Button */}
                <div className="lg:hidden fixed bottom-8 right-6 z-30">
                    <button
                        onClick={handleFinishWorkout}
                        disabled={!activeWorkout}
                        className="bg-primary hover:bg-primary-hover text-white size-16 rounded-full shadow-[0_8px_30px_rgba(239,68,68,0.4)] flex items-center justify-center transition-all active:scale-90 animate-bounce disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span className="material-symbols-outlined text-3xl font-black">check</span>
                    </button>
                </div>
            </div>

            {/* Nuevo Ejercicio Dialog */}
            <Dialog open={showNewExercise} onOpenChange={setShowNewExercise}>
                <DialogContent className="bg-[#2b1a1a] border-[#3e2121] text-white sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-primary">add_circle</span>
                            Nuevo Ejercicio
                        </DialogTitle>
                        <DialogDescription className="text-[#e8b4b4]">
                            Crea un ejercicio personalizado para tu biblioteca. Se guardará vía POST /gym/exercises.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleCreateExercise} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="ex-name" className="text-[#e8b4b4]">Nombre *</Label>
                            <Input
                                id="ex-name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Ej: Press Banca Inclinado"
                                className="bg-[#1c0f0f] border-[#3e2121] text-white placeholder:text-[#e8b4b4]/60 focus-visible:ring-primary"
                                required
                            />
                            {errors.name && <p className="text-xs text-red-400">{errors.name}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label className="text-[#e8b4b4]">Grupo muscular</Label>
                                <Select value={data.muscle_group} onValueChange={(v) => setData('muscle_group', v)}>
                                    <SelectTrigger className="bg-[#1c0f0f] border-[#3e2121] text-white">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent className="bg-[#1c0f0f] border-[#3e2121] text-white">
                                        {['Chest','Back','Legs','Arms','Shoulders','Core','Full Body'].map((g) => (
                                            <SelectItem key={g} value={g} className="focus:bg-[#3e2121] focus:text-white">{g}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.muscle_group && <p className="text-xs text-red-400">{errors.muscle_group}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label className="text-[#e8b4b4]">Tipo</Label>
                                <Select value={data.type} onValueChange={(v) => setData('type', v)}>
                                    <SelectTrigger className="bg-[#1c0f0f] border-[#3e2121] text-white">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent className="bg-[#1c0f0f] border-[#3e2121] text-white">
                                        {['Compound','Isolation','Machine','Bodyweight','Cardio'].map((t) => (
                                            <SelectItem key={t} value={t} className="focus:bg-[#3e2121] focus:text-white">{t}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.type && <p className="text-xs text-red-400">{errors.type}</p>}
                            </div>
                        </div>
                        <div className="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" onClick={() => setShowNewExercise(false)} className="border-[#3e2121] bg-transparent text-[#e8b4b4] hover:bg-[#1c0f0f] hover:text-white">
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={processing} className="bg-primary hover:bg-primary/90 text-white font-black">
                                {processing ? 'Guardando...' : '+ Crear Ejercicio'}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Picker de rutina */}
            <Dialog
                open={routinePickerOpen}
                onOpenChange={(open) => {
                    setRoutinePickerOpen(open);
                    if (!open) setConflictError('');
                }}
            >
                <DialogContent className="bg-[#2b1a1a] border-[#3e2121] text-white sm:max-w-md max-h-[85vh] overflow-y-auto custom-scrollbar">
                    <DialogHeader>
                        <DialogTitle className="text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-primary">edit_calendar</span>
                            Elegir rutina
                        </DialogTitle>
                        <DialogDescription className="text-[#e8b4b4]">
                            Empezá la sesión de hoy con una rutina programada.
                        </DialogDescription>
                    </DialogHeader>
                    {conflictError && (
                        <div role="alert" className="flex items-start gap-2 rounded-xl border border-primary/30 bg-primary/10 px-3 py-2.5 text-xs font-bold text-white">
                            <span className="material-symbols-outlined text-primary shrink-0 text-base">error</span>
                            <div className="min-w-0 flex-1">
                                <p>{conflictError}</p>
                                <button
                                    type="button"
                                    onClick={() => void resolveActiveWorkout()}
                                    className="mt-1 inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-primary hover:underline"
                                >
                                    <span className="material-symbols-outlined text-xs">refresh</span>
                                    Reintentar
                                </button>
                            </div>
                        </div>
                    )}
                    <div className="space-y-3">
                        {routines.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-10 text-center">
                                <span className="material-symbols-outlined text-4xl text-[#3e2121] mb-2">library_books</span>
                                <p className="text-sm font-bold text-white">Todavía no tenés rutinas</p>
                                <p className="text-xs text-[#e8b4b4] mt-1 mb-4">Creá tu primera rutina para arrancar con un plan.</p>
                                <Link
                                    href="/fitness/routines"
                                    className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-xs font-black uppercase tracking-widest text-white hover:bg-primary/90 transition"
                                >
                                    <span className="material-symbols-outlined text-sm">add</span>
                                    Crear rutina
                                </Link>
                            </div>
                        ) : (
                            routines.map((routine) => {
                                const isExpanded = expandedRoutineId === routine.id;
                                return (
                                    <div
                                        key={routine.id}
                                        className={`rounded-xl border transition-all ${isExpanded ? 'border-primary/40 bg-white/5' : 'border-[#3e2121] bg-[#1c0f0f]/60'}`}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => setExpandedRoutineId(isExpanded ? null : routine.id)}
                                            className="w-full flex items-center justify-between gap-3 p-4 text-left hover:bg-white/5 rounded-t-xl transition-colors"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <h4 className="font-bold text-white truncate">{routine.name}</h4>
                                                    {routine.scheduled_date && (
                                                        <span className="shrink-0 rounded-full bg-primary/10 border border-primary/20 px-2 py-0.5 text-[9px] font-black uppercase tracking-widest text-primary">
                                                            {routine.scheduled_date.slice(0, 3)}
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="text-[10px] font-black uppercase tracking-tighter text-[#e8b4b4] truncate mt-1">
                                                    {routine.focus} · {routine.exercises.length}{' '}
                                                    {routine.exercises.length === 1 ? 'ejercicio' : 'ejercicios'}
                                                </p>
                                            </div>
                                            <span className={`material-symbols-outlined text-[#e8b4b4] shrink-0 transition-transform ${isExpanded ? 'rotate-180' : ''}`}>
                                                expand_more
                                            </span>
                                        </button>

                                        {isExpanded && (
                                            <div className="px-4 pb-3">
                                                <ul className="space-y-1.5 border-t border-[#3e2121] pt-3">
                                                    {routine.exercises.length === 0 ? (
                                                        <li className="text-xs text-[#e8b4b4]">Esta rutina no tiene ejercicios todavía.</li>
                                                    ) : (
                                                        routine.exercises.map((ex, idx) => (
                                                            <li key={`${routine.id}-${ex.name}-${idx}`} className="text-xs text-[#e8b4b4] flex items-center gap-2">
                                                                <span className="h-1 w-1 rounded-full bg-primary shrink-0" />
                                                                <span className="text-white font-medium">{ex.name}</span>
                                                                <span className="ml-auto font-mono tabular-nums">
                                                                    {ex.pivot?.target_sets ?? '-'} × {ex.pivot?.target_reps ?? '-'}
                                                                    {ex.pivot?.target_weight ? ` · ${ex.pivot.target_weight} kg` : ''}
                                                                </span>
                                                            </li>
                                                        ))
                                                    )}
                                                </ul>
                                                <div className="flex justify-end pt-3">
                                                    <Button
                                                        type="button"
                                                        onClick={() => startRoutine(routine.id)}
                                                        className="bg-primary hover:bg-primary/90 text-white font-black text-xs"
                                                    >
                                                        Empezar con esta rutina
                                                    </Button>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                );
                            })
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            {/* Dialog de conflicto: ya hay un workout activo */}
            <Dialog
                open={conflictWorkout !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setConflictWorkout(null);
                        setPendingRoutineId(null);
                        setConflictError('');
                    }
                }}
            >
                <DialogContent className="bg-[#2b1a1a] border-[#3e2121] text-white sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-primary">warning</span>
                            Ya tenés un entrenamiento activo
                        </DialogTitle>
                        <DialogDescription className="text-[#e8b4b4]">
                            {conflictWorkout?.routine?.name
                                ? `La sesión «${conflictWorkout.routine.name}» sigue abierta.`
                                : 'Tenés una sesión rápida en curso.'}{' '}
                            Terminá la sesión actual antes de empezar una nueva rutina.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="rounded-xl bg-[#1c0f0f] border border-[#3e2121] p-4 text-sm">
                        <p className="text-[#e8b4b4] text-xs font-bold uppercase tracking-widest mb-1">Sesión activa</p>
                        <p className="text-white font-bold">{conflictWorkout?.routine?.name ?? 'Sesión rápida'}</p>
                    </div>
                    <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleContinueActive}
                            className="border-[#3e2121] bg-transparent text-[#e8b4b4] hover:bg-[#1c0f0f] hover:text-white"
                        >
                            Continuar el activo
                        </Button>
                        <Button
                            type="button"
                            onClick={handleFinishAndStartRoutine}
                            className="bg-primary hover:bg-primary/90 text-white font-black"
                        >
                            Terminar y empezar la rutina
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            {/* Modal de progresión */}
            <Dialog
                open={progressionFor !== null}
                onOpenChange={(open) => {
                    if (!open) setProgressionFor(null);
                }}
            >
                <DialogContent className="bg-[#2b1a1a] border-[#3e2121] text-white sm:max-w-lg max-h-[85vh] overflow-y-auto custom-scrollbar">
                    <DialogHeader>
                        <DialogTitle className="text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-primary">monitoring</span>
                            {progressionFor?.exerciseName ?? 'Progresión del ejercicio'}
                        </DialogTitle>
                        <DialogDescription className="text-[#e8b4b4]">
                            Peso máximo por sesión · historial completo
                        </DialogDescription>
                    </DialogHeader>

                    {progressionFor === null ? null : progressionFor.loading ? (
                        <div className="space-y-3" aria-busy="true">
                            <div className="h-24 rounded-xl bg-[#1c0f0f] border border-[#3e2121] animate-pulse" />
                            <div className="h-8 rounded-lg bg-[#1c0f0f] border border-[#3e2121] animate-pulse" />
                            <div className="h-8 rounded-lg bg-[#1c0f0f] border border-[#3e2121] animate-pulse" />
                        </div>
                    ) : progressionFor.error ? (
                        <div className="flex flex-col items-center justify-center py-10 text-center gap-3">
                            <span className="material-symbols-outlined text-4xl text-red-400">cloud_off</span>
                            <p className="text-sm font-bold text-white">{progressionFor.error}</p>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={retryProgression}
                                className="border-[#3e2121] bg-transparent text-[#e8b4b4] hover:bg-[#1c0f0f] hover:text-white"
                            >
                                Intentar de nuevo
                            </Button>
                        </div>
                    ) : progressionFor.rows.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-14 text-center gap-3">
                            <span className="material-symbols-outlined text-5xl text-[#3e2121]">timeline</span>
                            <p className="text-sm font-bold text-white">Sin sesiones previas</p>
                            <p className="text-xs text-[#e8b4b4]">
                                Todavía no registraste sesiones completadas con este ejercicio.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-5">
                            <ProgressionSparkline rows={progressionFor.rows} />
                            <div className="rounded-xl border border-[#3e2121] overflow-hidden">
                                <div className="grid grid-cols-[1.2fr_1fr_1fr_1fr_1fr] gap-3 px-4 py-2 bg-[#1c0f0f] text-[9px] font-black uppercase tracking-widest text-[#e8b4b4]">
                                    <span>Fecha</span>
                                    <span className="text-right">Peso máx</span>
                                    <span className="text-right">1RM</span>
                                    <span className="text-right">Tonelaje</span>
                                    <span className="text-right">Sets compl.</span>
                                </div>
                                {progressionFor.rows.map((row) => (
                                    <div
                                        key={row.workout_id}
                                        className="grid grid-cols-[1.2fr_1fr_1fr_1fr_1fr] gap-3 px-4 py-2 border-t border-[#3e2121]/60 text-xs hover:bg-white/5 transition-colors"
                                    >
                                        <span className="text-[#e8b4b4] font-medium">{formatShortDate(row.date)}</span>
                                        <span className="text-white font-bold tabular-nums">{row.best_weight}kg</span>
                                        <span className="text-white tabular-nums">{row.best_1rm}kg</span>
                                        <span className="text-[#e8b4b4] tabular-nums">{row.volume.toLocaleString()}kg</span>
                                        <span className="text-[#e8b4b4] tabular-nums text-right">{row.completed_sets}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <style dangerouslySetInnerHTML={{
                __html: `
                .custom-scrollbar::-webkit-scrollbar {
                    width: 4px;
                }
                .custom-scrollbar::-webkit-scrollbar-track {
                    background: transparent;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb {
                    background: #3e2121;
                    border-radius: 10px;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                    background: #ef4444;
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
