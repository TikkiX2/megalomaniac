import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { Debt } from '@/types/finance';

interface Props {
    debt: Debt;
}

export default function DebtShow({ debt }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        amount: '',
        payment_date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const isPaid = debt.status === 'paid';
    const isOverdue = debt.due_date && new Date(debt.due_date) < new Date() && !isPaid;

    const handlePayment = (e: React.FormEvent) => {
        e.preventDefault();
        post(finance.debts.payments.store(debt.id).url, {
            onSuccess: () => reset('amount', 'notes'),
        });
    };

    return (
        <MainLayout>
            <Head title={`Debt: ${debt.purchase?.description || 'Detail'}`} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
                {/* Header */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div className="flex items-center gap-4">
                        <Link href={finance.debts.index().url} className="rounded-full p-2 hover:bg-white/5 text-[#92c9a4] transition-all">
                            <span className="material-symbols-outlined">arrow_back</span>
                        </Link>
                        <div>
                            <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">Debt Detail</h2>
                            <p className="text-sm font-medium text-[#92c9a4]">
                                {debt.purchase ? (
                                    <>Linked to: <span className="text-primary font-bold">{debt.purchase.description}</span></>
                                ) : (
                                    <span className="italic">Direct manual debt record</span>
                                )}
                            </p>
                        </div>
                    </div>

                    <div className={`flex items-center gap-2 rounded-full px-4 py-1.5 border font-black uppercase tracking-widest text-[10px] ${isPaid ? 'bg-primary/10 text-primary border-primary/20' :
                        isOverdue ? 'bg-rose-500/10 text-rose-400 border-rose-400/20 animate-pulse' :
                            'bg-orange-500/10 text-orange-400 border-orange-400/20'
                        }`}>
                        <span className="material-symbols-outlined text-[14px]">{isPaid ? 'check_circle' : isOverdue ? 'warning' : 'pending'}</span>
                        {debt.status} {isOverdue && '(OVERDUE)'}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    {/* Main Info */}
                    <div className="lg:col-span-2 space-y-8">
                        {/* Highlights Grid */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div className="rounded-2xl bg-[#193322] border border-[#23482f] p-5 flex flex-col gap-1 ring-1 ring-white/5">
                                <span className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Remaining</span>
                                <span className="text-2xl font-black text-rose-400">{debt.currency?.symbol} {parseFloat(debt.remaining_amount as any).toLocaleString()}</span>
                            </div>
                            <div className="rounded-2xl bg-[#193322] border border-[#23482f] p-5 flex flex-col gap-1 ring-1 ring-white/5">
                                <span className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Total Debt</span>
                                <span className="text-2xl font-black text-white">{debt.currency?.symbol} {parseFloat(debt.total_amount as any).toLocaleString()}</span>
                            </div>
                            <div className="rounded-2xl bg-[#193322] border border-[#23482f] p-5 flex flex-col gap-1 ring-1 ring-white/5">
                                <span className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Next Due Date</span>
                                <span className={`text-2xl font-black ${isOverdue ? 'text-rose-500' : 'text-primary'}`}>
                                    {debt.due_date ? new Date(debt.due_date).toLocaleDateString() : 'N/A'}
                                </span>
                            </div>
                        </div>

                        {/* Details Card */}
                        <div className="rounded-2xl bg-[#193322]/50 border border-[#23482f] p-6 backdrop-blur-sm">
                            <h3 className="text-lg font-black text-white mb-6 flex items-center gap-2">
                                <span className="material-symbols-outlined text-primary">info</span>
                                Breakdown
                            </h3>
                            <div className="grid grid-cols-2 gap-y-6 gap-x-12">
                                <div>
                                    <Label className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4] block mb-1">Principal</Label>
                                    <span className="text-base font-bold text-white">{debt.currency?.symbol} {parseFloat(debt.original_amount as any).toLocaleString()}</span>
                                </div>
                                <div>
                                    <Label className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4] block mb-1">Interest</Label>
                                    <span className="text-base font-bold text-white">{debt.currency?.symbol} {parseFloat(debt.interest_amount as any).toLocaleString()}</span>
                                </div>
                                <div>
                                    <Label className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4] block mb-1">Tax</Label>
                                    <span className="text-base font-bold text-white">{debt.currency?.symbol} {parseFloat(debt.tax_amount as any).toLocaleString()}</span>
                                </div>
                                <div>
                                    <Label className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4] block mb-1">Credit Card</Label>
                                    {debt.credit_card ? (
                                        <Link href={finance.creditCards.show(debt.credit_card_id || 0).url} className="text-base font-bold text-primary hover:underline">
                                            {debt.credit_card.name} (**** {debt.credit_card.last_four_digits})
                                        </Link>
                                    ) : (
                                        <span className="text-sm font-bold text-gray-400 italic">None (Direct Debt)</span>
                                    )}
                                </div>
                                <div className="col-span-2">
                                    <Label className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4] block mb-1">Notes</Label>
                                    <p className="text-sm font-medium text-white/80 leading-relaxed italic">
                                        {debt.notes || 'No notes provided for this debt.'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Payments Timeline */}
                        <div className="space-y-4">
                            <h3 className="text-lg font-black text-white flex items-center gap-2">
                                <span className="material-symbols-outlined text-primary">history</span>
                                Payment History
                            </h3>

                            <div className="space-y-3">
                                {debt.payments?.length === 0 ? (
                                    <div className="rounded-xl border border-[#23482f] border-dashed p-8 text-center text-[#92c9a4] italic font-medium">
                                        No payments recorded yet.
                                    </div>
                                ) : (
                                    debt.payments?.map((p) => (
                                        <div key={p.id} className="flex items-center justify-between rounded-xl bg-[#193322] border border-[#23482f] p-4 transition-all hover:bg-white/5">
                                            <div className="flex items-center gap-4">
                                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary border border-primary/20">
                                                    <span className="material-symbols-outlined text-[20px]">payments</span>
                                                </div>
                                                <div>
                                                    <div className="text-sm font-black text-white">{new Date(p.payment_date).toLocaleDateString()}</div>
                                                    <div className="text-[10px] text-[#92c9a4] italic">{p.notes || 'Direct payment'}</div>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-base font-black text-primary">+{debt.currency?.symbol}{parseFloat(p.amount as any).toLocaleString()}</div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Sidebar / Actions */}
                    <div className="space-y-6">
                        {!isPaid && (
                            <div className="rounded-2xl bg-[#193322] border border-[#23482f] p-6 shadow-xl ring-2 ring-primary/5">
                                <h3 className="text-lg font-bold text-white mb-6 flex items-center gap-2">
                                    <span className="material-symbols-outlined text-primary">add_circle</span>
                                    Add Payment
                                </h3>
                                <form onSubmit={handlePayment} className="space-y-5">
                                    <div className="space-y-2">
                                        <Label htmlFor="amount" className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Payment Amount</Label>
                                        <div className="relative">
                                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[#92c9a4] font-bold">{debt.currency?.symbol}</span>
                                            <Input
                                                id="amount"
                                                type="number"
                                                step="0.01"
                                                max={debt.remaining_amount}
                                                value={data.amount}
                                                onChange={e => setData('amount', e.target.value)}
                                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary pl-10 h-12 font-black"
                                                placeholder="0.00"
                                                required
                                            />
                                        </div>
                                        {errors.amount && <p className="text-xs font-bold text-rose-500">{errors.amount}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="payment_date" className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Date</Label>
                                        <Input
                                            id="payment_date"
                                            type="date"
                                            value={data.payment_date}
                                            onChange={e => setData('payment_date', e.target.value)}
                                            className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12"
                                            required
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="notes" className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Notes (Optional)</Label>
                                        <Input
                                            id="notes"
                                            value={data.notes}
                                            onChange={e => setData('notes', e.target.value)}
                                            className="bg-[#102216] border-[#23482f] text-white focus:ring-primary"
                                            placeholder="Receipt info, mode..."
                                        />
                                    </div>

                                    <Button
                                        disabled={processing}
                                        className="w-full bg-primary py-6 text-base font-black text-[#102216] hover:bg-green-400 shadow-[0_4px_15_rgba(19,236,91,0.2)]"
                                    >
                                        {processing ? 'Recording...' : 'Record Payment'}
                                    </Button>

                                    <Button
                                        type="button"
                                        onClick={() => setData('amount', debt.remaining_amount.toString())}
                                        className="w-full bg-[#102216] border border-[#23482f] py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4] hover:bg-white/5 transition-all"
                                    >
                                        Pay in Full
                                    </Button>
                                </form>
                            </div>
                        )}

                        <div className="rounded-2xl border border-[#23482f] p-6 bg-white/[0.02]">
                            <h4 className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4] mb-4">Financial Advice</h4>
                            <p className="text-xs text-[#92c9a4] leading-relaxed italic">
                                "{isPaid ? 'Congratulations! This debt is cleared. Keep up the good work on your financial discipline.' : 'Focus on high-interest debts first. Small consistent payments lead to significant progress over time.'}"
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
