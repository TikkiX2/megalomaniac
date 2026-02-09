import SupplementLayout from '@/layouts/supplement-layout';
import { Head } from '@inertiajs/react';

interface Props {
    supplements: any[];
    recentLogs: any[];
}

export default function SupplementPage({ supplements, recentLogs }: Props) {
    return (
        <SupplementLayout>
            <Head title="Supplements" />
            <div className="p-6 max-w-7xl mx-auto animate-in fade-in slide-in-from-bottom-4 duration-700 text-white">
                <header className="flex justify-between items-end mb-10">
                    <div>
                        <h1 className="text-4xl font-black tracking-tight leading-none text-white">Supplements</h1>
                        <p className="mt-2 text-[#92c9a4] font-medium uppercase text-xs tracking-widest">Stack & Inventory</p>
                    </div>
                    <button className="bg-primary text-[#102216] px-6 py-2.5 rounded-xl font-black text-sm shadow-[0_0_15px_rgba(19,236,91,0.2)] hover:bg-green-400 transition-all active:scale-95">
                        Add Supplement
                    </button>
                </header>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <div className="lg:col-span-8 flex flex-col gap-6">
                        <section className="bg-[#193322] rounded-2xl shadow-xl border border-[#23482f] overflow-hidden">
                            <div className="p-6 border-b border-[#23482f] bg-white/5">
                                <h2 className="text-lg font-black text-white leading-none">Inventory Stack</h2>
                            </div>
                            <div className="divide-y divide-[#23482f]">
                                {supplements.map(supp => (
                                    <div key={supp.id} className="p-6 flex justify-between items-center hover:bg-white/5 transition-all group">
                                        <div className="flex items-center gap-5">
                                            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#102216] border border-[#23482f] group-hover:border-primary/40 transition-colors">
                                                <span className="material-symbols-outlined text-primary text-[28px]">pill</span>
                                            </div>
                                            <div>
                                                <p className="font-black text-xl text-white group-hover:text-primary transition-colors">{supp.name}</p>
                                                <p className="text-xs font-bold text-[#92c9a4] uppercase tracking-tight">{supp.brand || 'Premium Stack'} • {supp.dosage_amount}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-8">
                                            <div className="text-right hidden sm:block">
                                                <p className={`text-lg font-black ${supp.stock_quantity <= supp.low_stock_threshold ? 'text-rose-500' : 'text-primary'}`}>
                                                    {supp.stock_quantity} <span className="text-xs font-bold text-[#92c9a4]">left</span>
                                                </p>
                                                <p className="text-[10px] font-bold text-[#92c9a4] uppercase tracking-tighter">Current Stock</p>
                                            </div>
                                            <button className="bg-[#102216] text-white border border-[#23482f] px-5 py-2.5 rounded-xl font-black text-[11px] uppercase tracking-widest hover:border-primary/50 transition-all active:scale-95">
                                                Log Intake
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>

                    <div className="lg:col-span-4">
                        <section className="bg-[#193322] p-6 rounded-2xl shadow-xl border border-[#23482f] sticky top-8">
                            <div className="flex items-center justify-between mb-8">
                                <h2 className="text-lg font-black text-white leading-none">Recent Activity</h2>
                                <span className="material-symbols-outlined text-primary">history</span>
                            </div>
                            <ul className="space-y-6">
                                {recentLogs.length > 0 ? recentLogs.map(log => (
                                    <li key={log.id} className="relative pl-6 before:absolute before:left-0 before:top-2 before:bottom-2 before:w-[2px] before:bg-primary before:rounded-full">
                                        <p className="font-bold text-white text-sm">{log.supplement?.name}</p>
                                        <p className="text-[10px] font-black text-[#92c9a4] uppercase tracking-wider mt-1">{new Date(log.taken_at).toLocaleString('en-US', { hour: '2-digit', minute: '2-digit', month: 'short', day: 'numeric' })}</p>
                                    </li>
                                )) : (
                                    <div className="py-20 text-center opacity-40">
                                        <span className="material-symbols-outlined text-5xl mb-2">event_busy</span>
                                        <p className="text-[10px] font-bold uppercase tracking-widest">No recent intake</p>
                                    </div>
                                )}
                            </ul>

                            <div className="mt-8 pt-8 border-t border-[#23482f]">
                                <div className="bg-[#102216] p-4 rounded-xl border border-[#23482f]">
                                    <p className="text-xs font-black text-[#92c9a4] uppercase tracking-widest mb-1">Stack Strength</p>
                                    <div className="text-2xl font-black text-white leading-none">85%</div>
                                    <div className="mt-3 h-1 w-full bg-[#193322] rounded-full">
                                        <div className="bg-primary h-full rounded-full" style={{ width: '85%' }}></div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </SupplementLayout>
    );
}
