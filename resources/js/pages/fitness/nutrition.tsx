import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import NutritionLayout from '@/layouts/nutrition-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { SharedData } from '@/types';

interface MealSuggestion {
    name: string;
    calories: number;
    protein: number;
    carbs: number;
    fats: number;
    reason: string;
}

interface AiEnabled {
    ai_enabled?: boolean;
}

interface Food {
    id: number;
    name: string;
    brand?: string | null;
    calories: number;
    protein: number | string;
    carbs: number | string;
    fats: number | string;
    serving_size?: number | string | null;
    serving_unit?: string | null;
}

interface MealItem {
    id: number;
    quantity: number | string;
    calories_snapshot: number | string;
    protein_snapshot: number | string;
    carbs_snapshot: number | string;
    fats_snapshot: number | string;
    food: Food;
}

interface MealLog {
    id: number;
    date: string;
    meal_type: string;
    items: MealItem[];
}

interface Props {
    logs: MealLog[];
    currentDate: string;
}

function calcIMC(weight: number | null, height: number | null): { imc: number | null; label: string; color: string } {
    if (!weight || !height || weight <= 0 || height <= 0) return { imc: null, label: '—', color: 'text-muted-foreground' };
    const hM = height > 3 ? height / 100 : height;
    const imc = weight / (hM * hM);
    if (imc < 18.5) return { imc, label: 'Bajo peso', color: 'text-blue-400' };
    if (imc < 25) return { imc, label: 'Normal', color: 'text-emerald-400' };
    if (imc < 30) return { imc, label: 'Sobrepeso', color: 'text-amber-400' };
    return { imc, label: 'Obesidad', color: 'text-red-400' };
}

export default function Nutrition({ logs, currentDate }: Props) {
    const { auth } = usePage<SharedData>().props;
    const user = auth.user as unknown as { weight?: number | string | null; height?: number | string | null; target_weight?: number | string | null };
    const aiEnabled = (user as unknown as AiEnabled).ai_enabled ?? false;

    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedMeal, setSelectedMeal] = useState<string>('breakfast');
    const [query, setQuery] = useState('');
    const [searchResults, setSearchResults] = useState<Food[]>([]);
    const [searching, setSearching] = useState(false);
    const [selectedFood, setSelectedFood] = useState<Food | null>(null);
    const [showCreateFood, setShowCreateFood] = useState(false);

    // AI Meal Assistant state
    const [mealSuggestion, setMealSuggestion] = useState<MealSuggestion | null>(null);
    const [loadingMealSuggestion, setLoadingMealSuggestion] = useState(false);
    const [mealSuggestionError, setMealSuggestionError] = useState('');

    const form = useForm({
        date: currentDate,
        meal_type: 'breakfast' as string,
        food_id: '' as number | string,
        quantity: 1 as number,
    });

    const createFoodForm = useForm({
        name: '',
        brand: '',
        calories: '' as number | string,
        protein: '' as number | string,
        carbs: '' as number | string,
        fats: '' as number | string,
        serving_size: 100 as number | string,
        serving_unit: 'g' as string,
    });

    // Date navigation
    const changeDate = (offset: number) => {
        const d = new Date(currentDate);
        d.setDate(d.getDate() + offset);
        const iso = d.toISOString().slice(0, 10);
        router.get('/fitness/nutrition', { date: iso }, { preserveState: true, preserveScroll: true });
    };

    const handleDateInput = (e: React.ChangeEvent<HTMLInputElement>) => {
        router.get('/fitness/nutrition', { date: e.target.value }, { preserveState: true, preserveScroll: true });
    };

    // Search debounce
    useEffect(() => {
        if (!query || query.trim().length < 2) {
            setSearchResults([]);
            return;
        }
        const id = setTimeout(async () => {
            setSearching(true);
            try {
                const res = await fetch(`/nutrition/foods/search?query=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                });
                if (res.ok) {
                    const data = await res.json();
                    setSearchResults(data);
                }
            } catch {
                // ignore
            } finally {
                setSearching(false);
            }
        }, 300);
        return () => clearTimeout(id);
    }, [query]);

    const openDialog = (meal: string) => {
        setSelectedMeal(meal);
        form.setData({
            date: currentDate,
            meal_type: meal,
            food_id: '',
            quantity: 1,
        });
        setSelectedFood(null);
        setQuery('');
        setSearchResults([]);
        setShowCreateFood(false);
        setDialogOpen(true);
    };

    const handleSelectFood = (food: Food) => {
        setSelectedFood(food);
        form.setData('food_id', food.id);
        setQuery(food.name);
        setSearchResults([]);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/nutrition/logs/items', {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                setSelectedFood(null);
                setQuery('');
            },
        });
    };

    const handleCreateFood = (e: React.FormEvent) => {
        e.preventDefault();
        createFoodForm.post('/nutrition/foods', {
            preserveScroll: true,
            onSuccess: () => {
                // after creating, refetch search with name
                const name = createFoodForm.data.name;
                setQuery(name);
                setShowCreateFood(false);
                createFoodForm.reset();
                // trigger search again via query effect
            },
        });
    };

    const handleDelete = (id: number) => {
        if (!confirm('¿Eliminar alimento?')) return;
        router.delete(`/nutrition/logs/items/${id}`, { preserveScroll: true });
    };

    const fetchMealSuggestion = async () => {
        setLoadingMealSuggestion(true);
        setMealSuggestionError('');
        try {
            const res = await fetch(`/ai/suggest-meal?date=${currentDate}`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.suggestion) {
                    setMealSuggestion(data.suggestion);
                } else {
                    setMealSuggestionError(data.message || 'Could not generate a suggestion.');
                }
            } else {
                setMealSuggestionError('Failed to fetch suggestion.');
            }
        } catch {
            setMealSuggestionError('Network error. Please try again.');
        } finally {
            setLoadingMealSuggestion(false);
        }
    };

    // Totals
    const totals = useMemo(() => {
        let cal = 0, pro = 0, carb = 0, fat = 0;
        logs.forEach((l) => {
            l.items.forEach((it) => {
                cal += Number(it.calories_snapshot) || 0;
                pro += Number(it.protein_snapshot) || 0;
                carb += Number(it.carbs_snapshot) || 0;
                fat += Number(it.fats_snapshot) || 0;
            });
        });
        return { cal, pro, carb, fat };
    }, [logs]);

    const goals = { calories: 2400, protein: 180, carbs: 250, fats: 70 };
    const calPct = Math.min(100, Math.round((totals.cal / goals.calories) * 100));
    const proPct = Math.min(100, Math.round((totals.pro / goals.protein) * 100));
    const carbPct = Math.min(100, Math.round((totals.carb / goals.carbs) * 100));
    const fatPct = Math.min(100, Math.round((totals.fat / goals.fats) * 100));

    const macroTotal = totals.pro + totals.carb + totals.fat;
    const proShare = macroTotal ? Math.round((totals.pro / macroTotal) * 100) : 0;
    const carbShare = macroTotal ? Math.round((totals.carb / macroTotal) * 100) : 0;
    const fatShare = macroTotal ? Math.round((totals.fat / macroTotal) * 100) : 0;

    // ring dasharray calc - simple proportional
    // For visual, we use strokeDasharray as share% * 100/100 approx 100 circumference
    // Use circles with dashoffset technique - simplify: one circle per macro stacked

    const mealTypes = [
        { id: 'breakfast', label: 'Desayuno', icon: 'bakery_dining', color: 'text-blue-400', bg: 'bg-blue-400/10', border: 'border-blue-400/20' },
        { id: 'lunch', label: 'Comida', icon: 'restaurant', color: 'text-orange-400', bg: 'bg-orange-400/10', border: 'border-orange-400/20' },
        { id: 'dinner', label: 'Cena', icon: 'dinner_dining', color: 'text-purple-400', bg: 'bg-purple-400/10', border: 'border-purple-400/20' },
        { id: 'snack', label: 'Snack', icon: 'icecream', color: 'text-yellow-400', bg: 'bg-yellow-400/10', border: 'border-yellow-400/20' },
    ] as const;

    const logsByMeal = useMemo(() => {
        const map: Record<string, MealLog | undefined> = {};
        logs.forEach((l) => {
            map[l.meal_type] = l;
        });
        return map;
    }, [logs]);

    const weightNum = user.weight ? Number(user.weight) : null;
    const heightNum = user.height ? Number(user.height) : null;
    const targetWeightNum = user.target_weight ? Number(user.target_weight) : null;
    const imcInfo = calcIMC(weightNum, heightNum);

    return (
        <NutritionLayout>
            <Head title="Nutrición" />
            <div className="p-6 max-w-7xl mx-auto animate-in fade-in slide-in-from-bottom-4 duration-700">
                <header className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
                    <div>
                        <h1 className="text-4xl font-black text-white tracking-tight leading-none">Nutrición diaria</h1>
                        <p className="mt-2 text-muted-foreground font-medium uppercase text-xs tracking-widest flex items-center gap-2">
                            <span className="h-1.5 w-1.5 rounded-full bg-primary animate-pulse"></span>
                            Registra tus macros y comidas
                        </p>
                    </div>
                    <div className="flex items-center gap-2 p-2 bg-card rounded-xl border border-border shadow-sm">
                        <button
                            type="button"
                            onClick={() => changeDate(-1)}
                            className="p-1 hover:bg-background rounded-lg transition-colors text-muted-foreground hover:text-white"
                            aria-label="Día anterior"
                        >
                            <span className="material-symbols-outlined">chevron_left</span>
                        </button>
                        <input
                            type="date"
                            value={currentDate}
                            onChange={handleDateInput}
                            className="bg-transparent border-none text-white font-bold text-sm focus:ring-0 cursor-pointer uppercase p-0"
                        />
                        <button
                            type="button"
                            onClick={() => changeDate(1)}
                            className="p-1 hover:bg-background rounded-lg transition-colors text-muted-foreground hover:text-white"
                            aria-label="Día siguiente"
                        >
                            <span className="material-symbols-outlined">chevron_right</span>
                        </button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.get('/fitness/nutrition', { date: new Date().toISOString().slice(0, 10) })}
                            className="ml-2 text-xs"
                        >
                            Hoy
                        </Button>
                    </div>
                </header>

                {/* Métricas corporales */}
                <div className="rounded-2xl bg-card border border-border p-5 mb-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <div className="h-12 w-12 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary">
                            <span className="material-symbols-outlined">monitor_weight</span>
                        </div>
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Métricas corporales</p>
                            {weightNum && heightNum ? (
                                <div className="flex items-baseline gap-3 mt-1">
                                    <span className="text-sm font-bold text-white">{weightNum} kg</span>
                                    <span className="text-muted-foreground">·</span>
                                    <span className="text-sm font-bold text-white">{heightNum} cm</span>
                                    {targetWeightNum && (
                                        <>
                                            <span className="text-muted-foreground">→</span>
                                            <span className="text-sm font-bold text-primary">{targetWeightNum} kg objetivo</span>
                                        </>
                                    )}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground mt-1">Completa peso y altura en <a href="/settings/profile" className="text-primary underline">Perfil</a></p>
                            )}
                        </div>
                    </div>
                    {imcInfo.imc !== null ? (
                        <div className="flex items-center gap-6">
                            <div className="text-right">
                                <p className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">IMC</p>
                                <p className="text-2xl font-black text-white">{imcInfo.imc.toFixed(1)}</p>
                                <p className={`text-xs font-bold ${imcInfo.color}`}>{imcInfo.label}</p>
                            </div>
                            <div className="hidden sm:block h-12 w-px bg-border" />
                            <div className="text-xs text-muted-foreground max-w-[160px]">
                                {imcInfo.label === 'Normal' ? '¡En rango saludable!' : imcInfo.label === 'Sobrepeso' ? 'Considera ajustar tu objetivo calórico.' : imcInfo.label === 'Bajo peso' ? 'Aumenta aporte calórico progresivo.' : 'Consulta a tu profesional de salud.'}
                            </div>
                        </div>
                    ) : null}
                </div>

                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
                    {[
                        { label: 'Calorías', val: `${totals.cal.toLocaleString('es-ES')}`, goal: `/ ${goals.calories.toLocaleString('es-ES')}`, progress: calPct, color: 'bg-primary' },
                        { label: 'Proteína', val: `${Math.round(totals.pro)}g`, goal: `/ ${goals.protein}g`, progress: proPct, color: 'bg-blue-500' },
                        { label: 'Carbohidratos', val: `${Math.round(totals.carb)}g`, goal: `/ ${goals.carbs}g`, progress: carbPct, color: 'bg-orange-500' },
                        { label: 'Grasas', val: `${Math.round(totals.fat)}g`, goal: `/ ${goals.fats}g`, progress: fatPct, color: 'bg-yellow-500' },
                    ].map((card, i) => (
                        <div key={i} className="bg-card rounded-2xl p-5 border border-border shadow-lg">
                            <span className="text-[10px] font-black text-muted-foreground uppercase tracking-widest">{card.label}</span>
                            <div className="mt-2 flex items-baseline gap-1.5">
                                <span className="text-2xl font-black text-white">{card.val}</span>
                                <span className="text-[10px] font-bold text-muted-foreground">{card.goal}</span>
                            </div>
                            <div className="mt-4 h-1.5 w-full bg-background rounded-full overflow-hidden">
                                <div className={`${card.color} h-full transition-all duration-1000`} style={{ width: `${card.progress}%` }}></div>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="rounded-2xl bg-card border border-border p-6 mb-8 flex flex-col md:flex-row items-center gap-8">
                    <div className="relative size-40 shrink-0">
                        <svg viewBox="0 0 36 36" className="size-40 -rotate-90">
                            <circle cx="18" cy="18" r="16" fill="none" stroke="#1c0f0f" strokeWidth="4" />
                            {macroTotal > 0 ? (
                                <>
                                    <circle cx="18" cy="18" r="16" fill="none" stroke="#ef4444" strokeWidth="4" strokeDasharray={`${proShare} ${100 - proShare}`} strokeLinecap="round" />
                                    <circle cx="18" cy="18" r="16" fill="none" stroke="#3b82f6" strokeWidth="4" strokeDasharray={`${carbShare} ${100 - carbShare}`} strokeDashoffset={`-${proShare}`} strokeLinecap="round" />
                                    <circle cx="18" cy="18" r="16" fill="none" stroke="#eab308" strokeWidth="4" strokeDasharray={`${fatShare} ${100 - fatShare}`} strokeDashoffset={`-${proShare + carbShare}`} strokeLinecap="round" />
                                </>
                            ) : (
                                <circle cx="18" cy="18" r="16" fill="none" stroke="#3e2121" strokeWidth="4" strokeDasharray="0 100" />
                            )}
                        </svg>
                        <div className="absolute inset-0 flex flex-col items-center justify-center">
                            <span className="text-2xl font-black text-white">{totals.cal.toLocaleString('es-ES')}</span>
                            <span className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">kcal</span>
                        </div>
                    </div>
                    <div className="flex-1 grid grid-cols-3 gap-4 w-full">
                        {[
                            { label: 'Proteína', val: `${proShare}%`, color: 'bg-primary', dot: 'bg-primary', grams: `${Math.round(totals.pro)}g` },
                            { label: 'Carbohidratos', val: `${carbShare}%`, color: 'bg-blue-500', dot: 'bg-blue-500', grams: `${Math.round(totals.carb)}g` },
                            { label: 'Grasas', val: `${fatShare}%`, color: 'bg-yellow-500', dot: 'bg-yellow-500', grams: `${Math.round(totals.fat)}g` },
                        ].map((m) => (
                            <div key={m.label} className="flex flex-col gap-2 rounded-xl bg-background border border-border p-4">
                                <div className="flex items-center gap-2">
                                    <span className={`h-2 w-2 rounded-full ${m.dot}`}></span>
                                    <span className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">{m.label}</span>
                                </div>
                                <span className="text-xl font-black text-white">{m.val}</span>
                                <span className="text-[10px] font-bold text-muted-foreground">{m.grams}</span>
                                <div className="h-1.5 w-full bg-card rounded-full overflow-hidden">
                                    <div className={`${m.color} h-full`} style={{ width: m.val }}></div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* AI Meal Assistant */}
                <div className="rounded-2xl bg-card border border-border p-6 mb-8">
                    <div className="flex items-center gap-3 mb-4">
                        <div className="h-10 w-10 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary">
                            <span className="material-symbols-outlined">auto_awesome</span>
                        </div>
                        <div>
                            <h3 className="text-lg font-black text-white">AI Meal Assistant</h3>
                            <p className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Get a meal suggestion to hit your targets</p>
                        </div>
                    </div>

                    {!aiEnabled ? (
                        <div className="bg-background rounded-xl border border-border p-4 text-center">
                            <span className="material-symbols-outlined text-muted-foreground text-3xl mb-2 block">settings</span>
                            <p className="text-sm text-muted-foreground">
                                AI not configured. Enable it in <a href="/settings/profile" className="text-primary underline">Settings</a>.
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="grid grid-cols-4 gap-3 mb-4">
                                {[
                                    { label: 'Remaining Calories', val: `${Math.max(0, goals.calories - totals.cal)}`, unit: 'kcal', color: 'text-primary' },
                                    { label: 'Remaining Protein', val: `${Math.max(0, goals.protein - Math.round(totals.pro))}`, unit: 'g', color: 'text-blue-400' },
                                    { label: 'Remaining Carbs', val: `${Math.max(0, goals.carbs - Math.round(totals.carb))}`, unit: 'g', color: 'text-orange-400' },
                                    { label: 'Remaining Fats', val: `${Math.max(0, goals.fats - Math.round(totals.fat))}`, unit: 'g', color: 'text-yellow-400' },
                                ].map((r) => (
                                    <div key={r.label} className="bg-background rounded-xl border border-border p-3 text-center">
                                        <p className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">{r.label}</p>
                                        <p className={`text-xl font-black mt-1 ${r.color}`}>{r.val}<span className="text-xs">{r.unit}</span></p>
                                    </div>
                                ))}
                            </div>

                            {!mealSuggestion && !mealSuggestionError && (
                                <button
                                    type="button"
                                    onClick={fetchMealSuggestion}
                                    disabled={loadingMealSuggestion}
                                    className="w-full bg-primary/10 hover:bg-primary/20 border border-primary/20 text-primary font-black text-sm py-3 rounded-xl transition-all flex items-center justify-center gap-2"
                                >
                                    {loadingMealSuggestion ? (
                                        <>
                                            <span className="material-symbols-outlined animate-spin">progress_activity</span>
                                            Analyzing your macros...
                                        </>
                                    ) : (
                                        <>
                                            <span className="material-symbols-outlined">restaurant</span>
                                            Suggest Meal
                                        </>
                                    )}
                                </button>
                            )}

                            {mealSuggestionError && (
                                <div className="bg-destructive/10 border border-destructive/20 rounded-xl p-4 text-center">
                                    <p className="text-sm text-destructive">{mealSuggestionError}</p>
                                    <button
                                        type="button"
                                        onClick={fetchMealSuggestion}
                                        className="mt-2 text-xs font-bold text-primary hover:underline"
                                    >
                                        Try Again
                                    </button>
                                </div>
                            )}

                            {mealSuggestion && (
                                <div className="bg-background rounded-xl border border-primary/20 p-5">
                                    <div className="flex items-center justify-between mb-3">
                                        <h4 className="text-lg font-black text-white">{mealSuggestion.name}</h4>
                                        <button
                                            type="button"
                                            onClick={() => setMealSuggestion(null)}
                                            className="text-xs text-muted-foreground hover:text-white"
                                        >
                                            Clear
                                        </button>
                                    </div>
                                    <div className="grid grid-cols-4 gap-3 mb-3">
                                        <div className="text-center">
                                            <p className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Calories</p>
                                            <p className="text-lg font-black text-primary">{mealSuggestion.calories} kcal</p>
                                        </div>
                                        <div className="text-center">
                                            <p className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Protein</p>
                                            <p className="text-lg font-black text-blue-400">{mealSuggestion.protein}g</p>
                                        </div>
                                        <div className="text-center">
                                            <p className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Carbs</p>
                                            <p className="text-lg font-black text-orange-400">{mealSuggestion.carbs}g</p>
                                        </div>
                                        <div className="text-center">
                                            <p className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Fats</p>
                                            <p className="text-lg font-black text-yellow-400">{mealSuggestion.fats}g</p>
                                        </div>
                                    </div>
                                    <p className="text-sm text-muted-foreground">{mealSuggestion.reason}</p>
                                    <button
                                        type="button"
                                        onClick={fetchMealSuggestion}
                                        className="mt-3 text-xs font-bold text-primary hover:underline"
                                    >
                                        Get Another Suggestion
                                    </button>
                                </div>
                            )}
                        </>
                    )}
                </div>

                <div className="space-y-6">
                    {mealTypes.map((meal) => {
                        const log = logsByMeal[meal.id];
                        const items = log?.items ?? [];
                        const mealCal = items.reduce((a, b) => a + Number(b.calories_snapshot), 0);
                        const mealPro = items.reduce((a, b) => a + Number(b.protein_snapshot), 0);
                        return (
                            <section key={meal.id} className="bg-card rounded-2xl shadow-xl border border-border overflow-hidden">
                                <div className="p-4 border-b border-border bg-white/[0.02] flex justify-between items-center group">
                                    <div className="flex items-center gap-4">
                                        <div className={`${meal.bg} ${meal.color} ${meal.border} border p-2.5 rounded-xl transition-transform group-hover:scale-110`}>
                                            <span className="material-symbols-outlined text-[24px]">{meal.icon}</span>
                                        </div>
                                        <div>
                                            <h2 className="text-lg font-black text-white capitalize leading-tight">{meal.label}</h2>
                                            <p className="text-[10px] font-bold text-muted-foreground uppercase tracking-tighter">
                                                {items.length ? `${items.length} alimento(s) · ${mealCal} kcal · ${Math.round(mealPro)}g proteína` : 'Recomendado: 600 - 800 kcal'}
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => openDialog(meal.id)}
                                        className="flex items-center gap-2 text-primary hover:text-primary font-bold text-xs uppercase tracking-widest px-4 py-2 hover:bg-white/5 rounded-xl transition-all border border-transparent hover:border-primary/20"
                                    >
                                        <span className="material-symbols-outlined text-[20px]">add_circle</span>
                                        Añadir alimento
                                    </button>
                                </div>
                                {items.length ? (
                                    <div className="divide-y divide-border">
                                        {items.map((it) => (
                                            <div key={it.id} className="p-4 flex items-center justify-between gap-4 hover:bg-white/[0.02] transition-colors">
                                                <div className="min-w-0">
                                                    <p className="font-bold text-white truncate">{it.food.name}</p>
                                                    {it.food.brand && <p className="text-xs text-muted-foreground">{it.food.brand}</p>}
                                                    <p className="text-[11px] text-muted-foreground mt-1">
                                                        {Number(it.quantity)} × {it.food.serving_size ? `${it.food.serving_size}${it.food.serving_unit ?? 'g'}` : 'porción'} · {it.food.calories} kcal
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-4 shrink-0">
                                                    <div className="text-right">
                                                        <p className="text-sm font-black text-white">{Number(it.calories_snapshot)} kcal</p>
                                                        <p className="text-[10px] text-muted-foreground">
                                                            P {Number(it.protein_snapshot).toFixed(1)} · C {Number(it.carbs_snapshot).toFixed(1)} · G {Number(it.fats_snapshot).toFixed(1)}
                                                        </p>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDelete(it.id)}
                                                        className="h-8 w-8 rounded-lg bg-destructive/10 hover:bg-destructive/20 text-destructive flex items-center justify-center transition-colors"
                                                        aria-label="Eliminar"
                                                    >
                                                        <span className="material-symbols-outlined text-[18px]">delete</span>
                                                    </button>
                                                </div>
                                            </div>
                                        ))}
                                        <div className="p-3 bg-background/50 flex justify-between text-xs font-bold text-muted-foreground uppercase tracking-widest">
                                            <span>Total {meal.label}</span>
                                            <span className="text-white">{mealCal} kcal</span>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="p-10 flex flex-col items-center justify-center text-center opacity-60">
                                        <span className="material-symbols-outlined text-border text-5xl mb-3">no_food</span>
                                        <p className="text-muted-foreground text-xs font-black uppercase tracking-widest">Sin alimentos registrados</p>
                                        <button
                                            type="button"
                                            onClick={() => openDialog(meal.id)}
                                            className="mt-3 text-primary text-xs font-bold uppercase tracking-widest hover:underline"
                                        >
                                            Añadir el primero
                                        </button>
                                    </div>
                                )}
                            </section>
                        );
                    })}
                </div>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="bg-card border-border text-foreground max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="text-white">Añadir alimento — {mealTypes.find((m) => m.id === selectedMeal)?.label}</DialogTitle>
                        <DialogDescription className="text-muted-foreground">
                            Busca un alimento existente o crea uno nuevo. Fecha: {currentDate}
                        </DialogDescription>
                    </DialogHeader>

                    {!showCreateFood ? (
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="food-search">Buscar alimento</Label>
                                <div className="relative">
                                    <Input
                                        id="food-search"
                                        placeholder="Ej: Pollo, Arroz, Huevo..."
                                        value={query}
                                        onChange={(e) => {
                                            setQuery(e.target.value);
                                            if (selectedFood && e.target.value !== selectedFood.name) {
                                                setSelectedFood(null);
                                                form.setData('food_id', '');
                                            }
                                        }}
                                        className="bg-background border-border"
                                        autoComplete="off"
                                    />
                                    {searching && (
                                        <span className="absolute right-3 top-2.5 text-xs text-muted-foreground">Buscando...</span>
                                    )}
                                </div>
                                {query.length >= 2 && !selectedFood && (
                                    <div className="rounded-lg border border-border bg-background max-h-56 overflow-auto divide-y divide-border">
                                        {searchResults.length ? (
                                            searchResults.map((f) => (
                                                <button
                                                    key={f.id}
                                                    type="button"
                                                    onClick={() => handleSelectFood(f)}
                                                    className="w-full text-left p-3 hover:bg-card transition-colors flex justify-between gap-2"
                                                >
                                                    <div>
                                                        <p className="text-sm font-bold text-white">{f.name}</p>
                                                        {f.brand && <p className="text-xs text-muted-foreground">{f.brand}</p>}
                                                        <p className="text-[11px] text-muted-foreground">
                                                            {f.calories} kcal · P{f.protein} C{f.carbs} G{f.fats} {f.serving_size ? `· ${f.serving_size}${f.serving_unit ?? 'g'}` : ''}
                                                        </p>
                                                    </div>
                                                    <span className="material-symbols-outlined text-primary">add_circle</span>
                                                </button>
                                            ))
                                        ) : !searching ? (
                                            <div className="p-4 text-center">
                                                <p className="text-sm text-muted-foreground">Sin resultados para “{query}”</p>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        createFoodForm.setData('name', query);
                                                        setShowCreateFood(true);
                                                    }}
                                                    className="mt-2 text-xs font-bold text-primary hover:underline"
                                                >
                                                    Crear “{query}” como alimento
                                                </button>
                                            </div>
                                        ) : null}
                                    </div>
                                )}
                                {selectedFood && (
                                    <div className="rounded-lg bg-background border border-primary/20 p-3 flex justify-between items-center">
                                        <div>
                                            <p className="text-sm font-bold text-white flex items-center gap-2">
                                                {selectedFood.name}
                                                <span className="text-[10px] bg-primary text-primary-foreground px-1.5 py-0.5 rounded font-black">SELECCIONADO</span>
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {selectedFood.calories} kcal · P{selectedFood.protein} C{selectedFood.carbs} G{selectedFood.fats}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setSelectedFood(null);
                                                form.setData('food_id', '');
                                                setQuery('');
                                            }}
                                            className="text-xs text-muted-foreground hover:text-white"
                                        >
                                            Cambiar
                                        </button>
                                    </div>
                                )}
                                <button
                                    type="button"
                                    onClick={() => setShowCreateFood(true)}
                                    className="text-xs text-primary hover:underline text-left"
                                >
                                    ¿No lo encuentras? Crear alimento nuevo
                                </button>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="quantity">Cantidad (porciones)</Label>
                                <Input
                                    id="quantity"
                                    type="number"
                                    step="0.1"
                                    min="0.1"
                                    value={form.data.quantity}
                                    onChange={(e) => form.setData('quantity', parseFloat(e.target.value) || 0)}
                                    className="bg-background border-border"
                                    required
                                />
                                {selectedFood && (
                                    <p className="text-xs text-muted-foreground">
                                        ≈ {Math.round(Number(selectedFood.calories) * form.data.quantity)} kcal ·{' '}
                                        { (Number(selectedFood.protein) * form.data.quantity).toFixed(1)}g proteína
                                    </p>
                                )}
                            </div>

                            {form.errors.food_id && <p className="text-sm text-destructive">{form.errors.food_id}</p>}
                            {form.errors.quantity && <p className="text-sm text-destructive">{form.errors.quantity}</p>}

                            <div className="flex justify-end gap-2 pt-2">
                                <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={!form.data.food_id || form.processing} className="bg-primary hover:bg-primary/90">
                                    {form.processing ? 'Guardando...' : 'Añadir'}
                                </Button>
                            </div>
                        </form>
                    ) : (
                        <form onSubmit={handleCreateFood} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="new-name">Nombre *</Label>
                                <Input
                                    id="new-name"
                                    value={createFoodForm.data.name}
                                    onChange={(e) => createFoodForm.setData('name', e.target.value)}
                                    placeholder="Ej: Pechuga de pollo"
                                    className="bg-background border-border"
                                    required
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="new-brand">Marca</Label>
                                <Input
                                    id="new-brand"
                                    value={createFoodForm.data.brand}
                                    onChange={(e) => createFoodForm.setData('brand', e.target.value)}
                                    placeholder="Opcional"
                                    className="bg-background border-border"
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="new-cal">Calorías *</Label>
                                    <Input
                                        id="new-cal"
                                        type="number"
                                        min="0"
                                        value={createFoodForm.data.calories}
                                        onChange={(e) => createFoodForm.setData('calories', e.target.value)}
                                        className="bg-background border-border"
                                        required
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="new-serv">Porción (g/ml)</Label>
                                    <Input
                                        id="new-serv"
                                        type="number"
                                        min="0"
                                        step="0.1"
                                        value={createFoodForm.data.serving_size}
                                        onChange={(e) => createFoodForm.setData('serving_size', e.target.value)}
                                        className="bg-background border-border"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="new-pro">Proteína (g) *</Label>
                                    <Input
                                        id="new-pro"
                                        type="number"
                                        min="0"
                                        step="0.1"
                                        value={createFoodForm.data.protein}
                                        onChange={(e) => createFoodForm.setData('protein', e.target.value)}
                                        className="bg-background border-border"
                                        required
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="new-carb">Carbos (g) *</Label>
                                    <Input
                                        id="new-carb"
                                        type="number"
                                        min="0"
                                        step="0.1"
                                        value={createFoodForm.data.carbs}
                                        onChange={(e) => createFoodForm.setData('carbs', e.target.value)}
                                        className="bg-background border-border"
                                        required
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="new-fat">Grasas (g) *</Label>
                                    <Input
                                        id="new-fat"
                                        type="number"
                                        min="0"
                                        step="0.1"
                                        value={createFoodForm.data.fats}
                                        onChange={(e) => createFoodForm.setData('fats', e.target.value)}
                                        className="bg-background border-border"
                                        required
                                    />
                                </div>
                            </div>
                            {Object.entries(createFoodForm.errors).map(([k, v]) => (
                                <p key={k} className="text-sm text-destructive">
                                    {v as string}
                                </p>
                            ))}
                            <div className="flex justify-between gap-2 pt-2">
                                <Button type="button" variant="ghost" onClick={() => setShowCreateFood(false)}>
                                    Volver a buscar
                                </Button>
                                <div className="flex gap-2">
                                    <Button type="button" variant="outline" onClick={() => setShowCreateFood(false)}>
                                        Cancelar
                                    </Button>
                                    <Button type="submit" disabled={createFoodForm.processing} className="bg-primary hover:bg-primary/90">
                                        {createFoodForm.processing ? 'Creando...' : 'Crear alimento'}
                                    </Button>
                                </div>
                            </div>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </NutritionLayout>
    );
}
