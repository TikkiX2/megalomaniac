import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { SavingsReserve, Currency } from '@/types/finance';

interface Props {
    reserves: SavingsReserve[];
    currencies: Currency[];
}

export default function SavingsReservesIndex({ reserves, currencies }: Props) {
    return (
        <MainLayout>
            <Head title="Savings & Reserves" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                            Savings & Reserves</h2>
                        <p className="mt-1 text-base font-medium text-[#e8b4b4]">
                            Track your savings goals, emergency funds, and future plans.
                        </p>
                    </div>
                    <Link
                        href={finance.savingsReserves.create().url}
                        className="flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-black text-white shadow-[0_0_20px_rgba(239,68,68,0.25)] transition hover:bg-primary/90 active:scale-95">
                        <span className="material-symbols-outlined font-bold" style={{ fontSize: '20px' }}>add</span>
                        New Reserve
                    </Link>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {reserves.map((reserve) => (
                        <Link
                            key={reserve.id}
                            href={finance.savingsReserves.show(reserve.id).url}
                            className="group relative flex flex-col gap-4 rounded-3xl border border-[#3e2121] bg-[#2b1a1a] p-6 shadow-xl transition-all hover:border-primary/50 hover:-translate-y-1 hover:shadow-2xl overflow-hidden"
                        >
                            {!reserve.is_active && (
                                <div className="absolute top-4 right-4 rounded-full bg-gray-800 px-2 py-0.5 text-[10px] font-bold text-gray-400 uppercase tracking-wide z-10">
                                    Archived
                                </div>
                            )}

                            <div className="flex items-start justify-between">
                                <div className="flex items-center gap-4">
                                    <div
                                        className="flex h-12 w-12 items-center justify-center rounded-2xl shadow-lg transition-transform group-hover:scale-110"
                                        style={{ backgroundColor: reserve.color, color: '#1c0f0f' }}
                                    >
                                        <span className="material-symbols-outlined text-[24px] font-bold">
                                            {reserve.icon || 'savings'}
                                        </span>
                                    </div>
                                    <div className="flex flex-col">
                                        <h3 className="text-xl font-black text-white leading-tight group-hover:text-primary transition-colors">{reserve.name}</h3>
                                        <span className="text-xs font-bold text-[#e8b4b4] uppercase tracking-wider">{reserve.currency?.code}</span>
                                    </div>
                                </div>
                            </div>

                            <div className="flex flex-col gap-1 mt-2">
                                <span className="text-3xl font-black text-white tracking-tight">
                                    {parseFloat(reserve.current_amount as any).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                    <span className="text-lg text-[#e8b4b4] ml-1">{reserve.currency?.symbol}</span>
                                </span>
                                {reserve.goal_amount && (
                                    <span className="text-xs font-medium text-gray-400">
                                        of {parseFloat(reserve.goal_amount as any).toLocaleString()} {reserve.currency?.symbol} goal
                                    </span>
                                )}
                            </div>

                            {reserve.goal_amount && (
                                <div className="flex flex-col gap-2 mt-auto">
                                    <div className="flex justify-between text-[10px] font-bold uppercase tracking-widest">
                                        <span className="text-[#e8b4b4]">Progress</span>
                                        <span className="text-white">{reserve.progress}%</span>
                                    </div>
                                    <div className="h-2 w-full rounded-full bg-[#1c0f0f]">
                                        <div
                                            className="h-full rounded-full transition-all duration-1000 ease-out"
                                            style={{
                                                width: `${Math.min(100, reserve.progress || 0)}%`,
                                                backgroundColor: reserve.color,
                                                boxShadow: `0 0 10px ${reserve.color}40`
                                            }}
                                        />
                                    </div>
                                </div>
                            )}

                            {reserve.target_date && (
                                <div className="mt-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest flex items-center gap-1">
                                    <span className="material-symbols-outlined text-[14px]">event</span>
                                    Target: {new Date(reserve.target_date).toLocaleDateString()}
                                </div>
                            )}
                        </Link>
                    ))}

                    {reserves.length === 0 && (
                        <div className="col-span-full flex flex-col items-center justify-center rounded-3xl border border-dashed border-[#3e2121] p-12 text-center">
                            <div className="mb-4 rounded-full bg-[#2b1a1a] p-4 text-[#e8b4b4]">
                                <span className="material-symbols-outlined text-4xl">savings</span>
                            </div>
                            <h3 className="text-lg font-bold text-white">No Savings Reserves Yet</h3>
                            <p className="mt-2 text-sm text-gray-400 max-w-sm">
                                Create a reserve to start tracking your savings goals, emergency funds, or specialized accounts.
                            </p>
                            <Link
                                href={finance.savingsReserves.create().url}
                                className="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-black text-white hover:bg-primary/90 transition"
                            >
                                Get Started
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </MainLayout>
    );
}
