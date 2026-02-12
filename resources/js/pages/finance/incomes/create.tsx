import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { IncomeSource, Currency } from '@/types/finance';

interface Props {
    incomeSources: IncomeSource[];
    currencies: Currency[];
}

export default function IncomeCreate({ incomeSources, currencies }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        amount: '',
        currency_id: currencies[0]?.id || '',
        income_source_id: '',
        received_date: new Date().toISOString().split('T')[0],
        description: '',
        is_recurring: false,
        recurrence_day: '',
        recurrence_end_date: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(finance.incomes.store().url);
    };

    return (
        <MainLayout>
            <Head title="Add Income" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
                <div className="flex items-center gap-4">
                    <Link href={finance.incomes.index().url} className="rounded-full p-2 hover:bg-white/5 text-[#92c9a4] transition-all">
                        <span className="material-symbols-outlined">arrow_back</span>
                    </Link>
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white">Add Income</h2>
                        <p className="text-sm font-medium text-[#92c9a4]">Grow your wealth, one income at a time.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6 rounded-2xl bg-[#193322] border border-[#23482f] p-8 shadow-xl">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="amount" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Amount</Label>
                            <Input
                                id="amount"
                                type="number"
                                step="0.01"
                                value={data.amount}
                                onChange={e => setData('amount', e.target.value)}
                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12"
                                placeholder="0.00"
                                required
                            />
                            {errors.amount && <span className="text-xs font-bold text-rose-500">{errors.amount}</span>}
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
                            <Label htmlFor="income_source_id" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Source</Label>
                            <select
                                id="income_source_id"
                                value={data.income_source_id}
                                onChange={e => setData('income_source_id', e.target.value)}
                                className="flex h-12 w-full rounded-md border border-[#23482f] bg-[#102216] px-3 py-1 text-sm text-white shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                                required
                            >
                                <option value="">Select Source</option>
                                {incomeSources.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="received_date" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Received Date</Label>
                            <Input
                                id="received_date"
                                type="date"
                                value={data.received_date}
                                onChange={e => setData('received_date', e.target.value)}
                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12"
                                required
                            />
                        </div>

                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="description" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Description (Optional)</Label>
                            <Input
                                id="description"
                                value={data.description}
                                onChange={e => setData('description', e.target.value)}
                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12"
                                placeholder="E.g. Monthly salary, Freelance project..."
                            />
                        </div>

                        <div className="flex flex-col gap-4 md:col-span-2 rounded-xl bg-[#102216]/50 p-4 border border-[#23482f]/30">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_recurring"
                                    checked={data.is_recurring}
                                    onCheckedChange={(checked) => setData('is_recurring', checked as boolean)}
                                />
                                <Label htmlFor="is_recurring" className="text-sm font-bold text-white cursor-pointer uppercase tracking-tight">Recurring Income</Label>
                            </div>

                            {data.is_recurring && (
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 pt-2 animate-in fade-in duration-300">
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="recurrence_day" className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Pay Day (1-31)</Label>
                                        <Input
                                            id="recurrence_day"
                                            type="number"
                                            min="1"
                                            max="31"
                                            value={data.recurrence_day}
                                            onChange={e => setData('recurrence_day', e.target.value)}
                                            className="bg-[#102216] border-[#23482f] text-white h-10"
                                        />
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="recurrence_end_date" className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">End Date (Optional)</Label>
                                        <Input
                                            id="recurrence_end_date"
                                            type="date"
                                            value={data.recurrence_end_date}
                                            onChange={e => setData('recurrence_end_date', e.target.value)}
                                            className="bg-[#102216] border-[#23482f] text-white h-10"
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    <Button
                        disabled={processing}
                        className="mt-4 bg-primary px-8 py-6 text-base font-black text-[#102216] hover:bg-green-400 shadow-[0_4px_20px_rgba(19,236,91,0.2)]"
                    >
                        {processing ? 'Processing...' : 'Save Income'}
                    </Button>
                </form>
            </div>
        </MainLayout>
    );
}

