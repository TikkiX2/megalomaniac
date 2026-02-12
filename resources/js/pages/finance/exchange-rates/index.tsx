import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { ExchangeRate, Currency } from '@/types/finance';

interface Props {
    exchangeRates: {
        data: ExchangeRate[];
        links: any[];
    };
    currencies: Currency[];
    filters: {
        from_currency_id?: string;
        to_currency_id?: string;
    };
}

export default function ExchangeRatesIndex({ exchangeRates, currencies, filters }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        from_currency_id: '',
        to_currency_id: '',
        rate: '',
        effective_date: new Date().toISOString().split('T')[0],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(finance.exchangeRates.store().url, {
            onSuccess: () => reset(),
        });
    };

    return (
        <MainLayout>
            <Head title="Exchange Rates" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                            Exchange Rates</h2>
                        <p className="mt-1 text-base font-medium text-[#92c9a4]">
                            History of your purchasing power.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    {/* Create Form */}
                    <div className="rounded-2xl bg-[#193322] border border-[#23482f] p-6 h-fit shadow-xl">
                        <h3 className="mb-6 text-sm font-black uppercase tracking-widest text-white">Register Rate</h3>
                        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">From Currency</Label>
                                <select
                                    value={data.from_currency_id}
                                    onChange={e => setData('from_currency_id', e.target.value)}
                                    className="bg-[#102216] border-[#23482f] text-white rounded-lg px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="">Select</option>
                                    {currencies.map(c => <option key={c.id} value={c.id}>{c.code}</option>)}
                                </select>
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">To Currency</Label>
                                <select
                                    value={data.to_currency_id}
                                    onChange={e => setData('to_currency_id', e.target.value)}
                                    className="bg-[#102216] border-[#23482f] text-white rounded-lg px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="">Select</option>
                                    {currencies.map(c => <option key={c.id} value={c.id}>{c.code}</option>)}
                                </select>
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Rate (1 From = X To)</Label>
                                <Input
                                    type="number"
                                    step="0.000001"
                                    value={data.rate}
                                    onChange={e => setData('rate', e.target.value)}
                                    className="bg-[#102216] border-[#23482f] text-white h-10"
                                    placeholder="1.00"
                                    required
                                />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Effective Date</Label>
                                <Input
                                    type="date"
                                    value={data.effective_date}
                                    onChange={e => setData('effective_date', e.target.value)}
                                    className="bg-[#102216] border-[#23482f] text-white h-10"
                                    required
                                />
                            </div>
                            <Button disabled={processing} className="mt-2 bg-primary font-black text-[#102216] hover:bg-green-400">
                                {processing ? '...' : 'Save Rate'}
                            </Button>
                        </form>
                    </div>

                    {/* Rates Table */}
                    <div className="lg:col-span-2 overflow-hidden rounded-2xl bg-[#193322] border border-[#23482f] shadow-xl">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="border-b border-[#23482f] bg-[#102216]/50">
                                    <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Date</th>
                                    <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Pair</th>
                                    <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Rate</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#23482f]/30">
                                {exchangeRates.data.map((r) => (
                                    <tr key={r.id} className="group hover:bg-white/[0.02] transition-colors">
                                        <td className="px-6 py-4 text-sm font-medium text-[#92c9a4]">{new Date(r.effective_date).toLocaleDateString()}</td>
                                        <td className="px-6 py-4 text-sm font-black text-white">
                                            {r.from_currency?.code} → {r.to_currency?.code}
                                        </td>
                                        <td className="px-6 py-4 text-sm font-black text-primary">
                                            {parseFloat(r.rate as any).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 6 })}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}

