import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { SavingsReserve } from '@/types/finance';

interface Props {
    reserve: SavingsReserve;
}

export default function SavingsReserveShow({ reserve }: Props) {
    const [actionType, setActionType] = useState<'deposit' | 'withdraw' | null>(null);

    const { data, setData, post, processing, reset, errors } = useForm({
        amount: '',
        date: new Date().toISOString().split('T')[0],
        description: '',
    });

    const handleTransaction = (e: React.FormEvent) => {
        e.preventDefault();
        if (!actionType) return;

        const route = actionType === 'deposit'
            ? finance.savingsReserves.deposit(reserve.id)
            : finance.savingsReserves.withdraw(reserve.id);

        post(route.url, {
            onSuccess: () => {
                reset();
                setActionType(null);
            },
        });
    };

    return (
        <MainLayout>
            <Head title={reserve.name} />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                <div className="flex items-center gap-4">
                    <Link href={finance.savingsReserves.index().url} className="rounded-full p-2 hover:bg-white/5 text-[#e8b4b4] transition-all">
                        <span className="material-symbols-outlined">arrow_back</span>
                    </Link>
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white">{reserve.name}</h2>
                        <p className="text-sm font-medium text-[#e8b4b4]">{reserve.description || 'Savings Reserve Details'}</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    {/* Main Stats Card */}
                    <div className="lg:col-span-2 flex flex-col gap-8">
                        <div
                            className="relative overflow-hidden rounded-3xl border border-[#3e2121] bg-[#2b1a1a] p-8 shadow-2xl"
                        >
                            <div className="flex items-start justify-between">
                                <span className="text-sm font-black uppercase tracking-widest text-[#e8b4b4]">Current Balance</span>
                                <div
                                    className="h-12 w-12 rounded-xl flex items-center justify-center shadow-lg"
                                    style={{ backgroundColor: reserve.color }}
                                >
                                    <span className="material-symbols-outlined text-white text-2xl font-bold">{reserve.icon || 'savings'}</span>
                                </div>
                            </div>

                            <div className="mt-4 flex items-baseline gap-2">
                                <h3 className="text-5xl font-black text-white tracking-tight">
                                    {parseFloat(reserve.current_amount as any).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                </h3>
                                <span className="text-2xl font-bold text-[#e8b4b4]">{reserve.currency?.code}</span>
                            </div>

                            {reserve.goal_amount && (
                                <div className="mt-8 flex flex-col gap-3">
                                    <div className="flex justify-between text-xs font-bold uppercase tracking-widest">
                                        <span className="text-[#e8b4b4]">
                                            Goal: {parseFloat(reserve.goal_amount as any).toLocaleString()} {reserve.currency?.symbol}
                                        </span>
                                        <span className="text-white">{reserve.progress}% achieved</span>
                                    </div>
                                    <div className="h-4 w-full rounded-full bg-[#1c0f0f] overflow-hidden">
                                        <div
                                            className="h-full transition-all duration-1000 ease-out relative"
                                            style={{
                                                width: `${Math.min(100, reserve.progress || 0)}%`,
                                                backgroundColor: reserve.color,
                                            }}
                                        >
                                            <div className="absolute inset-0 bg-white/20 animate-[shimmer_2s_infinite]"></div>
                                        </div>
                                    </div>
                                    {reserve.target_date && (
                                        <p className="text-xs text-center text-gray-500 font-medium">
                                            Target Date: {new Date(reserve.target_date).toLocaleDateString()}
                                        </p>
                                    )}
                                </div>
                            )}

                            {/* Action Buttons */}
                            <div className="mt-8 flex gap-4">
                                <Button
                                    onClick={() => setActionType(actionType === 'deposit' ? null : 'deposit')}
                                    className="flex-1 bg-primary text-white font-black hover:bg-primary/90 h-12"
                                >
                                    <span className="material-symbols-outlined mr-2">add</span>
                                    Deposit
                                </Button>
                                <Button
                                    onClick={() => setActionType(actionType === 'withdraw' ? null : 'withdraw')}
                                    className="flex-1 bg-[#1c0f0f] text-white border border-[#3e2121] font-bold hover:bg-white/5 h-12"
                                >
                                    <span className="material-symbols-outlined mr-2">remove</span>
                                    Withdraw
                                </Button>
                            </div>

                            {/* Transaction Form Panel */}
                            {actionType && (
                                <div className="mt-6 rounded-xl bg-[#1c0f0f]/50 p-6 border border-[#3e2121] animate-in slide-in-from-top-4">
                                    <h4 className="text-sm font-black uppercase tracking-widest text-white mb-4">
                                        {actionType === 'deposit' ? 'Make a Deposit' : 'Make a Withdrawal'}
                                    </h4>
                                    <form onSubmit={handleTransaction} className="flex flex-col gap-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="flex flex-col gap-1.5">
                                                <Label className="text-[10px] text-[#e8b4b4] uppercase font-bold">Amount</Label>
                                                <Input
                                                    type="number" step="0.01"
                                                    value={data.amount} onChange={e => setData('amount', e.target.value)}
                                                    required className="bg-[#1c0f0f] border-[#3e2121] text-white"
                                                    autoFocus
                                                />
                                            </div>
                                            <div className="flex flex-col gap-1.5">
                                                <Label className="text-[10px] text-[#e8b4b4] uppercase font-bold">Date</Label>
                                                <Input
                                                    type="date"
                                                    value={data.date} onChange={e => setData('date', e.target.value)}
                                                    required className="bg-[#1c0f0f] border-[#3e2121] text-white"
                                                />
                                            </div>
                                        </div>
                                        <div className="flex flex-col gap-1.5">
                                            <Label className="text-[10px] text-[#e8b4b4] uppercase font-bold">Description (Optional)</Label>
                                            <Input
                                                value={data.description} onChange={e => setData('description', e.target.value)}
                                                className="bg-[#1c0f0f] border-[#3e2121] text-white"
                                                placeholder={actionType === 'deposit' ? 'Paycheck allocation...' : 'Emergency expense...'}
                                            />
                                        </div>
                                        <div className="flex justify-end gap-2 mt-2">
                                            <Button type="button" variant="ghost" onClick={() => setActionType(null)} className="text-gray-400">Cancel</Button>
                                            <Button disabled={processing} className="bg-white text-black font-bold hover:bg-gray-200">
                                                Confirm {actionType === 'deposit' ? 'Deposit' : 'Withdrawal'}
                                            </Button>
                                        </div>
                                    </form>
                                </div>
                            )}
                        </div>

                        {/* Transactions History */}
                        <div className="rounded-3xl border border-[#3e2121] bg-[#2b1a1a] p-8 shadow-xl">
                            <h3 className="mb-6 text-lg font-black text-white">Transaction History</h3>
                            <div className="flex flex-col gap-4">
                                {reserve.transactions && reserve.transactions.length > 0 ? (
                                    reserve.transactions.map((tx) => (
                                        <div key={tx.id} className="flex items-center justify-between border-b border-[#3e2121]/50 pb-4 last:border-0 last:pb-0">
                                            <div className="flex items-center gap-4">
                                                <div className={`flex h-10 w-10 items-center justify-center rounded-full ${tx.transaction_type === 'deposit' ? 'bg-primary/10 text-primary' : 'bg-rose-500/10 text-rose-500'}`}>
                                                    <span className="material-symbols-outlined text-[20px]">
                                                        {tx.transaction_type === 'deposit' ? 'arrow_downward' : 'arrow_upward'}
                                                    </span>
                                                </div>
                                                <div className="flex flex-col">
                                                    <span className="text-sm font-bold text-white">{tx.description || (tx.transaction_type === 'deposit' ? 'Deposit' : 'Withdrawal')}</span>
                                                    <span className="text-xs font-medium text-gray-400">{new Date(tx.transaction_date).toLocaleDateString()}</span>
                                                </div>
                                            </div>
                                            <span className={`text-base font-black ${tx.transaction_type === 'deposit' ? 'text-primary' : 'text-rose-500'}`}>
                                                {tx.transaction_type === 'deposit' ? '+' : '-'}{parseFloat(tx.amount as any).toLocaleString()}
                                            </span>
                                        </div>
                                    ))
                                ) : (
                                    <div className="py-8 text-center text-gray-500 text-sm">No transactions yet.</div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Sidebar / Actions */}
                    <div className="flex flex-col gap-6">
                        <div className="rounded-2xl border border-[#3e2121] bg-[#2b1a1a] p-6">
                            <h4 className="mb-4 text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Quick Actions</h4>
                            <div className="flex flex-col gap-2">
                                <Link
                                    href={finance.savingsReserves.edit(reserve.id).url}
                                    className="flex w-full items-center gap-3 rounded-lg bg-[#1c0f0f] px-4 py-3 text-sm font-bold text-gray-300 hover:bg-[#1c0f0f]/80 hover:text-white transition"
                                >
                                    <span className="material-symbols-outlined text-[20px]">edit</span>
                                    Edit Details
                                </Link>
                                <Link
                                    href={finance.savingsReserves.destroy(reserve.id).url}
                                    method="delete"
                                    as="button"
                                    className="flex w-full items-center gap-3 rounded-lg bg-[#1c0f0f] px-4 py-3 text-sm font-bold text-rose-500 hover:bg-rose-950/20 hover:text-rose-400 transition"
                                >
                                    <span className="material-symbols-outlined text-[20px]">delete</span>
                                    Delete Reserve
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
