import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { Currency } from '@/types/finance';

interface Props {
    currencies: Currency[];
}

export default function SavingsReserveCreate({ currencies }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        currency_id: currencies[0]?.id || '',
        current_amount: '',
        goal_amount: '',
        target_date: '',
        description: '',
        color: '#e8b4b4',
        icon: 'savings',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(finance.savingsReserves.store().url);
    };

    return (
        <MainLayout>
            <Head title="New Reserve" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
                <div className="flex items-center gap-4">
                    <Link href={finance.savingsReserves.index().url} className="rounded-full p-2 hover:bg-white/5 text-[#e8b4b4] transition-all">
                        <span className="material-symbols-outlined">arrow_back</span>
                    </Link>
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white">Create Reserve</h2>
                        <p className="text-sm font-medium text-[#e8b4b4]">Set up a new savings goal or account.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6 rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-8 shadow-xl">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="name" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Reserve Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary h-12"
                                placeholder="E.g. Emergency Fund, New Car, Vacation"
                                required
                            />
                            {errors.name && <span className="text-xs font-bold text-rose-500">{errors.name}</span>}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="current_amount" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Current Balance</Label>
                            <Input
                                id="current_amount"
                                type="number"
                                step="0.01"
                                value={data.current_amount}
                                onChange={e => setData('current_amount', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary h-12"
                                placeholder="0.00"
                                required
                            />
                            {errors.current_amount && <span className="text-xs font-bold text-rose-500">{errors.current_amount}</span>}
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
                            <Label htmlFor="goal_amount" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Target Goal (Optional)</Label>
                            <Input
                                id="goal_amount"
                                type="number"
                                step="0.01"
                                value={data.goal_amount}
                                onChange={e => setData('goal_amount', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary h-12"
                                placeholder="0.00"
                            />
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="target_date" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Target Date (Optional)</Label>
                            <Input
                                id="target_date"
                                type="date"
                                value={data.target_date}
                                onChange={e => setData('target_date', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary h-12"
                            />
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="color" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Color Tag</Label>
                            <div className="flex gap-2">
                                <Input
                                    id="color"
                                    type="color"
                                    value={data.color}
                                    onChange={e => setData('color', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] h-12 w-16 p-1 cursor-pointer"
                                />
                                <Input
                                    value={data.color}
                                    onChange={e => setData('color', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white h-12 uppercase flex-1"
                                    maxLength={7}
                                />
                            </div>
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="icon" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Icon Name</Label>
                            <div className="relative">
                                <Input
                                    id="icon"
                                    value={data.icon}
                                    onChange={e => setData('icon', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary h-12 pl-12"
                                    placeholder="savings"
                                />
                                <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                    <span className="material-symbols-outlined">{data.icon || 'savings'}</span>
                                </div>
                            </div>
                            <p className="text-[10px] text-gray-500">Google Material Icon name</p>
                        </div>

                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="description" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Description (Optional)</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('description', e.target.value)}
                                className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary min-h-[100px]"
                                placeholder="What is this reserve for?"
                            />
                        </div>
                    </div>

                    <Button
                        disabled={processing}
                        className="mt-4 bg-primary px-8 py-6 text-base font-black text-white hover:bg-primary/90 shadow-[0_4px_20px_rgba(239,68,68,0.2)]"
                    >
                        {processing ? 'Processing...' : 'Create Reserve'}
                    </Button>
                </form>
            </div>
        </MainLayout>
    );
}
