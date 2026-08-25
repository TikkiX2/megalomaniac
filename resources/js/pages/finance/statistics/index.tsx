import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import type { Currency } from '@/types/finance';

interface Props {
    currencies: Currency[];
    currentCurrencyId: number;
    filters: {
        date_from: string;
        date_to: string;
        currency_id: number;
    };
    stats: {
        expenses_by_category: any[];
        monthly_trend: any[];
        debt_summary: any;
    };
}

export default function StatisticsIndex({ currencies, currentCurrencyId, filters, stats }: Props) {
    return (
        <MainLayout>
            <Head title="Finance Statistics" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                            Financial Analytics</h2>
                        <p className="mt-1 text-base font-medium text-primary">
                            Visualizing your path to freedom.
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <select
                            value={filters.currency_id}
                            className="bg-[#1c0f0f] border-[#3e2121] text-white rounded-lg px-3 py-2 text-sm"
                            onChange={(e) => {/* Handle change */ }}
                        >
                            {currencies.map(c => <option key={c.id} value={c.id}>{c.code}</option>)}
                        </select>
                        <input type="date" value={filters.date_from} className="bg-[#1c0f0f] border-[#3e2121] text-white rounded-lg px-3 py-2 text-sm" />
                        <input type="date" value={filters.date_to} className="bg-[#1c0f0f] border-[#3e2121] text-white rounded-lg px-3 py-2 text-sm" />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Expenses by Category List (Basic for now) */}
                    <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6 shadow-xl">
                        <h3 className="mb-6 text-sm font-black uppercase tracking-widest text-white">Expenses by Category</h3>
                        <div className="flex flex-col gap-4">
                            {stats.expenses_by_category.length > 0 ? (
                                stats.expenses_by_category.map((item, idx) => (
                                    <div key={idx} className="flex flex-col gap-2">
                                        <div className="flex justify-between items-center text-sm font-bold text-white">
                                            <span>{item.name}</span>
                                            <span>{item.total.toLocaleString()}</span>
                                        </div>
                                        <div className="h-2 w-full rounded-full bg-[#1c0f0f]">
                                            <div
                                                className="h-full rounded-full transition-all duration-1000"
                                                style={{
                                                    backgroundColor: item.color,
                                                    width: `${Math.min(100, (item.total / stats.expenses_by_category[0].total) * 100)}%`
                                                }}
                                            ></div>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="text-center text-[#e8b4b4] italic text-sm">No expense data for this period.</p>
                            )}
                        </div>
                    </div>

                    {/* Monthly Trend List */}
                    <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6 shadow-xl">
                        <h3 className="mb-6 text-sm font-black uppercase tracking-widest text-white">Monthly Trend</h3>
                        <div className="flex flex-col gap-4">
                            {stats.monthly_trend.map((item, idx) => (
                                <div key={idx} className="flex items-center justify-between border-b border-[#3e2121]/30 pb-3 last:border-0">
                                    <span className="text-sm font-bold text-white">{item.month}</span>
                                    <div className="flex gap-4 text-xs font-black uppercase tracking-tighter">
                                        <span className="text-primary">In: {item.income.toLocaleString()}</span>
                                        <span className="text-rose-400">Out: {item.expenses.toLocaleString()}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Cashflow Chart Rojizo */}
                    <div className="rounded-2xl bg-card border border-border p-6 shadow-xl lg:col-span-2">
                        <h3 className="mb-6 text-sm font-black uppercase tracking-widest text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-primary">trending_up</span>
                            Cashflow · Rojizo
                        </h3>
                        <div className="flex items-end gap-2 h-32">
                            {stats.monthly_trend.length > 0 ? (
                                stats.monthly_trend.map((item: any, idx: number) => {
                                    const max = Math.max(...stats.monthly_trend.map((m: any) => Math.max(m.income, m.expenses)), 1);
                                    const incomeH = (item.income / max) * 100;
                                    const expenseH = (item.expenses / max) * 100;
                                    const cashflow = item.income - item.expenses;
                                    return (
                                        <div key={idx} className="flex-1 flex flex-col items-center gap-1">
                                            <div className="w-full flex gap-1 items-end justify-center h-24">
                                                <div className="flex-1 rounded-t bg-primary/90" style={{ height: `${incomeH}%` }} title={`In: ${item.income}`}></div>
                                                <div className="flex-1 rounded-t bg-rose-500/80" style={{ height: `${expenseH}%` }} title={`Out: ${item.expenses}`}></div>
                                            </div>
                                            <span className={`text-[9px] font-black ${cashflow >= 0 ? 'text-primary' : 'text-rose-400'}`}>{cashflow >= 0 ? '+' : ''}{cashflow.toLocaleString()}</span>
                                            <span className="text-[9px] font-bold text-muted-foreground">{item.month.slice(0, 3)}</span>
                                        </div>
                                    );
                                })
                            ) : (
                                <p className="text-center text-muted-foreground italic text-sm w-full">Sin datos de tendencia.</p>
                            )}
                        </div>
                    </div>

                    {/* Debt Summary */}
                    <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6 shadow-xl lg:col-span-2">
                        <h3 className="mb-6 text-sm font-black uppercase tracking-widest text-white">Debt Amortization Summary</h3>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="flex flex-col gap-1 rounded-xl bg-[#1c0f0f]/50 p-6 border border-[#3e2121]/50">
                                <span className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Paid</span>
                                <span className="text-2xl font-black text-primary">{(stats.debt_summary?.paid || 0).toLocaleString()}</span>
                            </div>
                            <div className="flex flex-col gap-1 rounded-xl bg-[#1c0f0f]/50 p-6 border border-[#3e2121]/50 text-orange-400">
                                <span className="text-xs font-black uppercase tracking-widest">Remaining</span>
                                <span className="text-2xl font-black">{(stats.debt_summary?.remaining || 0).toLocaleString()}</span>
                            </div>
                            <div className="flex flex-col gap-1 rounded-xl bg-[#1c0f0f]/50 p-6 border border-[#3e2121]/50 text-white">
                                <span className="text-xs font-black uppercase tracking-widest">Total Liability</span>
                                <span className="text-2xl font-black">{(stats.debt_summary?.total || 0).toLocaleString()}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}

