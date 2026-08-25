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

interface MonthlyBudget {
    spent: number;
    limit: number;
    percent: number;
    remaining: number;
    count: number;
    currency: Currency | null;
}

interface SavingsGoal {
    id: number;
    name: string;
    current_amount: number | string;
    goal_amount: number | string | null;
    progress: number;
    currency?: Currency;
    color: string;
    icon?: string | null;
    target_date?: string | null;
    is_active?: boolean;
}

interface Props {
    balances: Balance[];
    pendingDebts: any[];
    recentTransactions: Transaction[];
    overdueDebts: Debt[];
    stats: Stats;
    monthlyBudget: MonthlyBudget;
    savingsGoals: SavingsGoal[];
}

export default function FinanceDashboard({ balances, pendingDebts, recentTransactions, overdueDebts, stats, monthlyBudget, savingsGoals }: Props) {
    return (
        <MainLayout>
            <Head title="Finance Dashboard" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                {/* Header Section */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                            Financial Overview</h2>
                        <p className="mt-1 text-base font-medium text-[#e8b4b4]">
                            Master your money, control your destiny.
                        </p>
                    </div>
                    <div className="flex gap-3">
                        <Link
                            href={finance.currencyExchanges.create().url}
                            className="flex items-center gap-2 rounded-lg bg-[#1c0f0f] border border-[#3e2121] px-4 py-2 text-sm font-bold text-[#e8b4b4] transition hover:bg-white/10 active:scale-95">
                            <span className="material-symbols-outlined text-[20px]">currency_exchange</span>
                            New Exchange
                        </Link>
                        <Link
                            href={finance.purchases.create().url}
                            className="flex items-center gap-2 rounded-lg bg-[#3e2121] border border-[#3e2121] px-4 py-2 text-sm font-bold text-white transition hover:bg-white/10 active:scale-95">
                            <span className="material-symbols-outlined text-[20px]">add_shopping_cart</span>
                            New Purchase
                        </Link>
                        <Link
                            href={finance.incomes.create().url}
                            className="flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-black text-white shadow-[0_0_20px_rgba(239,68,68,0.25)] transition hover:bg-primary/90 active:scale-95">
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
                        <div key={item.currency.id} className="rounded-2xl bg-[#2b1a1a] p-6 border border-[#3e2121] shadow-lg relative overflow-hidden group">
                            <div className="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-primary/5 blur-2xl group-hover:bg-primary/10 transition-colors"></div>
                            <div className="flex flex-col gap-1 relative z-10">
                                <span className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Balance {item.currency.code}</span>
                                <div className="text-3xl font-black text-white">
                                    {item.currency.symbol} {item.balance.toLocaleString()}
                                </div>
                                <div className="mt-4 flex justify-between text-[10px] font-bold text-[#e8b4b4] uppercase">
                                    <span>Income: {item.total_income.toLocaleString()}</span>
                                    <span>Spent: {item.total_expenses.toLocaleString()}</span>
                                </div>
                            </div>
                        </div>
                    ))}

                    {/* Active Debts Summary Widget */}
                    <div className="rounded-2xl bg-[#2b1a1a] p-6 border border-[#3e2121] shadow-lg relative overflow-hidden group">
                        <div className="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-orange-500/5 blur-2xl group-hover:bg-orange-500/10 transition-colors"></div>
                        <div className="flex flex-col gap-1 relative z-10">
                            <span className="text-[10px] font-black uppercase tracking-widest text-orange-400">Active Debts</span>
                            <div className="text-3xl font-black text-white">
                                {stats.active_debts_count}
                            </div>
                            <div className="mt-4 text-[10px] font-bold text-[#e8b4b4] uppercase">
                                {pendingDebts.length > 0 ? (
                                    pendingDebts.map(d => `${d.currency.symbol}${d.total.toLocaleString()}`).join(', ')
                                ) : 'No pending debts'}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Finance Core Avanzado — Budget + Savings Goals (rojizo #EF4444) */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Budget Mensual */}
                    <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6 flex flex-col gap-5 shadow-lg relative overflow-hidden">
                        <div className="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-primary/5 blur-2xl pointer-events-none"></div>
                        <div className="flex items-center justify-between relative z-10">
                            <div className="flex items-center gap-3">
                                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/15 border border-primary/20 text-primary">
                                    <span className="material-symbols-outlined text-[20px] font-bold">account_balance_wallet</span>
                                </div>
                                <h3 className="text-sm font-black uppercase tracking-widest text-white">Budget Mensual</h3>
                            </div>
                            {monthlyBudget && monthlyBudget.count > 0 ? (
                                <span
                                    className={`rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-widest border ${
                                        monthlyBudget.percent >= 100
                                            ? 'bg-rose-500/15 text-rose-400 border-rose-500/20'
                                            : monthlyBudget.percent >= 80
                                              ? 'bg-orange-500/15 text-orange-400 border-orange-500/20'
                                              : 'bg-primary/10 text-primary border-primary/20'
                                    }`}
                                >
                                    {monthlyBudget.percent >= 100 ? 'Excedido' : monthlyBudget.percent >= 80 ? '¡Cuidado!' : 'En control'}
                                </span>
                            ) : (
                                <span className="rounded-full bg-white/5 border border-white/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">
                                    {new Date().toLocaleDateString('es-ES', { month: 'long' })}
                                </span>
                            )}
                        </div>

                        {monthlyBudget ? (
                            <>
                                <div className="flex items-baseline gap-2 relative z-10">
                                    <span className="text-3xl font-black tracking-tight text-white">
                                        {monthlyBudget.currency?.symbol ?? '$'}
                                        {Number(monthlyBudget.spent).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </span>
                                    <span className="text-sm font-bold text-[#e8b4b4]">
                                        / {monthlyBudget.currency?.symbol ?? '$'}
                                        {Number(monthlyBudget.limit).toLocaleString(undefined, { minimumFractionDigits: 0 })}
                                    </span>
                                    <span className="ml-auto text-[11px] font-black uppercase tracking-widest text-[#e8b4b4]">
                                        {monthlyBudget.count} {monthlyBudget.count === 1 ? 'compra' : 'compras'}
                                    </span>
                                </div>

                                <div className="flex flex-col gap-2 relative z-10">
                                    <div className="h-2.5 w-full rounded-full bg-[#1c0f0f] border border-[#3e2121]/50 overflow-hidden p-0.5">
                                        <div
                                            className={`h-full rounded-full transition-all duration-700 ease-out shadow-[0_0_10px_rgba(239,68,68,0.35)] ${monthlyBudget.percent >= 100 ? 'bg-rose-500' : monthlyBudget.percent >= 80 ? 'bg-orange-500' : 'bg-primary'}`}
                                            style={{ width: `${Math.min(100, monthlyBudget.percent)}%` }}
                                        />
                                    </div>
                                    <div className="flex justify-between text-[11px] font-bold">
                                        <span className={monthlyBudget.percent >= 100 ? 'text-rose-400' : monthlyBudget.percent >= 80 ? 'text-orange-400' : 'text-primary'}>
                                            {monthlyBudget.percent}% usado
                                        </span>
                                        <span className={monthlyBudget.remaining >= 0 ? 'text-[#e8b4b4]' : 'text-rose-400'}>
                                            {monthlyBudget.remaining >= 0
                                                ? `${monthlyBudget.currency?.symbol ?? '$'}${Math.abs(Number(monthlyBudget.remaining)).toLocaleString(undefined, { minimumFractionDigits: 2 })} restante`
                                                : `${monthlyBudget.currency?.symbol ?? '$'}${Math.abs(Number(monthlyBudget.remaining)).toLocaleString(undefined, { minimumFractionDigits: 2 })} excedido`}
                                        </span>
                                    </div>
                                </div>

                                <p className="text-xs font-medium text-[#e8b4b4] relative z-10">
                                    Meta mensual <span className="font-black text-white">{monthlyBudget.currency?.symbol ?? '$'}1.000</span> •{' '}
                                    {monthlyBudget.percent < 80 && 'Vas bien, sigue controlando tus gastos.'}
                                    {monthlyBudget.percent >= 80 && monthlyBudget.percent < 100 && 'Casi llegas al límite — revisa tus compras.'}
                                    {monthlyBudget.percent >= 100 && 'Has superado el presupuesto este mes.'}
                                </p>

                                {monthlyBudget.count === 0 && (
                                    <div className="rounded-xl bg-[#1c0f0f] border border-dashed border-[#3e2121] p-4 flex flex-col items-center gap-3 text-center relative z-10">
                                        <p className="text-xs font-bold text-[#e8b4b4]">Aún no hay compras este mes</p>
                                        <Link
                                            href={finance.purchases.create().url}
                                            className="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-black text-white shadow-[0_0_20px_rgba(239,68,68,0.3)] transition hover:bg-primary/90 active:scale-95"
                                        >
                                            <span className="material-symbols-outlined text-[18px]">add_circle</span>
                                            Crear presupuesto
                                        </Link>
                                    </div>
                                )}
                            </>
                        ) : (
                            <div className="rounded-xl bg-[#1c0f0f] border border-dashed border-[#3e2121] p-6 flex flex-col items-center gap-3 text-center relative z-10">
                                <div className="rounded-full bg-primary/10 border border-primary/20 p-3 text-primary">
                                    <span className="material-symbols-outlined text-2xl">pie_chart</span>
                                </div>
                                <p className="text-sm font-black text-white">Sin presupuesto aún</p>
                                <p className="text-xs font-medium text-[#e8b4b4]">Define tu meta mensual de $1000 y empieza a trackear.</p>
                                <Link
                                    href={finance.purchases.create().url}
                                    className="mt-1 inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-black text-white shadow-[0_0_20px_rgba(239,68,68,0.3)] transition hover:bg-primary/90 active:scale-95"
                                >
                                    <span className="material-symbols-outlined text-[18px]">add_circle</span>
                                    Crear presupuesto
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* Savings Goals */}
                    <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6 flex flex-col gap-5 shadow-lg relative overflow-hidden">
                        <div className="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-primary/5 blur-2xl pointer-events-none"></div>
                        <div className="flex items-center justify-between relative z-10">
                            <div className="flex items-center gap-3">
                                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/15 border border-primary/20 text-primary">
                                    <span className="material-symbols-outlined text-[20px] font-bold">savings</span>
                                </div>
                                <h3 className="text-sm font-black uppercase tracking-widest text-white">Savings Goals</h3>
                            </div>
                            <Link href={finance.savingsReserves.index().url} className="text-xs font-bold text-primary hover:underline">
                                Ver todo
                            </Link>
                        </div>

                        {savingsGoals && savingsGoals.length > 0 ? (
                            <div className="flex flex-col gap-3 relative z-10">
                                {savingsGoals.slice(0, 3).map((goal) => {
                                    const current = parseFloat(String(goal.current_amount ?? 0));
                                    const target = goal.goal_amount ? parseFloat(String(goal.goal_amount)) : null;
                                    const progress = target ? Math.min(100, Number(goal.progress ?? 0)) : 0;
                                    const pctLabel = target ? `${progress}%` : 'Sin meta';
                                    return (
                                        <div key={goal.id} className="rounded-xl bg-[#1c0f0f] border border-[#3e2121]/50 p-4 flex flex-col gap-3 hover:border-[#3e2121] transition-colors">
                                            <div className="flex items-center justify-between gap-2">
                                                <div className="flex items-center gap-3 min-w-0">
                                                    <div
                                                        className="flex h-8 w-8 items-center justify-center rounded-lg shrink-0 shadow-sm"
                                                        style={{ backgroundColor: goal.color || '#EF4444' }}
                                                    >
                                                        <span className="material-symbols-outlined text-white text-[18px] font-bold">{goal.icon || 'savings'}</span>
                                                    </div>
                                                    <span className="text-sm font-black text-white truncate">{goal.name}</span>
                                                </div>
                                                <span className="text-xs font-bold text-[#e8b4b4] shrink-0">
                                                    {goal.currency?.symbol ?? '$'}
                                                    {current.toLocaleString(undefined, { minimumFractionDigits: 2 })} {target ? `/ ${goal.currency?.symbol ?? '$'}${target.toLocaleString(undefined, { minimumFractionDigits: 0 })}` : ''}
                                                </span>
                                            </div>
                                            {target ? (
                                                <>
                                                    <div className="h-2 w-full rounded-full bg-[#2b1a1a] border border-[#3e2121]/30 overflow-hidden p-0.5">
                                                        <div
                                                            className="h-full rounded-full bg-primary transition-all duration-700 ease-out shadow-[0_0_8px_rgba(239,68,68,0.35)]"
                                                            style={{ width: `${progress}%` }}
                                                        />
                                                    </div>
                                                    <div className="flex justify-between text-[10px] font-black uppercase tracking-widest">
                                                        <span className="text-primary">{pctLabel}</span>
                                                        <span className="text-[#e8b4b4]">{goal.currency?.code ?? ''}</span>
                                                    </div>
                                                </>
                                            ) : (
                                                <p className="text-[11px] font-bold text-[#e8b4b4]">Sin meta definida — <Link href={finance.savingsReserves.show(goal.id).url} className="text-primary hover:underline">definir meta</Link></p>
                                            )}
                                        </div>
                                    );
                                })}
                                <Link
                                    href={finance.savingsReserves.create().url}
                                    className="mt-1 inline-flex items-center justify-center gap-2 rounded-xl border border-dashed border-[#3e2121] bg-[#1c0f0f]/50 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-[#e8b4b4] transition hover:border-primary/30 hover:text-primary hover:bg-primary/5"
                                >
                                    <span className="material-symbols-outlined text-[16px]">add</span>
                                    Nueva meta
                                </Link>
                            </div>
                        ) : (
                            <div className="rounded-xl bg-[#1c0f0f] border border-dashed border-[#3e2121] p-6 flex flex-col items-center gap-3 text-center relative z-10">
                                <div className="rounded-full bg-[#2b1a1a] border border-[#3e2121] p-3 text-[#e8b4b4]">
                                    <span className="material-symbols-outlined text-2xl">savings</span>
                                </div>
                                <p className="text-sm font-black text-white">Sin metas aún</p>
                                <p className="text-xs font-medium leading-relaxed text-[#e8b4b4] max-w-[260px]">
                                    Crea tu primera reserva para empezar a ahorrar — fondo emergencia, viaje, inversión.
                                </p>
                                <Link
                                    href={finance.savingsReserves.create().url}
                                    className="mt-1 inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-black text-white shadow-[0_0_20px_rgba(239,68,68,0.3)] transition hover:bg-primary/90 active:scale-95"
                                >
                                    <span className="material-symbols-outlined text-[18px]">add_circle</span>
                                    Crear presupuesto
                                </Link>
                            </div>
                        )}
                    </div>
                </div>

                {/* Bento Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Recent Transactions */}
                    <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6 lg:col-span-2">
                        <div className="mb-6 flex items-center justify-between">
                            <h3 className="text-sm font-black uppercase tracking-widest text-white">Recent Transactions</h3>
                            <Link href={finance.purchases.index().url} className="text-xs font-bold text-primary hover:underline">View All</Link>
                        </div>
                        <div className="flex flex-col gap-4">
                            {recentTransactions.length > 0 ? (
                                recentTransactions.map((tx, idx) => (
                                    <div key={idx} className="flex items-center justify-between border-b border-[#3e2121]/50 pb-4 last:border-0 last:pb-0">
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
                                                    <span className="text-[10px] font-medium text-[#e8b4b4]">{new Date(tx.date).toLocaleDateString()}</span>
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
                                <div className="py-12 text-center text-[#e8b4b4] italic font-bold text-sm uppercase">No transactions yet.</div>
                            )}
                        </div>
                    </div>

                    {/* Quick Stats & Quick Actions */}
                    <div className="flex flex-col gap-6">
                        <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6">
                            <h3 className="mb-6 text-sm font-black uppercase tracking-widest text-white">Monthly Stats</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="flex flex-col gap-1 rounded-xl bg-[#1c0f0f] p-4 border border-[#3e2121]/50">
                                    <span className="text-[9px] font-black uppercase tracking-widest text-[#e8b4b4]">Purchases</span>
                                    <span className="text-xl font-black text-white">{stats.total_purchases_this_month}</span>
                                </div>
                                <div className="flex flex-col gap-1 rounded-xl bg-[#1c0f0f] p-4 border border-[#3e2121]/50">
                                    <span className="text-[9px] font-black uppercase tracking-widest text-[#e8b4b4]">Incomes</span>
                                    <span className="text-xl font-black text-white">{stats.total_incomes_this_month}</span>
                                </div>
                                <div className="flex flex-col gap-1 rounded-xl bg-[#1c0f0f] p-4 border border-[#3e2121]/50 col-span-2">
                                    <span className="text-[9px] font-black uppercase tracking-widest text-[#e8b4b4]">Withdrawals</span>
                                    <span className="text-xl font-black text-white">{stats.total_withdrawals_this_month}</span>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6">
                            <h3 className="mb-4 text-sm font-black uppercase tracking-widest text-white">Management</h3>
                            <div className="grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-1">
                                <Link href={finance.withdrawals.index().url} className="flex items-center gap-3 rounded-xl bg-[#1c0f0f] p-4 text-sm font-bold text-white hover:bg-[#3e2121] transition-colors border border-[#3e2121]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">receipt_long</span>
                                    Withdrawals & Services
                                </Link>
                                <Link href={finance.savingsReserves.index().url} className="flex items-center gap-3 rounded-xl bg-[#1c0f0f] p-4 text-sm font-bold text-white hover:bg-[#3e2121] transition-colors border border-[#3e2121]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">savings</span>
                                    Savings & Reserves
                                </Link>
                                <Link href={finance.creditCards.index().url} className="flex items-center gap-3 rounded-xl bg-[#1c0f0f] p-4 text-sm font-bold text-white hover:bg-[#3e2121] transition-colors border border-[#3e2121]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">credit_card</span>
                                    Credit Cards
                                </Link>
                                <Link href={finance.currencies.index().url} className="flex items-center gap-3 rounded-xl bg-[#1c0f0f] p-4 text-sm font-bold text-white hover:bg-[#3e2121] transition-colors border border-[#3e2121]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">currency_exchange</span>
                                    Currencies & Rates
                                </Link>
                                <Link href={finance.categories.index().url} className="flex items-center gap-3 rounded-xl bg-[#1c0f0f] p-4 text-sm font-bold text-white hover:bg-[#3e2121] transition-colors border border-[#3e2121]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">category</span>
                                    Purchase Categories
                                </Link>
                                <Link href={finance.incomeSources.index().url} className="flex items-center gap-3 rounded-xl bg-[#1c0f0f] p-4 text-sm font-bold text-white hover:bg-[#3e2121] transition-colors border border-[#3e2121]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">source</span>
                                    Income Sources
                                </Link>
                                <Link href={finance.debts.index().url} className="flex items-center gap-3 rounded-xl bg-[#1c0f0f] p-4 text-sm font-bold text-white hover:bg-[#3e2121] transition-colors border border-[#3e2121]/30 col-span-1">
                                    <span className="material-symbols-outlined text-primary">account_balance_wallet</span>
                                    Debts Tracking
                                </Link>
                                <Link href={finance.statistics().url} className="flex items-center gap-3 rounded-xl bg-[#1c0f0f] p-4 text-sm font-bold text-white hover:bg-[#3e2121] transition-colors border border-[#3e2121]/30 md:col-span-2 lg:col-span-1">
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
