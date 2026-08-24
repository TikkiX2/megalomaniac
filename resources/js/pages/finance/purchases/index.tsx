import { Head, Link, usePage } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { Purchase, PurchaseCategory, Currency } from '@/types/finance';

interface Props {
    purchases: {
        data: Purchase[];
        links: any[];
    };
    categories: PurchaseCategory[];
    currencies: Currency[];
    filters: {
        category_id?: string;
        currency_id?: string;
        date_from?: string;
        date_to?: string;
    };
}

export default function PurchasesIndex({ purchases, categories, currencies, filters }: Props) {
    return (
        <MainLayout>
            <Head title="Purchases" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                {/* Header Section */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                            Purchases</h2>
                        <p className="mt-1 text-base font-medium text-[#92c9a4]">
                            Track every cent, build your wealth.
                        </p>
                    </div>
                    <div className="flex gap-3">
                        <Link
                            href={finance.purchases.create().url}
                            className="flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-black text-[#102216] shadow-[0_0_20px_rgba(19,236,91,0.25)] transition hover:bg-green-400 active:scale-95">
                            <span className="material-symbols-outlined font-bold" style={{ fontSize: '20px' }}>add_shopping_cart</span>
                            Record Purchase
                        </Link>
                    </div>
                </div>

                {/* Filters Section (Basic) */}
                <div className="rounded-2xl bg-[#193322] border border-[#23482f] p-6">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <select className="bg-[#102216] border-[#23482f] text-white rounded-lg px-3 py-2 text-sm">
                            <option value="">All Categories</option>
                            {categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                        <select className="bg-[#102216] border-[#23482f] text-white rounded-lg px-3 py-2 text-sm">
                            <option value="">All Currencies</option>
                            {currencies.map(c => <option key={c.id} value={c.id}>{c.code}</option>)}
                        </select>
                        <input type="date" className="bg-[#102216] border-[#23482f] text-white rounded-lg px-3 py-2 text-sm" placeholder="From" />
                        <input type="date" className="bg-[#102216] border-[#23482f] text-white rounded-lg px-3 py-2 text-sm" placeholder="To" />
                    </div>
                </div>

                {/* Purchases Table */}
                <div className="overflow-hidden rounded-2xl bg-[#193322] border border-[#23482f] shadow-xl">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-[#23482f] bg-[#102216]/50">
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Date</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Description</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Category</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4] text-right">Amount</th>
                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#23482f]/30">
                            {purchases.data.length > 0 ? (
                                purchases.data.map((p) => (
                                    <tr key={p.id} className="group hover:bg-white/[0.02] transition-colors">
                                        <td className="px-6 py-4">
                                            <span className="text-sm font-medium text-[#92c9a4]">{new Date(p.purchase_date).toLocaleDateString()}</span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex flex-col">
                                                <span className="text-sm font-bold text-white">{p.description}</span>
                                                {p.debt && (
                                                    <span className="text-[10px] font-black uppercase tracking-tighter text-orange-400">Linked to Debt</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            {p.category && (
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className="h-2 w-2 rounded-full"
                                                        style={{ backgroundColor: p.category.color || '#primary' }}
                                                    ></span>
                                                    <span className="text-sm font-medium text-white">{p.category.name}</span>
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <span className="text-sm font-black text-white">{p.currency?.symbol}{p.amount.toLocaleString()}</span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <Link
                                                href={finance.purchases.show(p.id).url}
                                                className="rounded-lg p-2 text-[#92c9a4] hover:bg-white/5 hover:text-white transition-all"
                                            >
                                                <span className="material-symbols-outlined text-[20px]">visibility</span>
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={5} className="px-6 py-12 text-center">
                                        <p className="text-sm font-bold text-[#92c9a4] uppercase italic">No purchases found.</p>
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

