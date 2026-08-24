import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { CreditCard, Currency } from '@/types/finance';

interface Props {
    creditCards: CreditCard[];
    currencies: Currency[];
}

export default function DebtCreate({ creditCards, currencies }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        original_amount: '',
        currency_id: currencies[0]?.id || '',
        credit_card_id: '',
        due_date: '',
        notes: '',
        // purchase_id: '', // Optional: usually linked from purchase
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(finance.debts.store().url);
    };

    return (
        <MainLayout>
            <Head title="Record Debt" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
                <div className="flex items-center gap-4">
                    <Link href={finance.debts.index().url} className="rounded-full p-2 hover:bg-white/5 text-[#92c9a4] transition-all">
                        <span className="material-symbols-outlined">arrow_back</span>
                    </Link>
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white">Record Debt</h2>
                        <p className="text-sm font-medium text-orange-400">Track your liabilities, gain your freedom.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6 rounded-2xl bg-[#193322] border border-[#23482f] p-8 shadow-xl">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="original_amount" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Principal Amount</Label>
                            <Input
                                id="original_amount"
                                type="number"
                                step="0.01"
                                value={data.original_amount}
                                onChange={e => setData('original_amount', e.target.value)}
                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12"
                                placeholder="0.00"
                                required
                            />
                            {errors.original_amount && <span className="text-xs font-bold text-rose-500">{errors.original_amount}</span>}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="currency_id" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Currency</Label>
                            <select
                                id="currency_id"
                                value={data.currency_id}
                                onChange={e => setData('currency_id', e.target.value)}
                                className="flex h-12 w-full rounded-md border border-[#23482f] bg-[#102216] px-3 py-1 text-sm text-white shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                                required
                            >
                                <option value="">Select Currency</option>
                                {currencies.map(c => <option key={c.id} value={c.id}>{c.code} ({c.symbol})</option>)}
                            </select>
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="credit_card_id" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Credit Card (Optional)</Label>
                            <select
                                id="credit_card_id"
                                value={data.credit_card_id}
                                onChange={e => setData('credit_card_id', e.target.value)}
                                className="flex h-12 w-full rounded-md border border-[#23482f] bg-[#102216] px-3 py-1 text-sm text-white shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                            >
                                <option value="">Direct Debt (No Card)</option>
                                {creditCards.map(cc => <option key={cc.id} value={cc.id}>{cc.name} (**** {cc.last_four_digits})</option>)}
                            </select>
                            {errors.credit_card_id && <span className="text-xs font-bold text-rose-500">{errors.credit_card_id}</span>}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="due_date" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Next Due Date</Label>
                            <Input
                                id="due_date"
                                type="date"
                                value={data.due_date}
                                onChange={e => setData('due_date', e.target.value)}
                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12"
                            />
                        </div>

                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="notes" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Notes</Label>
                            <textarea
                                id="notes"
                                value={data.notes || ''}
                                onChange={e => setData('notes', e.target.value)}
                                className="flex min-h-[100px] w-full rounded-md border border-[#23482f] bg-[#102216] px-3 py-2 text-sm text-white shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                                placeholder="Details about this debt..."
                            />
                        </div>
                    </div>

                    <div className="rounded-xl bg-orange-500/5 border border-orange-500/10 p-4">
                        <p className="text-[10px] font-medium text-[#92c9a4] italic">
                            {data.credit_card_id
                                ? "Interest and taxes will be automatically calculated based on the selected credit card's settings."
                                : "No card selected. Interest and taxes will be set to 0."}
                        </p>
                    </div>

                    <Button
                        disabled={processing}
                        className="mt-4 bg-primary px-8 py-6 text-base font-black text-[#102216] hover:bg-green-400 shadow-[0_4px_20_rgba(19,236,91,0.2)]"
                    >
                        {processing ? 'Processing...' : 'Save Debt'}
                    </Button>
                </form>
            </div>
        </MainLayout>
    );
}

