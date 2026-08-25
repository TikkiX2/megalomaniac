import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { PurchaseCategory, Currency } from '@/types/finance';

interface Props {
    categories: PurchaseCategory[];
    currencies: Currency[];
}

export default function PurchaseCreate({ categories, currencies }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        description: '',
        amount: '',
        currency_id: currencies[0]?.id || '',
        category_id: '',
        purchase_date: new Date().toISOString().split('T')[0],
        notes: '',
        receipt: null as File | null,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(finance.purchases.store().url);
    };

    return (
        <MainLayout>
            <Head title="Record Purchase" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
                <div className="flex items-center gap-4">
                    <Link href={finance.purchases.index().url} className="rounded-full p-2 hover:bg-white/5 text-[#e8b4b4] transition-all">
                        <span className="material-symbols-outlined">arrow_back</span>
                    </Link>
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white">Record Purchase</h2>
                        <p className="text-sm font-medium text-[#e8b4b4]">Log your expenses to stay on track.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6 rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-8 shadow-xl">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="description" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Description</Label>
                            <Input
                                id="description"
                                value={data.description}
                                onChange={e => setData('description', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary h-12"
                                placeholder="What did you buy?"
                                required
                            />
                            {errors.description && <span className="text-xs font-bold text-rose-500">{errors.description}</span>}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="amount" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Amount</Label>
                            <Input
                                id="amount"
                                type="number"
                                step="0.01"
                                value={data.amount}
                                onChange={e => setData('amount', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary h-12"
                                placeholder="0.00"
                                required
                            />
                            {errors.amount && <span className="text-xs font-bold text-rose-500">{errors.amount}</span>}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="currency_id" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Currency</Label>
                            <select
                                id="currency_id"
                                value={data.currency_id}
                                onChange={e => setData('currency_id', e.target.value)}
                                className="flex h-12 w-full rounded-md border border-[#3e2121] bg-[#1c0f0f] px-3 py-1 text-sm text-white shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                                required
                            >
                                <option value="">Select Currency</option>
                                {currencies.map(c => <option key={c.id} value={c.id}>{c.code} ({c.symbol})</option>)}
                            </select>
                            {errors.currency_id && <span className="text-xs font-bold text-rose-500">{errors.currency_id}</span>}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="category_id" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Category</Label>
                            <select
                                id="category_id"
                                value={data.category_id}
                                onChange={e => setData('category_id', e.target.value)}
                                className="flex h-12 w-full rounded-md border border-[#3e2121] bg-[#1c0f0f] px-3 py-1 text-sm text-white shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                                required
                            >
                                <option value="">Select Category</option>
                                {categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                            {errors.category_id && <span className="text-xs font-bold text-rose-500">{errors.category_id}</span>}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="purchase_date" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Date</Label>
                            <Input
                                id="purchase_date"
                                type="date"
                                value={data.purchase_date}
                                onChange={e => setData('purchase_date', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary h-12"
                                required
                            />
                            {errors.purchase_date && <span className="text-xs font-bold text-rose-500">{errors.purchase_date}</span>}
                        </div>

                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="receipt" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Receipt (Optional)</Label>
                            <input
                                id="receipt"
                                type="file"
                                onChange={e => setData('receipt', e.target.files ? e.target.files[0] : null)}
                                className="flex w-full rounded-md border border-[#3e2121] bg-[#1c0f0f] px-3 py-2 text-sm text-[#e8b4b4] file:mr-4 file:rounded-full file:border-0 file:bg-primary/10 file:px-4 file:py-1 file:text-xs file:font-black file:text-primary hover:file:bg-primary/20"
                            />
                            {errors.receipt && <span className="text-xs font-bold text-rose-500">{errors.receipt}</span>}
                        </div>

                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="notes" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Notes</Label>
                            <textarea
                                id="notes"
                                value={data.notes || ''}
                                onChange={e => setData('notes', e.target.value)}
                                className="flex min-h-[80px] w-full rounded-md border border-[#3e2121] bg-[#1c0f0f] px-3 py-2 text-sm text-white shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                                placeholder="Extra details..."
                            />
                        </div>
                    </div>

                    <Button
                        disabled={processing}
                        className="mt-4 bg-primary px-8 py-6 text-base font-black text-white hover:bg-primary/90 shadow-[0_4px_20px_rgba(239,68,68,0.2)]"
                    >
                        {processing ? 'Processing...' : 'Save Purchase'}
                    </Button>
                </form>
            </div>
        </MainLayout>
    );
}

