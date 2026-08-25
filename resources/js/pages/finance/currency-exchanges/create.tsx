import { Head, useForm, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Currency, IncomeSource, WithdrawalCategory, SavingsReserve } from '@/types/finance';
import * as React from 'react';
import { cn } from '@/lib/utils';

interface Props {
    currencies: Currency[];
    incomeSources: IncomeSource[];
    withdrawalCategories: WithdrawalCategory[];
    savingsReserves: SavingsReserve[];
}

export default function CreateExchange({ currencies, incomeSources, withdrawalCategories, savingsReserves }: Props) {
    const [targetType, setTargetType] = React.useState<'balance' | 'reserve'>('balance');

    const { data, setData, post, processing, errors } = useForm({
        from_currency_id: '',
        to_currency_id: '',
        to_reserve_id: '',
        from_amount: '',
        to_amount: '',
        exchange_date: new Date().toISOString().split('T')[0],
        notes: '',
        withdrawal_category_id: '',
        income_source_id: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(finance.currencyExchanges.store().url);
    };

    // Calculate effective rate if both amounts are present
    const effectiveRate = React.useMemo(() => {
        const from = parseFloat(data.from_amount);
        const to = parseFloat(data.to_amount);
        if (from > 0 && to > 0) {
            return (to / from).toFixed(4);
        }
        return null;
    }, [data.from_amount, data.to_amount]);

    // Update currency when reserve changes
    React.useEffect(() => {
        if (targetType === 'reserve' && data.to_reserve_id) {
            const reserve = savingsReserves.find(r => r.id.toString() === data.to_reserve_id);
            if (reserve) {
                setData('to_currency_id', reserve.currency_id.toString());
            }
        }
    }, [data.to_reserve_id, targetType]);

    return (
        <MainLayout>
            <Head title="Record Currency Exchange" />
            <div className="mx-auto max-w-2xl p-6 lg:p-8">
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-black text-white uppercase tracking-tight">Record Exchange</h2>
                        <p className="text-sm font-medium text-[#e8b4b4]">Swap currencies and update balances.</p>
                    </div>
                    <Link
                        href={finance.dashboard().url}
                        className="text-xs font-bold text-[#e8b4b4] hover:text-white transition-colors uppercase tracking-widest"
                    >
                        Cancel
                    </Link>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6 rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-8 shadow-xl">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        {/* FROM Section */}
                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <span className="material-symbols-outlined text-rose-400 text-sm">account_balance_wallet</span>
                                <h3 className="text-[10px] font-black uppercase tracking-[0.2em] text-rose-400">Egreso (Gave Away)</h3>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="from_currency_id" className="text-xs font-bold text-[#e8b4b4] uppercase tracking-wider">Source Currency</Label>
                                <Select
                                    value={data.from_currency_id}
                                    onValueChange={value => setData('from_currency_id', value)}
                                >
                                    <SelectTrigger id="from_currency_id" className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary">
                                        <SelectValue placeholder="Select currency" />
                                    </SelectTrigger>
                                    <SelectContent className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                        {currencies.map(c => (
                                            <SelectItem key={c.id} value={c.id.toString()}>{c.code} - {c.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.from_currency_id && <p className="text-[10px] font-bold text-rose-400 uppercase">{errors.from_currency_id}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="from_amount" className="text-xs font-bold text-[#e8b4b4] uppercase tracking-wider">Amount Sent</Label>
                                <Input
                                    id="from_amount"
                                    type="number"
                                    step="0.01"
                                    value={data.from_amount}
                                    onChange={e => setData('from_amount', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary h-10"
                                    placeholder="0.00"
                                />
                                {errors.from_amount && <p className="text-[10px] font-bold text-rose-400 uppercase">{errors.from_amount}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="withdrawal_category_id" className="text-xs font-bold text-[#e8b4b4] uppercase tracking-wider">Category (Optional)</Label>
                                <Select
                                    value={data.withdrawal_category_id}
                                    onValueChange={value => setData('withdrawal_category_id', value)}
                                >
                                    <SelectTrigger id="withdrawal_category_id" className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary">
                                        <SelectValue placeholder="Select category" />
                                    </SelectTrigger>
                                    <SelectContent className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                        {withdrawalCategories.map(c => (
                                            <SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* TO Section */}
                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <span className="material-symbols-outlined text-primary text-sm">savings</span>
                                <h3 className="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Ingreso (Received)</h3>
                            </div>

                            {/* Target Type Toggle */}
                            <div className="grid grid-cols-2 gap-2 rounded-lg bg-[#1c0f0f] p-1 border border-[#3e2121]/50">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setTargetType('balance');
                                        setData('to_reserve_id', '');
                                    }}
                                    className={cn(
                                        "px-3 py-1.5 text-[10px] font-black uppercase tracking-widest rounded-md transition-all",
                                        targetType === 'balance' ? "bg-primary text-white shadow-lg" : "text-[#e8b4b4] hover:bg-white/5"
                                    )}
                                >
                                    Balance
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setTargetType('reserve')}
                                    className={cn(
                                        "px-3 py-1.5 text-[10px] font-black uppercase tracking-widest rounded-md transition-all",
                                        targetType === 'reserve' ? "bg-primary text-white shadow-lg" : "text-[#e8b4b4] hover:bg-white/5"
                                    )}
                                >
                                    Reserve
                                </button>
                            </div>

                            {targetType === 'reserve' ? (
                                <div className="space-y-2">
                                    <Label htmlFor="to_reserve_id" className="text-xs font-bold text-[#e8b4b4] uppercase tracking-wider">Target Reserve</Label>
                                    <Select
                                        value={data.to_reserve_id}
                                        onValueChange={value => setData('to_reserve_id', value)}
                                    >
                                        <SelectTrigger id="to_reserve_id" className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary">
                                            <SelectValue placeholder="Select reserve" />
                                        </SelectTrigger>
                                        <SelectContent className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                            {savingsReserves.map(r => (
                                                <SelectItem key={r.id} value={r.id.toString()}>
                                                    {r.name} ({r.currency?.code})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.to_reserve_id && <p className="text-[10px] font-bold text-rose-400 uppercase">{errors.to_reserve_id}</p>}
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <Label htmlFor="to_currency_id" className="text-xs font-bold text-[#e8b4b4] uppercase tracking-wider">Target Currency</Label>
                                    <Select
                                        value={data.to_currency_id}
                                        onValueChange={value => setData('to_currency_id', value)}
                                    >
                                        <SelectTrigger id="to_currency_id" className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary">
                                            <SelectValue placeholder="Select currency" />
                                        </SelectTrigger>
                                        <SelectContent className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                            {currencies.map(c => (
                                                <SelectItem key={c.id} value={c.id.toString()}>{c.code} - {c.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.to_currency_id && <p className="text-[10px] font-bold text-rose-400 uppercase">{errors.to_currency_id}</p>}
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="to_amount" className="text-xs font-bold text-[#e8b4b4] uppercase tracking-wider">Amount Received</Label>
                                <Input
                                    id="to_amount"
                                    type="number"
                                    step="0.01"
                                    value={data.to_amount}
                                    onChange={e => setData('to_amount', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary h-10"
                                    placeholder="0.00"
                                />
                                {errors.to_amount && <p className="text-[10px] font-bold text-rose-400 uppercase">{errors.to_amount}</p>}
                            </div>

                            {targetType === 'balance' && (
                                <div className="space-y-2">
                                    <Label htmlFor="income_source_id" className="text-xs font-bold text-[#e8b4b4] uppercase tracking-wider">Source (Optional)</Label>
                                    <Select
                                        value={data.income_source_id}
                                        onValueChange={value => setData('income_source_id', value)}
                                    >
                                        <SelectTrigger id="income_source_id" className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary">
                                            <SelectValue placeholder="Select source" />
                                        </SelectTrigger>
                                        <SelectContent className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                            {incomeSources.map(s => (
                                                <SelectItem key={s.id} value={s.id.toString()}>{s.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="border-t border-[#3e2121]/50 pt-6 space-y-4">
                        <div className="flex items-center justify-between">
                            <div className="space-y-1">
                                <Label htmlFor="exchange_date" className="text-xs font-bold text-[#e8b4b4] uppercase tracking-wider">Exchange Date</Label>
                                <Input
                                    id="exchange_date"
                                    type="date"
                                    value={data.exchange_date}
                                    onChange={e => setData('exchange_date', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary"
                                />
                                {errors.exchange_date && <p className="text-[10px] font-bold text-rose-400 uppercase">{errors.exchange_date}</p>}
                            </div>

                            {effectiveRate && (
                                <div className="text-right">
                                    <span className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Effective Rate</span>
                                    <div className="text-lg font-black text-primary">1 : {effectiveRate}</div>
                                </div>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="notes" className="text-xs font-bold text-[#e8b4b4] uppercase tracking-wider">Notes</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('notes', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary min-h-[80px]"
                                placeholder="Details about the exchange..."
                            />
                        </div>
                    </div>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full bg-primary font-black uppercase tracking-[0.2em] text-white shadow-[0_0_20px_rgba(239,68,68,0.2)] hover:bg-primary/90 py-6"
                    >
                        {processing ? 'Recording...' : 'Record Exchange'}
                    </Button>
                </form>
            </div>
        </MainLayout>
    );
}
