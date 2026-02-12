import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { Income, IncomeSource, Currency } from '@/types/finance';

interface Props {
    incomes: {
        data: Income[];
        links: any[];
    };
    incomeSources: IncomeSource[];
    currencies: Currency[];
    summary: {
        currency_id: number;
        total: number;
        currency: Currency;
    }[];
    filters: {
        income_source_id?: string;
        currency_id?: string;
        date_from?: string;
        date_to?: string;
        is_recurring?: string;
    };
}

export default function IncomesIndex({ incomes, incomeSources, currencies, summary, filters }: Props) {
    return (
        <MainLayout>
            <Head title="Incomes" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                {/* Header Section */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                            Incomes</h2>
                        <p className="mt-1 text-base font-medium text-[#92c9a4]">
                            Harvest the fruits of your labor.
                        </p>
                    </div>
                    <div className="flex gap-3">
                        <Link
                            href={finance.incomes.create().url}
                            className="flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-black text-[#102216] shadow-[0_0_20px_rgba(19,236,91,0.25)] transition hover:bg-green-400 active:scale-95">
                            <span className="material-symbols-outlined font-bold" style={{ fontSize: '20px' }}>add_circle</span>
                            Add Income
                        </Link>
                    </div>
                </div>

                {/* Summary Widgets */}
                <div className="flex flex-wrap gap-4">
                    {summary.map((s) => (
                        <div key={s.currency_id} className="rounded-2xl bg-[#193322] border border-[#23482f] p-4 flex flex-col gap-1 min-w-[150px]">
                            <span className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Total {s.currency.code}</span>
                            <span className="text-xl font-black text-white">{s.currency.symbol} {parseFloat(s.total as any).toLocaleString()}</span>
                        </div>
                    ))}
                </div>

                {/* Incomes Table */}
                <div className="overflow-hidden rounded-2xl bg-[#193322] border border-[#23482f] shadow-xl">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-[#23482f] bg-[#102216]/50">
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Date</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Source</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Description</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4] text-right">Amount</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Type</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#23482f]/30">
                            {incomes.data.length > 0 ? (
                                incomes.data.map((i) => (
                                    <tr key={i.id} className="group hover:bg-white/[0.02] transition-colors">
                                        <td className="px-6 py-4">
                                            <span className="text-sm font-medium text-[#92c9a4]">{new Date(i.received_date).toLocaleDateString()}</span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="text-sm font-bold text-white">{i.income_source?.name}</span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="text-sm text-[#92c9a4]">{i.description || '-'}</span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <span className="text-sm font-black text-primary">{i.currency?.symbol} {parseFloat(i.amount as any).toLocaleString()}</span>
                                        </td>
                                        <td className="px-6 py-4">
                                            {i.is_recurring ? (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[9px] font-black uppercase tracking-widest text-primary border border-primary/20">
                                                    <span className="material-symbols-outlined text-[12px]">replay</span> Recurring
                                                </span>
                                            ) : (
                                                <span className="text-[10px] font-bold text-[#92c9a4] uppercase tracking-widest">One-time</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <Link
                                                href={finance.incomes.show(i.id).url}
                                                className="rounded-lg p-2 text-[#92c9a4] hover:bg-white/5 hover:text-white transition-all"
                                            >
                                                <span className="material-symbols-outlined text-[20px]">visibility</span>
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={6} className="px-6 py-12 text-center">
                                        <p className="text-sm font-bold text-[#92c9a4] uppercase italic">No incomes recorded yet.</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </MainLayout>
    );
}

