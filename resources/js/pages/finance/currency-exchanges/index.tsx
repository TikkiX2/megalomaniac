import { Head, Link, useForm } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { CurrencyExchange } from '@/types/finance';
import { Button } from '@/components/ui/button';

interface Props {
    exchanges: {
        data: CurrencyExchange[];
        links: any[];
    };
}

export default function ExchangeIndex({ exchanges }: Props) {
    const { delete: destroy } = useForm();

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this exchange? This will also delete the associated income and withdrawal records.')) {
            destroy(finance.currencyExchanges.destroy(id).url);
        }
    };

    return (
        <MainLayout>
            <Head title="Currency Exchanges" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl uppercase">Currency Exchanges</h2>
                        <p className="mt-1 text-base font-medium text-[#e8b4b4]">History of your currency swaps.</p>
                    </div>
                    <Link
                        href={finance.currencyExchanges.create().url}
                        className="flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-black text-white shadow-[0_0_20px_rgba(239,68,68,0.25)] transition hover:bg-primary/90 active:scale-95"
                    >
                        <span className="material-symbols-outlined font-bold" style={{ fontSize: '20px' }}>currency_exchange</span>
                        New Exchange
                    </Link>
                </div>

                <div className="overflow-hidden rounded-2xl border border-[#3e2121] bg-[#2b1a1a]">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-[#3e2121] bg-[#1c0f0f]/50">
                                    <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Date</th>
                                    <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-rose-400">Sold (Egreso)</th>
                                    <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-primary">Bought (Ingreso)</th>
                                    <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Rate</th>
                                    <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Notes</th>
                                    <th className="px-6 py-4 text-right text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#3e2121]/50">
                                {exchanges.data.length > 0 ? (
                                    exchanges.data.map((ex) => (
                                        <tr key={ex.id} className="hover:bg-white/5 transition-colors">
                                            <td className="px-6 py-4 text-sm font-medium text-white">
                                                {new Date(ex.exchange_date).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex flex-col">
                                                    <span className="text-sm font-black text-rose-400">
                                                        -{ex.from_currency?.symbol}{ex.from_amount.toLocaleString()}
                                                    </span>
                                                    <span className="text-[10px] text-[#e8b4b4] uppercase font-bold">{ex.from_currency?.code}</span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex flex-col">
                                                    <span className="text-sm font-black text-primary">
                                                        +{ex.to_amount.toLocaleString()} {ex.to_currency?.code}
                                                    </span>
                                                    {ex.to_reserve ? (
                                                        <span className="text-[10px] font-black text-primary uppercase flex items-center gap-1">
                                                            <span className="material-symbols-outlined text-[12px]">savings</span>
                                                            {ex.to_reserve.name}
                                                        </span>
                                                    ) : (
                                                        <span className="text-[10px] text-[#e8b4b4] uppercase font-bold">Balance</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="text-xs font-mono text-white bg-[#1c0f0f] px-2 py-1 rounded border border-[#3e2121]">
                                                    1:{ex.exchange_rate}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-sm text-[#e8b4b4] max-w-xs truncate">
                                                {ex.notes || '-'}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <button
                                                    onClick={() => handleDelete(ex.id)}
                                                    className="text-[#e8b4b4] hover:text-rose-400 transition-colors"
                                                >
                                                    <span className="material-symbols-outlined text-lg">delete</span>
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-12 text-center text-[#e8b4b4] italic font-bold text-sm uppercase">
                                            No exchanges recorded yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
