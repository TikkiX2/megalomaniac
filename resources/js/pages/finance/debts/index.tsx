import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { Debt, CreditCard, Currency } from '@/types/finance';

interface Props {
    debts: {
        data: Debt[];
        links: any[];
    };
    creditCards: CreditCard[];
    currencies: Currency[];
    summary: {
        currency_id: number;
        total: number;
        currency: Currency;
    }[];
    filters: {
        credit_card_id?: string;
        status?: string;
        overdue?: boolean;
    };
}

export default function DebtsIndex({ debts, creditCards, currencies, summary, filters }: Props) {
    return (
        <MainLayout>
            <Head title="Debts" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                {/* Header Section */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div className="flex items-center justify-between gap-4 w-full md:w-auto">
                        <div>
                            <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                                Debts Tracking</h2>
                            <p className="mt-1 text-base font-medium text-orange-300">
                                Clear your debts, free your future.
                            </p>
                        </div>
                        <Link
                            href={finance.debts.create().url}
                            className="flex items-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-black text-[#102216] shadow-[0_0_20px_rgba(19,236,91,0.2)] hover:bg-green-400 transition-all"
                        >
                            <span className="material-symbols-outlined">add_circle</span>
                            Record Debt
                        </Link>
                    </div>
                </div>

                {/* Summary Widgets */}
                <div className="flex flex-wrap gap-4">
                    {summary.map((s) => (
                        <div key={s.currency_id} className="rounded-2xl bg-[#193322] border border-[#23482f] p-4 flex flex-col gap-1 min-w-[200px]">
                            <span className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Pending {s.currency.code}</span>
                            <span className="text-xl font-black text-rose-400">{s.currency.symbol} {parseFloat(s.total as any).toLocaleString()}</span>
                        </div>
                    ))}
                </div>

                {/* Debts Table */}
                <div className="overflow-hidden rounded-2xl bg-[#193322] border border-[#23482f] shadow-xl">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-[#23482f] bg-[#102216]/50">
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Due Date</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Description / Purchase</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Credit Card</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4] text-right">Remaining</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Status</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#23482f]/30">
                            {debts.data.length > 0 ? (
                                debts.data.map((d) => {
                                    const isOverdue = d.due_date && new Date(d.due_date) < new Date() && d.status !== 'paid';
                                    return (
                                        <tr key={d.id} className="group hover:bg-white/[0.02] transition-colors">
                                            <td className="px-6 py-4">
                                                <span className={`text-sm font-bold ${isOverdue ? 'text-rose-500' : 'text-[#92c9a4]'}`}>
                                                    {d.due_date ? new Date(d.due_date).toLocaleDateString() : 'No date'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex flex-col">
                                                    <span className="text-sm font-bold text-white">{d.purchase?.description || d.notes || 'Manual Debt'}</span>
                                                    {isOverdue && <span className="text-[9px] font-black uppercase tracking-tighter text-rose-500">Overdue!</span>}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                {d.credit_card ? (
                                                    <>
                                                        <span className="text-sm font-medium text-white">{d.credit_card.name}</span>
                                                        <span className="ml-2 text-[10px] text-[#92c9a4]">**** {d.credit_card.last_four_digits}</span>
                                                    </>
                                                ) : (
                                                    <span className="text-xs font-bold text-[#92c9a4] uppercase italic">Sin Tarjeta</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex flex-col text-right">
                                                    <span className="text-sm font-black text-rose-400">{d.currency?.symbol} {parseFloat(d.remaining_amount as any).toLocaleString()}</span>
                                                    <span className="text-[10px] text-[#92c9a4]">Total: {d.currency?.symbol}{parseFloat(d.total_amount as any).toLocaleString()}</span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-widest border ${d.status === 'paid' ? 'bg-primary/10 text-primary border-primary/20' :
                                                    d.status === 'partial' ? 'bg-sky-500/10 text-sky-400 border-sky-400/20' :
                                                        'bg-orange-500/10 text-orange-400 border-orange-400/20'
                                                    }`}>
                                                    {d.status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <Link
                                                    href={finance.debts.show(d.id).url}
                                                    className="rounded-lg p-2 text-[#92c9a4] hover:bg-white/5 hover:text-white transition-all"
                                                >
                                                    <span className="material-symbols-outlined text-[20px]">visibility</span>
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td colSpan={6} className="px-6 py-12 text-center">
                                        <p className="text-sm font-bold text-[#92c9a4] uppercase italic">No debts found. You are debt-free! 🕊️</p>
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

