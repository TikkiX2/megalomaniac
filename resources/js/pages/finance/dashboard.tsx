import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { Currency, Debt } from '@/types/finance';

interface Balance {
    currency: Currency;
    balance: number;
    total_income: number;
    total_expenses: number;
}

interface Transaction {
    id: number;
    type: 'income' | 'purchase' | 'withdrawal' | 'reserve_deposit' | 'reserve_withdrawal';
    description: string;
    amount: number;
    currency: Currency;
    date: string;
    category?: {
        name: string;
        color: string;
    };
}

interface Stats {
    total_purchases_this_month: number;
    total_incomes_this_month: number;
    total_withdrawals_this_month: number;
    active_debts_count: number;
    overdue_debts_count: number;
}

interface Props {
    balances: Balance[];
    pendingDebts: any[];
    recentTransactions: Transaction[];
    overdueDebts: Debt[];
    stats: Stats;
}

export default function FinanceDashboard({ balances, pendingDebts, recentTransactions, overdueDebts, stats }: Props) {
    return (
        <MainLayout>
            <Head title="Finance Dashboard" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                {/* Header Section */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                            Financial Overview</h2>
                        <p className="mt-1 text-base font-medium text-[#92c9a4]">
                            Master your money, control your destiny.
                        </p>
                    </div>
                    <div className="flex gap-3">
                        <Link
                            href={finance.currencyExchanges.create().url}
                            className="flex items-center gap-2 rounded-lg bg-[#102216] border border-[#23482f] px-4 py-2 text-sm font-bold text-[#92c9a4] transition hover:bg-white/10 active:scale-95">
                            <span className="material-symbols-outlined text-[20px]">currency_exchange</span>
                            New Exchange
                        </Link>
                        <Link
                            href={finance.purchases.create().url}
                            className="flex items-center gap-2 rounded-lg bg-[#23482f] border border-[#23482f] px-4 py-2 text-sm font-bold text-white transition hover:bg-white/10 active:scale-95">
                            <span className="material-symbols-outlined text-[20px]">add_shopping_cart</span>
                            New Purchase
                        </Link>
                        <Link
                            href={finance.incomes.create().url}
                            className="flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-black text-[#102216] shadow-[0_0_20px_rgba(19,236,91,0.25)] transition hover:bg-green-400 active:scale-95">
                            <span className="material-symbols-outlined font-bold" style={{ fontSize: '20px' }}>payments</span>
                            Add Income
                        </Link>
                    </div>
                </div>

                {/* Overdue Alert */}
                {overdueDebts.length > 0 && (
                    <div className="rounded-2xl bg-rose-500/10 border border-rose-500/20 p-4 flex items-center justify-between text-rose-400 animate-pulse">
                        <div className="flex items-center gap-3">
                            <span className="material-symbols-outlined font-bold">warning</span>
                            <span className="text-sm font-black uppercase tracking-widest">
                                You have {overdueDebts.length} overdue {overdueDebts.length === 1 ? 'debt' : 'debts'}!
                            </span>
                        </div>
                        <Link href={finance.debts.index().url} className="text-xs font-black underline uppercase tracking-tighter">View Debts</Link>
                    </div>
                )}

                {/* Primary Stats Grid */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                    {balances.map((item) => (
                        <div key={item.currency.id} className="rounded-2xl bg-[#193322] p-6 border border-[#23482f] shadow-lg relative overflow-hidden group">
                            <div className="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-primary/5 blur-2xl group-hover:bg-primary/10 transition-colors"></div>
                            <div className="flex flex-col gap-1 relative z-10">
                                <span className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Balance {item.currency.code}</span>
                                <div className="text-3xl font-black text-white">
                                    {item.currency.symbol} {item.balance.toLocaleString()}
                                </div>
                                <div className="mt-4 flex justify-between text-[10px] font-bold text-[#92c9a4] uppercase">
                                    <span>Income: {item.total_income.toLocaleString()}</span>
                                    <span>Spent: {item.total_expenses.toLocaleString()}</span>
                                </div>
                            </div>
                        </div>
                    ))}

                    {/* Active Debts Summary Widget */}
                    <div className="rounded-2xl bg-[#193322] p-6 border border-[#23482f] shadow-lg relative overflow-hidden group">
                        <div className="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-orange-500/5 blur-2xl group-hover:bg-orange-500/10 transition-colors"></div>
                        <div className="flex flex-col gap-1 relative z-10">
                            <span className="text-[10px] font-black uppercase tracking-widest text-orange-400">Active Debts</span>
                            <div className="text-3xl font-black text-white">
                                {stats.active_debts_count}
                            </div>
                            <div className="mt-4 text-[10px] font-bold text-[#92c9a4] uppercase">
                                {pendingDebts.length > 0 ? (
                                    pendingDebts.map(d => `${d.currency.symbol}${d.total.toLocaleString()}`).join(', ')
                                ) : 'No pending debts'}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Bento Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Recent Transactions */}
                    <div className="rounded-2xl bg-[#193322] border border-[#23482f] p-6 lg:col-span-2">
                        <div className="mb-6 flex items-center justify-between">
                            <h3 className="text-sm font-black uppercase tracking-widest text-white">Recent Transactions</h3>
                            <Link href={finance.purchases.index().url} className="text-xs font-bold text-primary hover:underline">View All</Link>
                        </div>
                        <div className="flex flex-col gap-4">
                            {recentTransactions.length > 0 ? (
                                recentTransactions.map((tx, idx) => (
                                    <div key={idx} className="flex items-center justify-between border-b border-[#23482f]/50 pb-4 last:border-0 last:pb-0">
                                        <div className="flex items-center gap-4">
                                            <div className={`flex h-10 w-10 items-center justify-center rounded-xl 
                                                ${tx.type === 'income' ? 'bg-primary/10 text-primary' :
                                                    tx.type === 'purchase' ? 'bg-rose-500/10 text-rose-500' :
                                                        tx.type === 'withdrawal' ? 'bg-orange-500/10 text-orange-500' :
                                                            'bg-blue-500/10 text-blue-500'
                                                }`}>
                                                <span className="material-symbols-outlined">
                                                    {tx.type === 'income' ? 'add_circle' :
                                                        tx.type === 'purchase' ? 'remove_circle' :
                                                            tx.type === 'withdrawal' ? 'receipt_long' :
                                                                'savings'}
                                                </span>
                                            </div>
                                            <div className="flex flex-col">
                                                <span className="text-sm font-bold text-white">{tx.description}</span>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-[10px] font-medium text-[#92c9a4]">{new Date(tx.date).toLocaleDateString()}</span>
                                                    {tx.category && (
                                                        <span
                                                            className="rounded-full px-1.5 py-0.5 text-[8px] font-black uppercase tracking-tighter"
                                                            style={{ backgroundColor: `${tx.category.color}20`, color: tx.category.color }}
                                                        >
                                                            {tx.category.name}
                                                        </span>
                                                    )}
                                                    {/* Show type tag for reserves */}
                                                    {(tx.type === 'reserve_deposit' || tx.type === 'reserve_withdrawal') && (
                                                        <span className="rounded-full bg-blue-500/20 px-1.5 py-0.5 text-[8px] font-black uppercase tracking-tighter text-blue-400">
                                                            Reserve
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                        <div className={`text-sm font-black 
                                            ${tx.type === 'income' || tx.type === 'reserve_withdrawal' ? 'text-primary' : 'text-rose-400'}
                                        `}>
                                            {tx.type === 'income' || tx.type === 'reserve_withdrawal' ? '+' : '-'}{tx.currency.symbol}{tx.amount.toLocaleString()}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="py-12 text-center text-[#92c9a4] italic font-bold text-sm uppercase">No transactions yet.</div>
                            )}
                        </div>
                    </div>

                    {/* Quick Stats & Quick Actions */}
                    <div className="flex flex-col gap-6">
                        <div className="rounded-2xl bg-[#193322] border border-[#23482f] p-6">
                            <h3 className="mb-6 text-sm font-black uppercase tracking-widest text-white">Monthly Stats</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="flex flex-col gap-1 rounded-xl bg-[#102216] p-4 border border-[#23482f]/50">
                                    <span className="text-[9px] font-black uppercase tracking-widest text-[#92c9a4]">Purchases</span>
                                    <span className="text-xl font-black text-white">{stats.total_purchases_this_month}</span>
                                </div>
                                <div className="flex flex-col gap-1 rounded-xl bg-[#102216] p-4 border border-[#23482f]/50">
                                    <span className="text-[9px] font-black uppercase tracking-widest text-[#92c9a4]">Incomes</span>
                                    <span className="text-xl font-black text-white">{stats.total_incomes_this_month}</span>
                                </div>
                                <div className="flex flex-col gap-1 rounded-xl bg-[#102216] p-4 border border-[#23482f]/50 col-span-2">
                                    <span className="text-[9px] font-black uppercase tracking-widest text-[#92c9a4]">Withdrawals</span>
                                    <span className="text-xl font-black text-white">{stats.total_withdrawals_this_month}</span>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-2xl bg-[#193322] border border-[#23482f] p-6">
                            <h3 className="mb-4 text-sm font-black uppercase tracking-widest text-white">Management</h3>
                            <div className="grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-1">
                                <Link href={finance.withdrawals.index().url} className="flex items-center gap-3 rounded-xl bg-[#102216] p-4 text-sm font-bold text-white hover:bg-[#23482f] transition-colors border border-[#23482f]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">receipt_long</span>
                                    Withdrawals & Services
                                </Link>
                                <Link href={finance.savingsReserves.index().url} className="flex items-center gap-3 rounded-xl bg-[#102216] p-4 text-sm font-bold text-white hover:bg-[#23482f] transition-colors border border-[#23482f]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">savings</span>
                                    Savings & Reserves
                                </Link>
                                <Link href={finance.creditCards.index().url} className="flex items-center gap-3 rounded-xl bg-[#102216] p-4 text-sm font-bold text-white hover:bg-[#23482f] transition-colors border border-[#23482f]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">credit_card</span>
                                    Credit Cards
                                </Link>
                                <Link href={finance.currencies.index().url} className="flex items-center gap-3 rounded-xl bg-[#102216] p-4 text-sm font-bold text-white hover:bg-[#23482f] transition-colors border border-[#23482f]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">currency_exchange</span>
                                    Currencies & Rates
                                </Link>
                                <Link href={finance.categories.index().url} className="flex items-center gap-3 rounded-xl bg-[#102216] p-4 text-sm font-bold text-white hover:bg-[#23482f] transition-colors border border-[#23482f]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">category</span>
                                    Purchase Categories
                                </Link>
                                <Link href={finance.incomeSources.index().url} className="flex items-center gap-3 rounded-xl bg-[#102216] p-4 text-sm font-bold text-white hover:bg-[#23482f] transition-colors border border-[#23482f]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">source</span>
                                    Income Sources
                                </Link>
                                <Link href={finance.debts.index().url} className="flex items-center gap-3 rounded-xl bg-[#102216] p-4 text-sm font-bold text-white hover:bg-[#23482f] transition-colors border border-[#23482f]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">account_balance_wallet</span>
                                    Debts Tracking
                                </Link>
                                <Link href={finance.statistics().url} className="flex items-center gap-3 rounded-xl bg-[#102216] p-4 text-sm font-bold text-white hover:bg-[#23482f] transition-colors border border-[#23482f]/30 md:col-span-2 lg:col-span-1">
                                    <span className="material-symbols-outlined text-primary">analytics</span>
                                    Full Statistics
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
