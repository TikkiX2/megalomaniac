import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { Withdrawal, WithdrawalCategory, Currency } from '@/types/finance';

interface Props {
    withdrawals: {
        data: Withdrawal[];
        links: any[];
    };
    categories: WithdrawalCategory[];
    currencies: Currency[];
    filters: {
        category_id?: string;
        currency_id?: string;
        start_date?: string;
        end_date?: string;
        recurring_only?: boolean;
    };
}

export default function WithdrawalsIndex({ withdrawals, categories, currencies, filters }: Props) {
    const { data, setData, get, processing } = useForm({
        category_id: filters.category_id || '',
        currency_id: filters.currency_id || '',
        start_date: filters.start_date || '',
        end_date: filters.end_date || '',
        recurring_only: filters.recurring_only || false,
    });

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        get(finance.withdrawals.index().url, {
            preserveState: true,
        });
    };

    return (
        <MainLayout>
            <Head title="Withdrawals" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                            Withdrawals & Services</h2>
                        <p className="mt-1 text-base font-medium text-[#e8b4b4]">
                            Manage your expenses, recurring payments, and obligations.
                        </p>
                    </div>
                    <div className="flex gap-3">
                        <Link
                            href={finance.withdrawalCategories.index().url}
                            className="flex items-center gap-2 rounded-lg border border-[#3e2121] bg-[#2b1a1a] px-4 py-2 text-sm font-bold text-white transition hover:bg-white/5"
                        >
                            <span className="material-symbols-outlined text-[20px]">category</span>
                            Categories
                        </Link>
                        <Link
                            href={finance.withdrawals.create().url}
                            className="flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-black text-white shadow-[0_0_20px_rgba(239,68,68,0.25)] transition hover:bg-primary/90 active:scale-95">
                            <span className="material-symbols-outlined font-bold" style={{ fontSize: '20px' }}>add</span>
                            Add Withdrawal
                        </Link>
                    </div>
                </div>

                {/* Filters */}
                <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6 shadow-xl">
                    <form onSubmit={handleFilter} className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5 items-end">
                        <div className="flex flex-col gap-1.5">
                            <Label className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Category</Label>
                            <select
                                value={data.category_id}
                                onChange={e => setData('category_id', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white rounded-lg px-3 py-2 text-sm h-10"
                            >
                                <option value="">All Categories</option>
                                {categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Date Range</Label>
                            <div className="flex gap-2">
                                <Input
                                    type="date"
                                    value={data.start_date}
                                    onChange={e => setData('start_date', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white h-10 text-xs"
                                />
                                <Input
                                    type="date"
                                    value={data.end_date}
                                    onChange={e => setData('end_date', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white h-10 text-xs"
                                />
                            </div>
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Currency</Label>
                            <select
                                value={data.currency_id}
                                onChange={e => setData('currency_id', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white rounded-lg px-3 py-2 text-sm h-10"
                            >
                                <option value="">All Currencies</option>
                                {currencies.map(c => <option key={c.id} value={c.id}>{c.code}</option>)}
                            </select>
                        </div>
                        <div className="flex items-center gap-2 pb-3">
                            <Checkbox
                                id="recurring_only"
                                checked={data.recurring_only}
                                onCheckedChange={(checked) => setData('recurring_only', checked as boolean)}
                            />
                            <Label htmlFor="recurring_only" className="text-xs font-bold text-white cursor-pointer">Recurring Only</Label>
                        </div>
                        <Button disabled={processing} className="bg-primary font-black text-white hover:bg-primary/90 h-10">
                            Apply Filters
                        </Button>
                    </form>
                </div>

                {/* Withdrawals List */}
                <div className="overflow-hidden rounded-2xl bg-[#2b1a1a] border border-[#3e2121] shadow-xl">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-[#3e2121] bg-[#1c0f0f]/50">
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Date</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Description</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Category</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Type</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4] text-right">Amount</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#3e2121]/30">
                            {withdrawals.data.length > 0 ? (
                                withdrawals.data.map((withdrawal) => (
                                    <tr key={withdrawal.id} className="group hover:bg-white/[0.02] transition-colors">
                                        <td className="px-6 py-4 text-sm font-medium text-[#e8b4b4] whitespace-nowrap">
                                            {new Date(withdrawal.withdrawal_date).toLocaleDateString()}
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex flex-col">
                                                <span className="text-sm font-bold text-white">{withdrawal.description}</span>
                                                {withdrawal.notes && <span className="text-xs text-gray-400 truncate max-w-[200px]">{withdrawal.notes}</span>}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            {withdrawal.category ? (
                                                <span
                                                    className="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide"
                                                    style={{ backgroundColor: `${withdrawal.category.color}20`, color: withdrawal.category.color }}
                                                >
                                                    {withdrawal.category.name}
                                                </span>
                                            ) : (
                                                <span className="text-xs text-gray-500">Uncategorized</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4">
                                            {withdrawal.is_recurring ? (
                                                <div className="flex items-center gap-1.5 text-orange-400">
                                                    <span className="material-symbols-outlined text-[16px]">autorenew</span>
                                                    <span className="text-xs font-bold capitalize">{withdrawal.recurrence_frequency}</span>
                                                </div>
                                            ) : (
                                                <span className="text-xs font-medium text-gray-500">One-time</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <span className="text-sm font-black text-rose-400">
                                                -{parseFloat(withdrawal.amount as any).toLocaleString(undefined, { minimumFractionDigits: 2 })} {withdrawal.currency?.symbol}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <Link
                                                    href={finance.withdrawals.destroy(withdrawal.id).url}
                                                    method="delete"
                                                    as="button"
                                                    className="p-1 text-rose-500 hover:text-rose-400 transition-colors"
                                                >
                                                    <span className="material-symbols-outlined text-[18px]">delete</span>
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={6} className="px-6 py-12 text-center text-[#e8b4b4]">
                                        <span className="material-symbols-outlined text-4xl mb-2 opacity-50">receipt_long</span>
                                        <p className="text-sm font-medium">No withdrawals found matching your filters.</p>
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
