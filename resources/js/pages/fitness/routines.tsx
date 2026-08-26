import { Head, Link, router, usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';
import GymLayout from '@/layouts/gym-layout';
import type { SharedData } from '@/types';

interface AiRoutine {
    name: string;
    focus: string;
    exercises: {
        name: string;
        sets: number;
        reps: string;
        notes?: string;
    }[];
}

interface AiEnabled {
    ai_enabled?: boolean;
}

interface Routine {
    id: number;
    name: string;
    focus: string;
    scheduled_date: string;
    exercises: any[];
}

interface Props {
    routines: Routine[];
    exercises: any[];
}

export default function Routines({ routines, exercises }: Props) {
    const { auth } = usePage<SharedData>().props;
    const user = auth.user as unknown as AiEnabled;
    const aiEnabled = user?.ai_enabled ?? false;

    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);

    // AI Routine Builder state
    const [aiRoutine, setAiRoutine] = useState<AiRoutine | null>(null);
    const [loadingAiRoutine, setLoadingAiRoutine] = useState(false);
    const [aiRoutineError, setAiRoutineError] = useState('');

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        focus: '',
        scheduled_date: '',
        exercises: [] as any[]
    });

    const handleAddExerciseRow = () => {
        setData('exercises', [
            ...data.exercises,
            { name: '', target_sets: '', target_reps: '', target_weight: '', notes: '' }
        ]);
    };

    const handleRemoveExerciseRow = (index: number) => {
        const newExercises = [...data.exercises];
        newExercises.splice(index, 1);
        setData('exercises', newExercises);
    };

    const handleExerciseChange = (index: number, field: string, value: any) => {
        const newExercises = [...data.exercises];
        newExercises[index] = { ...newExercises[index], [field]: value };

        // Auto-fill details if existing exercise is selected
        if (field === 'name') {
            const existingExercise = exercises.find(e => e.name === value);
            if (existingExercise) {
                newExercises[index].id = existingExercise.id;
            } else {
                delete newExercises[index].id;
            }
        }

        setData('exercises', newExercises);
    };

    const [editingRoutineId, setEditingRoutineId] = useState<number | null>(null);

    const handleEditRoutine = (routine: any) => {
        setEditingRoutineId(routine.id);
        setData({
            name: routine.name,
            focus: routine.focus || '',
            scheduled_date: routine.scheduled_date || '',
            exercises: routine.exercises.map((e: any) => ({
                id: e.id,
                name: e.name,
                target_sets: e.pivot?.target_sets || '',
                target_reps: e.pivot?.target_reps || '',
                target_weight: e.pivot?.target_weight || '',
                notes: e.pivot?.notes || '',
                muscle_group: e.muscle_group,
                type: e.type,
            }))
        });
        setIsCreateModalOpen(true);
    };

    const handleDeleteRoutine = (routineId: number) => {
        if (confirm('Are you sure you want to delete this routine?')) {
            router.delete(`/gym/routines/${routineId}`);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingRoutineId) {
            router.put(`/gym/routines/${editingRoutineId}`, data, {
                onSuccess: () => {
                    setIsCreateModalOpen(false);
                    reset();
                    setEditingRoutineId(null);
                }
            });
        } else {
            post('/fitness/routines', {
                onSuccess: () => {
                    setIsCreateModalOpen(false);
                    reset();
                }
            });
        }
    };

    // ... (rest of the component)


    const handleStartRoutine = (routineId: number) => {
        router.post('/gym/workouts', {
            routine_id: routineId,
            started_at: new Date().toISOString()
        }, {
            onSuccess: () => {
                router.visit('/fitness/gym');
            }
        });
    };

    const fetchAiRoutine = async () => {
        setLoadingAiRoutine(true);
        setAiRoutineError('');
        try {
            const res = await fetch('/ai/generate-routine', {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.routine) {
                    setAiRoutine(data.routine);
                } else {
                    setAiRoutineError(data.message || 'Could not generate a routine.');
                }
            } else {
                setAiRoutineError('Failed to generate routine.');
            }
        } catch {
            setAiRoutineError('Network error. Please try again.');
        } finally {
            setLoadingAiRoutine(false);
        }
    };

    const handleUseAiRoutine = (routine: AiRoutine) => {
        setData({
            name: routine.name,
            focus: routine.focus || '',
            scheduled_date: '',
            exercises: routine.exercises.map((e) => ({
                name: e.name,
                target_sets: e.sets,
                target_reps: e.reps,
                target_weight: '',
                notes: e.notes || '',
            }))
        });
        setAiRoutine(null);
        setIsCreateModalOpen(true);
    };

    return (
        <GymLayout>
            <Head title="Workout Routines" />

            <div className="p-6 lg:p-10 max-w-7xl mx-auto h-full overflow-y-auto custom-scrollbar">
                <header className="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-6 animate-in fade-in slide-in-from-bottom-4 duration-700">
                    <div>
                        <h1 className="text-4xl font-black text-white tracking-tight leading-none mb-3">Your Routines</h1>
                        <p className="text-[#e8b4b4] font-medium uppercase text-xs tracking-[0.2em]">Build and Schedule Your Training</p>
                    </div>
                    <button
                        onClick={() => setIsCreateModalOpen(true)}
                        className="bg-primary hover:bg-primary-hover text-white px-8 py-3 rounded-xl font-black text-sm transition-all shadow-lg hover:scale-105 active:scale-95 flex items-center gap-2"
                    >
                        <span className="material-symbols-outlined font-black">add</span>
                        Create New Routine
                    </button>
                </header>

                {/* AI Routine Builder */}
                <div className="bg-[#2b1a1a] border border-[#3e2121] rounded-2xl p-6 mb-8">
                    <div className="flex items-center gap-3 mb-4">
                        <div className="h-10 w-10 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary">
                            <span className="material-symbols-outlined">auto_awesome</span>
                        </div>
                        <div>
                            <h3 className="text-lg font-black text-white">AI Routine Builder</h3>
                            <p className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Generate a routine based on your workout history</p>
                        </div>
                    </div>

                    {!aiEnabled ? (
                        <div className="bg-[#1c0f0f] rounded-xl border border-[#3e2121] p-4 text-center">
                            <span className="material-symbols-outlined text-[#3e2121] text-3xl mb-2 block">settings</span>
                            <p className="text-sm text-[#e8b4b4]">
                                AI not configured. Enable it in <a href="/settings/profile" className="text-primary underline">Settings</a>.
                            </p>
                        </div>
                    ) : (
                        <>
                            {!aiRoutine && !aiRoutineError && (
                                <button
                                    type="button"
                                    onClick={fetchAiRoutine}
                                    disabled={loadingAiRoutine}
                                    className="w-full bg-primary/10 hover:bg-primary/20 border border-primary/20 text-primary font-black text-sm py-3 rounded-xl transition-all flex items-center justify-center gap-2"
                                >
                                    {loadingAiRoutine ? (
                                        <>
                                            <span className="material-symbols-outlined animate-spin">progress_activity</span>
                                            Analyzing your workouts...
                                        </>
                                    ) : (
                                        <>
                                            <span className="material-symbols-outlined">fitness_center</span>
                                            Generate Routine
                                        </>
                                    )}
                                </button>
                            )}

                            {aiRoutineError && (
                                <div className="bg-red-400/10 border border-red-400/20 rounded-xl p-4 text-center">
                                    <p className="text-sm text-red-400">{aiRoutineError}</p>
                                    <button
                                        type="button"
                                        onClick={fetchAiRoutine}
                                        className="mt-2 text-xs font-bold text-primary hover:underline"
                                    >
                                        Try Again
                                    </button>
                                </div>
                            )}

                            {aiRoutine && (
                                <div className="bg-[#1c0f0f] rounded-xl border border-primary/20 p-5">
                                    <div className="flex items-center justify-between mb-3">
                                        <div>
                                            <h4 className="text-lg font-black text-white">{aiRoutine.name}</h4>
                                            <p className="text-xs text-[#e8b4b4] font-medium uppercase tracking-tighter">{aiRoutine.focus}</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setAiRoutine(null)}
                                            className="text-xs text-[#e8b4b4] hover:text-white"
                                        >
                                            Clear
                                        </button>
                                    </div>
                                    <div className="space-y-2 mb-4">
                                        {aiRoutine.exercises.map((ex, idx) => (
                                            <div key={idx} className="flex items-center gap-3 text-sm">
                                                <span className="text-primary font-black">{idx + 1}.</span>
                                                <span className="text-white font-bold flex-1">{ex.name}</span>
                                                <span className="text-[#e8b4b4]">{ex.sets} × {ex.reps}</span>
                                                {ex.notes && <span className="text-[#e8b4b4] text-xs">({ex.notes})</span>}
                                            </div>
                                        ))}
                                    </div>
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            onClick={() => handleUseAiRoutine(aiRoutine)}
                                            className="flex-1 bg-primary hover:bg-primary-hover text-white text-[10px] font-black uppercase tracking-widest py-2 rounded-lg border border-primary/20 transition-all"
                                        >
                                            Use This Routine
                                        </button>
                                        <button
                                            type="button"
                                            onClick={fetchAiRoutine}
                                            className="bg-white/5 hover:bg-white/10 text-white text-[10px] font-black uppercase tracking-widest py-2 px-4 rounded-lg border border-[#3e2121] transition-all"
                                        >
                                            Regenerate
                                        </button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-16">
                    {/* Weekly Schedule Overview */}
                    <div className="lg:col-span-2 space-y-4">
                        <h3 className="text-white font-bold text-xl mb-4 flex items-center gap-2">
                            <span className="material-symbols-outlined text-primary">calendar_month</span>
                            Weekly Schedule
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {days.map((day) => {
                                const routine = routines.find(r => r.scheduled_date === day);
                                return (
                                    <div key={day} className={`p-5 rounded-2xl border transition-all ${routine ? 'bg-[#2b1a1a] border-[#3e2121] shadow-md' : 'bg-[#1c0f0f] border-[#3e2121]/50 border-dashed'}`}>
                                        <div className="flex justify-between items-start mb-3">
                                            <span className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">{day}</span>
                                            {routine && (
                                                <span className="bg-primary/10 text-primary text-[9px] font-black px-2 py-0.5 rounded border border-primary/20 uppercase">Scheduled</span>
                                            )}
                                        </div>
                                        {routine ? (
                                            <div>
                                                <div className="flex justify-between items-start">
                                                    <h4 className="text-lg font-bold text-white mb-1">{routine.name}</h4>
                                                    <div className="flex gap-1">
                                                        <button
                                                            onClick={() => handleEditRoutine(routine)}
                                                            className="p-1 hover:text-primary transition-colors text-[#e8b4b4]"
                                                        >
                                                            <span className="material-symbols-outlined text-base">edit</span>
                                                        </button>
                                                        <button
                                                            onClick={() => handleDeleteRoutine(routine.id)}
                                                            className="p-1 hover:text-red-400 transition-colors text-[#e8b4b4]"
                                                        >
                                                            <span className="material-symbols-outlined text-base">delete</span>
                                                        </button>
                                                    </div>
                                                </div>
                                                <p className="text-xs text-[#e8b4b4] font-medium mb-4">{routine.focus}</p>
                                                <div className="flex gap-2">
                                                    <button
                                                        onClick={() => handleStartRoutine(routine.id)}
                                                        className="flex-1 bg-white/5 hover:bg-white/10 text-white text-[10px] font-black uppercase tracking-widest py-2 rounded-lg border border-[#3e2121] transition-all"
                                                    >
                                                        Start Today
                                                    </button>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="h-20 flex flex-col items-center justify-center text-center opacity-40">
                                                <span className="material-symbols-outlined text-2xl mb-1">event_busy</span>
                                                <p className="text-[10px] font-black uppercase tracking-widest">Rest Day</p>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* All Routines List */}
                    <div className="space-y-6">
                        <h3 className="text-white font-bold text-xl mb-4 flex items-center gap-2">
                            <span className="material-symbols-outlined text-primary">library_books</span>
                            Routine Library
                        </h3>
                        <div className="space-y-4">
                            {routines.map((routine) => (
                                <div key={routine.id} className="bg-[#2b1a1a] border border-[#3e2121] rounded-2xl p-5 hover:border-primary/50 transition-all group">
                                    <h4 className="font-bold text-white mb-1 flex items-center justify-between">
                                        {routine.name}
                                        <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button
                                                onClick={() => handleEditRoutine(routine)}
                                                className="p-1 hover:text-primary transition-colors text-[#e8b4b4]"
                                            >
                                                <span className="material-symbols-outlined text-lg">edit</span>
                                            </button>
                                            <button
                                                onClick={() => handleDeleteRoutine(routine.id)}
                                                className="p-1 hover:text-red-400 transition-colors text-[#e8b4b4]"
                                            >
                                                <span className="material-symbols-outlined text-lg">delete</span>
                                            </button>
                                        </div>
                                    </h4>
                                    <div className="flex items-center justify-between mb-4">
                                        <p className="text-xs text-[#e8b4b4] font-medium uppercase tracking-tighter">{routine.focus}</p>
                                        {routine.scheduled_date && (
                                            <span className="bg-primary/10 text-primary text-[9px] font-black px-2 py-0.5 rounded border border-primary/20 uppercase">
                                                {routine.scheduled_date.slice(0, 3)}
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex items-center justify-between text-[10px] font-black text-[#e8b4b4]">
                                        <span>{routine.exercises.length} Exercises</span>
                                        <button
                                            onClick={() => handleStartRoutine(routine.id)}
                                            className="text-primary hover:underline flex items-center gap-1 group/btn"
                                        >
                                            USE ROUTINE
                                            <span className="material-symbols-outlined text-sm group-hover/btn:translate-x-1 transition-transform">arrow_forward</span>
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Create Routine Modal */}
                {isCreateModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
                        <div className="bg-[#1c0f0f] border border-[#3e2121] rounded-3xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col shadow-2xl animate-in fade-in zoom-in-95 duration-300">
                            <div className="p-6 border-b border-[#3e2121] flex items-center justify-between bg-[#2b1a1a]">
                                <h3 className="text-2xl font-black text-white">
                                    {editingRoutineId ? 'Edit Routine' : 'Create New Routine'}
                                </h3>
                                <button
                                    onClick={() => {
                                        setIsCreateModalOpen(false);
                                        setEditingRoutineId(null);
                                        reset();
                                    }}
                                    className="text-[#e8b4b4] hover:text-white transition-colors"
                                >
                                    <span className="material-symbols-outlined">close</span>
                                </button>
                            </div>

                            <form onSubmit={submit} className="flex-1 overflow-y-auto p-6 md:p-8 custom-scrollbar">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                    <div className="space-y-2">
                                        <label className="text-xs font-black text-[#e8b4b4] uppercase tracking-widest">Routine Name</label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={e => setData('name', e.target.value)}
                                            className="w-full bg-[#2b1a1a] border border-[#3e2121] rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary transition-all outline-none"
                                            placeholder="e.g. Chest & Triceps"
                                        />
                                        {errors.name && <p className="text-red-400 text-xs mt-1">{errors.name}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <label className="text-xs font-black text-[#e8b4b4] uppercase tracking-widest">Focus</label>
                                        <input
                                            type="text"
                                            value={data.focus}
                                            onChange={e => setData('focus', e.target.value)}
                                            className="w-full bg-[#2b1a1a] border border-[#3e2121] rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary transition-all outline-none"
                                            placeholder="e.g. Strength"
                                        />
                                        {errors.focus && <p className="text-red-400 text-xs mt-1">{errors.focus}</p>}
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <label className="text-xs font-black text-[#e8b4b4] uppercase tracking-widest">Scheduled Day</label>
                                        <div className="grid grid-cols-4 md:grid-cols-7 gap-2">
                                            {days.map(day => (
                                                <button
                                                    key={day}
                                                    type="button"
                                                    onClick={() => setData('scheduled_date', data.scheduled_date === day ? '' : day)}
                                                    className={`px-2 py-2 rounded-lg text-xs font-bold border transition-all ${data.scheduled_date === day
                                                            ? 'bg-primary text-white border-primary'
                                                            : 'bg-[#1c0f0f] text-[#e8b4b4] border-[#3e2121] hover:border-primary/50'
                                                        }`}
                                                >
                                                    {day.slice(0, 3)}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-4 mb-8">
                                    <div className="flex items-center justify-between">
                                        <label className="text-xs font-black text-[#e8b4b4] uppercase tracking-widest">Exercises</label>
                                        <button
                                            type="button"
                                            onClick={handleAddExerciseRow}
                                            className="text-xs font-bold text-primary hover:text-primary-hover flex items-center gap-1"
                                        >
                                            <span className="material-symbols-outlined text-sm">add</span>
                                            Add Exercise
                                        </button>
                                    </div>

                                    {data.exercises.map((exercise, index) => (
                                        <div key={index} className="bg-[#2b1a1a]/50 border border-[#3e2121] rounded-xl p-4 animate-in fade-in slide-in-from-top-2">
                                            <div className="flex justify-between items-start gap-4 mb-3">
                                                <div className="flex-1">
                                                    <input
                                                        type="text"
                                                        list={`exercises-list-${index}`}
                                                        value={exercise.name}
                                                        onChange={e => handleExerciseChange(index, 'name', e.target.value)}
                                                        className="w-full bg-[#1c0f0f] border border-[#3e2121] rounded-lg px-3 py-2 text-sm text-white focus:border-primary outline-none"
                                                        placeholder="Exercise Name"
                                                    />
                                                    <datalist id={`exercises-list-${index}`}>
                                                        {exercises.map(e => <option key={e.id} value={e.name} />)}
                                                    </datalist>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => handleRemoveExerciseRow(index)}
                                                    className="text-red-400 hover:text-red-300 p-1"
                                                >
                                                    <span className="material-symbols-outlined text-lg">delete</span>
                                                </button>
                                            </div>
                                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                                <div>
                                                    <input
                                                        type="number"
                                                        value={exercise.target_sets}
                                                        onChange={e => handleExerciseChange(index, 'target_sets', e.target.value)}
                                                        className="w-full bg-[#1c0f0f] border border-[#3e2121] rounded-lg px-3 py-2 text-xs text-white focus:border-primary outline-none"
                                                        placeholder="Sets"
                                                    />
                                                </div>
                                                <div>
                                                    <input
                                                        type="text"
                                                        value={exercise.target_reps}
                                                        onChange={e => handleExerciseChange(index, 'target_reps', e.target.value)}
                                                        className="w-full bg-[#1c0f0f] border border-[#3e2121] rounded-lg px-3 py-2 text-xs text-white focus:border-primary outline-none"
                                                        placeholder="Reps (e.g. 8-12)"
                                                    />
                                                </div>
                                                <div>
                                                    <input
                                                        type="text"
                                                        value={exercise.target_weight}
                                                        onChange={e => handleExerciseChange(index, 'target_weight', e.target.value)}
                                                        className="w-full bg-[#1c0f0f] border border-[#3e2121] rounded-lg px-3 py-2 text-xs text-white focus:border-primary outline-none"
                                                        placeholder="Weight (kg)"
                                                    />
                                                </div>
                                                <div>
                                                    <input
                                                        type="text"
                                                        value={exercise.notes}
                                                        onChange={e => handleExerciseChange(index, 'notes', e.target.value)}
                                                        className="w-full bg-[#1c0f0f] border border-[#3e2121] rounded-lg px-3 py-2 text-xs text-white focus:border-primary outline-none"
                                                        placeholder="Notes"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    ))}

                                    {data.exercises.length === 0 && (
                                        <div className="text-center py-8 border-2 border-dashed border-[#3e2121] rounded-xl">
                                            <p className="text-[#e8b4b4] text-sm mb-2">No exercises added yet.</p>
                                            <button
                                                type="button"
                                                onClick={handleAddExerciseRow}
                                                className="text-primary font-bold text-sm hover:underline"
                                            >
                                                Add your first exercise
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </form>

                            <div className="p-6 border-t border-[#3e2121] bg-[#2b1a1a] flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setIsCreateModalOpen(false);
                                        setEditingRoutineId(null);
                                        reset();
                                    }}
                                    className="px-6 py-2 rounded-xl font-bold text-[#e8b4b4] hover:text-white transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={submit}
                                    disabled={processing}
                                    className="bg-primary hover:bg-primary-hover text-white px-8 py-2 rounded-xl font-black transition-all shadow-lg hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {processing ? 'Saving...' : (editingRoutineId ? 'Update Routine' : 'Create Routine')}
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <style dangerouslySetInnerHTML={{
                __html: `
                .custom-scrollbar::-webkit-scrollbar { width: 4px; }
                .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background: #3e2121; border-radius: 10px; }
                .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #ef4444; }
            `}} />
        </GymLayout>
    );
}
