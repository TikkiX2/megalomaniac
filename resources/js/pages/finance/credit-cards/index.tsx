import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { CreditCard } from '@/types/finance';

interface Props {
    creditCards: CreditCard[];
}

export default function CreditCardsIndex({ creditCards }: Props) {
    return (
        <MainLayout>
            <Head title="Credit Cards" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                            Credit Cards</h2>
                        <p className="mt-1 text-base font-medium text-sky-300">
                            The tools of leverage. Use them wisely.
                        </p>
                    </div>
                    <div className="flex gap-3">
                        <Link
                            href={finance.creditCards.create().url}
                            className="flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-black text-white shadow-[0_0_20px_rgba(239,68,68,0.25)] transition hover:bg-primary/90 active:scale-95">
                            <span className="material-symbols-outlined font-bold" style={{ fontSize: '20px' }}>add_card</span>
                            Add Card
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {creditCards.length > 0 ? (
                        creditCards.map((card) => (
                            <div key={card.id} className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#2b1a1a] to-[#1c0f0f] p-8 border border-[#3e2121] shadow-xl group">
                                <div className="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-primary/5 blur-3xl group-hover:bg-primary/10 transition-colors duration-500"></div>

                                <div className="relative z-10 flex flex-col h-full">
                                    <div className="flex justify-between items-start mb-8">
                                        <div className="flex h-12 w-16 items-center justify-center rounded-lg bg-white/5 border border-white/10">
                                            <div className="h-8 w-10 rounded bg-[#3e2121] opacity-50"></div>
                                        </div>
                                        <div className="flex flex-col text-right">
                                            <span className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">{card.is_mine ? 'Personal' : 'External'}</span>
                                            {card.owner && <span className="text-xs font-bold text-white italic">{card.owner.name}</span>}
                                        </div>
                                    </div>

                                    <h3 className="text-xl font-black text-white mb-2">{card.name}</h3>
                                    <div className="flex items-center gap-3 mb-8">
                                        <span className="text-lg font-bold text-[#e8b4b4] tracking-widest">**** **** ****</span>
                                        <span className="text-lg font-black text-white tracking-widest">{card.last_four_digits || '0000'}</span>
                                    </div>

                                    <div className="mt-auto grid grid-cols-2 gap-4 pt-4 border-t border-[#3e2121]/30">
                                        <div className="flex flex-col">
                                            <span className="text-[8px] font-black uppercase tracking-widest text-[#e8b4b4]">Interest Rate</span>
                                            <span className="text-sm font-black text-white">{card.interest_rate}%</span>
                                        </div>
                                        <div className="flex flex-col">
                                            <span className="text-[8px] font-black uppercase tracking-widest text-[#e8b4b4]">Tax</span>
                                            <span className="text-sm font-black text-white">{card.tax_percentage}%</span>
                                        </div>
                                    </div>

                                    <div className="mt-6 flex justify-end gap-2">
                                        <Link href={finance.creditCards.edit(card.id).url} className="rounded-lg p-2 text-[#e8b4b4] hover:bg-white/5 hover:text-white transition-all">
                                            <span className="material-symbols-outlined text-[20px]">edit</span>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="md:col-span-2 lg:col-span-3 py-12 flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-[#3e2121] text-[#e8b4b4]">
                            <span className="material-symbols-outlined text-4xl mb-2">credit_card_off</span>
                            <p className="font-bold uppercase tracking-widest text-sm">No cards registered.</p>
                        </div>
                    )}
                </div>
            </div>
        </MainLayout>
    );
}

