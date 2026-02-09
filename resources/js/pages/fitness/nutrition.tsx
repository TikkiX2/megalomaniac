import NutritionLayout from '@/layouts/nutrition-layout';
import { Head } from '@inertiajs/react';

interface Props {
    logs: any[];
    currentDate: string;
}

export default function Nutrition({ logs, currentDate }: Props) {
    const mealTypes = [
        { id: 'breakfast', icon: 'bakery_dining', color: 'text-blue-400', bg: 'bg-blue-400/10', border: 'border-blue-400/20' },
        { id: 'lunch', icon: 'restaurant', color: 'text-orange-400', bg: 'bg-orange-400/10', border: 'border-orange-400/20' },
        { id: 'dinner', icon: 'dinner_dining', color: 'text-purple-400', bg: 'bg-purple-400/10', border: 'border-purple-400/20' },
        { id: 'snack', icon: 'icecream', color: 'text-yellow-400', bg: 'bg-yellow-400/10', border: 'border-yellow-400/20' },
    ];

    return (
        <NutritionLayout>
            <Head title="Nutrition Tracker" />
            <div className="p-6 max-w-7xl mx-auto animate-in fade-in slide-in-from-bottom-4 duration-700">
                <header className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
                    <div>
                        <h1 className="text-4xl font-black text-white tracking-tight leading-none">Daily Nutrition</h1>
                        <p className="mt-2 text-[#92c9a4] font-medium uppercase text-xs tracking-widest flex items-center gap-2">
                            <span className="h-1.5 w-1.5 rounded-full bg-primary animate-pulse"></span>
                            Track your macros & meals
                        </p>
                    </div>
                    <div className="flex items-center gap-4 p-2 bg-[#193322] rounded-xl border border-[#23482f] shadow-sm">
                        <button className="p-1 hover:bg-[#102216] rounded-lg transition-colors text-[#92c9a4] hover:text-white">
                            <span className="material-symbols-outlined">chevron_left</span>
                        </button>
                        <input
                            type="date"
                            defaultValue={currentDate}
                            className="bg-transparent border-none text-white font-bold text-sm focus:ring-0 cursor-pointer uppercase p-0"
                        />
                        <button className="p-1 hover:bg-[#102216] rounded-lg transition-colors text-[#92c9a4] hover:text-white">
                            <span className="material-symbols-outlined">chevron_right</span>
                        </button>
                    </div>
                </header>

                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
                    {/* Summary cards with FitTrack palette */}
                    {[
                        { label: 'Calories', val: '1,850', goal: '/ 2,400', progress: 77, color: 'bg-primary' },
                        { label: 'Protein', val: '145g', goal: '/ 180g', progress: 80, color: 'bg-blue-500' },
                        { label: 'Carbs', val: '220g', goal: '/ 250g', progress: 88, color: 'bg-orange-500' },
                        { label: 'Fats', labelColor: 'Fats', val: '65g', goal: '/ 70g', progress: 92, color: 'bg-yellow-500' },
                    ].map((card, i) => (
                        <div key={i} className="bg-[#193322] rounded-2xl p-5 border border-[#23482f] shadow-lg">
                            <span className="text-[10px] font-black text-[#92c9a4] uppercase tracking-widest">{card.label}</span>
                            <div className="mt-2 flex items-baseline gap-1.5">
                                <span className="text-2xl font-black text-white">{card.val}</span>
                                <span className="text-[10px] font-bold text-[#92c9a4]">{card.goal}</span>
                            </div>
                            <div className="mt-4 h-1.5 w-full bg-[#102216] rounded-full overflow-hidden">
                                <div className={`${card.color} h-full transition-all duration-1000`} style={{ width: `${card.progress}%` }}></div>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="space-y-6">
                    {mealTypes.map(meal => (
                        <section key={meal.id} className="bg-[#193322] rounded-2xl shadow-xl border border-[#23482f] overflow-hidden">
                            <div className="p-4 border-b border-[#23482f] bg-white/5 flex justify-between items-center group">
                                <div className="flex items-center gap-4">
                                    <div className={`${meal.bg} ${meal.color} ${meal.border} border p-2.5 rounded-xl transition-transform group-hover:scale-110`}>
                                        <span className="material-symbols-outlined text-[24px]">{meal.icon}</span>
                                    </div>
                                    <div>
                                        <h2 className="text-lg font-black text-white capitalize leading-tight">{meal.id}</h2>
                                        <p className="text-[10px] font-bold text-[#92c9a4] uppercase tracking-tighter">Recommended: 600 - 800 kcal</p>
                                    </div>
                                </div>
                                <button className="flex items-center gap-2 text-primary hover:text-green-400 font-bold text-xs uppercase tracking-widest px-4 py-2 hover:bg-white/5 rounded-xl transition-all">
                                    <span className="material-symbols-outlined text-[20px]">add_circle</span>
                                    Add Food
                                </button>
                            </div>
                            <div className="p-10 flex flex-col items-center justify-center text-center opacity-60">
                                <span className="material-symbols-outlined text-[#23482f] text-5xl mb-3">no_food</span>
                                <p className="text-[#92c9a4] text-xs font-black uppercase tracking-widest">No items logged yet</p>
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        </NutritionLayout>
    );
}
