import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';

export default function CreditCardCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        last_four_digits: '',
        interest_rate: '0',
        tax_percentage: '0',
        apply_interest: true,
        apply_tax: true,
        is_mine: true,
        owner_id: null,
        notes: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(finance.creditCards.store().url);
    };

    return (
        <MainLayout>
            <Head title="Add Credit Card" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
                <div className="flex items-center gap-4">
                    <Link href={finance.creditCards.index().url} className="rounded-full p-2 hover:bg-white/5 text-[#92c9a4] transition-all">
                        <span className="material-symbols-outlined">arrow_back</span>
                    </Link>
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white">Add Credit Card</h2>
                        <p className="text-sm font-medium text-[#92c9a4]">Configure your financial tools.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6 rounded-2xl bg-[#193322] border border-[#23482f] p-8 shadow-xl">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="name" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Card Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12"
                                placeholder="E.g. Visa Signature, BBVA Mastercard..."
                                required
                            />
                            {errors.name && <span className="text-xs font-bold text-rose-500">{errors.name}</span>}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="last_four_digits" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Last 4 Digits</Label>
                            <Input
                                id="last_four_digits"
                                maxLength={4}
                                value={data.last_four_digits}
                                onChange={e => setData('last_four_digits', e.target.value)}
                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12"
                                placeholder="1234"
                            />
                        </div>

                        <div className="flex items-center space-x-2 pt-6">
                            <Checkbox
                                id="is_mine"
                                checked={data.is_mine}
                                onCheckedChange={(checked) => setData('is_mine', checked as boolean)}
                            />
                            <Label htmlFor="is_mine" className="text-sm font-bold text-white cursor-pointer uppercase tracking-tight">Personal Card (Mine)</Label>
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="interest_rate" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Interest Rate (%)</Label>
                            <Input
                                id="interest_rate"
                                type="number"
                                step="0.01"
                                value={data.interest_rate}
                                onChange={e => setData('interest_rate', e.target.value)}
                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12"
                            />
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="tax_percentage" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Tax Percentage (%)</Label>
                            <Input
                                id="tax_percentage"
                                type="number"
                                step="0.01"
                                value={data.tax_percentage}
                                onChange={e => setData('tax_percentage', e.target.value)}
                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12"
                            />
                        </div>

                        <div className="flex flex-col gap-4 md:col-span-2 rounded-xl bg-[#102216]/50 p-4 border border-[#23482f]/30">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="apply_interest"
                                        checked={data.apply_interest}
                                        onCheckedChange={(checked) => setData('apply_interest', checked as boolean)}
                                    />
                                    <Label htmlFor="apply_interest" className="text-xs font-bold text-white cursor-pointer uppercase tracking-tight">Apply Interest to Debts</Label>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="apply_tax"
                                        checked={data.apply_tax}
                                        onCheckedChange={(checked) => setData('apply_tax', checked as boolean)}
                                    />
                                    <Label htmlFor="apply_tax" className="text-xs font-bold text-white cursor-pointer uppercase tracking-tight">Apply Tax to Debts</Label>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="notes" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Notes</Label>
                            <textarea
                                id="notes"
                                value={data.notes}
                                onChange={e => setData('notes', e.target.value)}
                                className="flex min-h-[80px] w-full rounded-md border border-[#23482f] bg-[#102216] px-3 py-2 text-sm text-white shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                                placeholder="Extra info about this card..."
                            />
                        </div>
                    </div>

                    <Button
                        disabled={processing}
                        className="mt-4 bg-primary px-8 py-6 text-base font-black text-[#102216] hover:bg-green-400 shadow-[0_4px_20px_rgba(19,236,91,0.2)]"
                    >
                        {processing ? 'Processing...' : 'Save Card'}
                    </Button>
                </form>
            </div>
        </MainLayout>
    );
}

