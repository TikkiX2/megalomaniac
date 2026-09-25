import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { WithdrawalCategory, Currency } from '@/types/finance';

interface Props {
    categories: WithdrawalCategory[];
    currencies: Currency[];
}

export default function WithdrawalCreate({ categories, currencies }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        amount: '',
        currency_id: currencies[0]?.id || '',
        category_id: '',
        withdrawal_date: new Date().toISOString().split('T')[0],
        description: '',
        notes: '',
        is_recurring: false,
        recurrence_frequency: 'monthly',
        recurrence_day: new Date().getDate().toString(),
        recurrence_end_date: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(finance.withdrawals.store().url);
    };

    return (
        <MainLayout>
            <Head title="Add Withdrawal" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
                <div className="flex items-center gap-4">
                    <Link href={finance.withdrawals.index().url} className="rounded-full p-2 hover:bg-white/5 text-[#e8b4b4] transition-all">
                        <span className="material-symbols-outlined">arrow_back</span>
                    </Link>
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white">Add Withdrawal</h2>
                        <p className="text-sm font-medium text-[#e8b4b4]">Record an expense or set up a recurring service.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6 rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-8 shadow-xl">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
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
                            <div className="flex gap-2">
                                <select
                                    id="category_id"
                                    value={data.category_id}
                                    onChange={e => setData('category_id', e.target.value)}
                                    className="flex h-12 w-full rounded-md border border-[#3e2121] bg-[#1c0f0f] px-3 py-1 text-sm text-white shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                                >
                                    <option value="">Select Category</option>
                                    {categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                                <Link
                                    href={finance.withdrawalCategories.index().url}
                                    className="flex h-12 w-12 items-center justify-center rounded-md border border-[#3e2121] bg-[#1c0f0f] text-[#e8b4b4] hover:text-white hover:bg-white/5 transition-colors"
                                    title="Manage Categories"
                                >
                                    <span className="material-symbols-outlined">settings</span>
                                </Link>
                            </div>
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="withdrawal_date" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Date</Label>
                            <Input
                                id="withdrawal_date"
                                type="date"
                                value={data.withdrawal_date}
                                onChange={e => setData('withdrawal_date', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary h-12"
                                required
                            />
                        </div>

                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="description" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Description</Label>
                            <Input
                                id="description"
                                value={data.description}
                                onChange={e => setData('description', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary h-12"
                                placeholder="E.g. Monthly Rent, Electricity Bill..."
                                required
                            />
                            {errors.description && <span className="text-xs font-bold text-rose-500">{errors.description}</span>}
                        </div>

                        <div className="flex flex-col gap-4 md:col-span-2 rounded-xl bg-[#1c0f0f]/50 p-4 border border-[#3e2121]/30">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_recurring"
                                    checked={data.is_recurring}
                                    onCheckedChange={(checked) => setData('is_recurring', checked as boolean)}
                                />
                                <Label htmlFor="is_recurring" className="text-sm font-bold text-white cursor-pointer uppercase tracking-tight">Recurring Service</Label>
                            </div>

                            {data.is_recurring && (
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3 pt-2 animate-in fade-in duration-300">
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="recurrence_frequency" className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Frequency</Label>
                                        <select
                                            id="recurrence_frequency"
                                            value={data.recurrence_frequency}
                                            onChange={e => setData('recurrence_frequency', e.target.value)}
                                            className="bg-[#1c0f0f] border-[#3e2121] text-white rounded-md h-10 px-3 text-sm"
                                        >
                                            <option value="monthly">Monthly</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="yearly">Yearly</option>
                                        </select>
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="recurrence_day" className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Day</Label>
                                        <Input
                                            id="recurrence_day"
                                            type="number"
                                            min="1"
                                            max="31"
                                            value={data.recurrence_day}
                                            onChange={e => setData('recurrence_day', e.target.value)}
                                            className="bg-[#1c0f0f] border-[#3e2121] text-white h-10"
                                        />
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="recurrence_end_date" className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">End Date (Optional)</Label>
                                        <Input
                                            id="recurrence_end_date"
                                            type="date"
                                            value={data.recurrence_end_date}
                                            onChange={e => setData('recurrence_end_date', e.target.value)}
                                            className="bg-[#1c0f0f] border-[#3e2121] text-white h-10"
                                        />
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="notes" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Notes (Optional)</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('notes', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary min-h-[100px]"
                                placeholder="Additional details..."
                            />
                        </div>
                    </div>

                    <Button
                        disabled={processing}
                        className="mt-4 bg-primary px-8 py-6 text-base font-black text-white hover:bg-primary/90 shadow-[0_4px_20px_rgba(239,68,68,0.2)]"
                    >
                        {processing ? 'Processing...' : 'Save Withdrawal'}
                    </Button>
                </form>
            </div>
        </MainLayout>
    );
}
