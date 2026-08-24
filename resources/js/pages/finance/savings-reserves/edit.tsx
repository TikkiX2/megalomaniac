import { Head, Link, useForm } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { SavingsReserve, Currency } from '@/types/finance';
import finance from '@/routes/finance';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';

interface Props {
    reserve: SavingsReserve;
    currencies: Currency[];
}

export default function SavingsReserveEdit({ reserve, currencies }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: reserve.name || '',
        goal_amount: reserve.goal_amount || '',
        target_date: reserve.target_date || '',
        description: reserve.description || '',
        color: reserve.color || '#92c9a4',
        icon: reserve.icon || 'savings',
        is_active: !!reserve.is_active,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(finance.savingsReserves.update(reserve.id).url);
    };

    return (
        <MainLayout>
            <Head title={`Edit ${reserve.name}`} />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
                <div className="flex items-center gap-4">
                    <Link href={finance.savingsReserves.show(reserve.id).url} className="rounded-full p-2 hover:bg-white/5 text-[#92c9a4] transition-all">
                        <span className="material-symbols-outlined">arrow_back</span>
                    </Link>
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white">Edit Reserve</h2>
                        <p className="text-sm font-medium text-[#92c9a4]">Modify your savings goal or account details.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6 rounded-2xl bg-[#193322] border border-[#23482f] p-8 shadow-xl">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="name" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Reserve Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12"
                                placeholder="E.g. Emergency Fund, New Car, Vacation"
                                required
                            />
                            {errors.name && <span className="text-xs font-bold text-rose-500">{errors.name}</span>}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="goal_amount" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Target Goal (Optional)</Label>
                            <Input
                                id="goal_amount"
                                type="number"
                                step="0.01"
                                value={data.goal_amount}
                                onChange={e => setData('goal_amount', e.target.value)}
                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12"
                                placeholder="0.00"
                            />
                            {errors.goal_amount && <span className="text-xs font-bold text-rose-500">{errors.goal_amount}</span>}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="target_date" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Target Date (Optional)</Label>
                            <Input
                                id="target_date"
                                type="date"
                                value={data.target_date}
                                onChange={e => setData('target_date', e.target.value)}
                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12"
                            />
                            {errors.target_date && <span className="text-xs font-bold text-rose-500">{errors.target_date}</span>}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="color" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Color Tag</Label>
                            <div className="flex gap-2">
                                <Input
                                    id="color"
                                    type="color"
                                    value={data.color}
                                    onChange={e => setData('color', e.target.value)}
                                    className="bg-[#102216] border-[#23482f] h-12 w-16 p-1 cursor-pointer"
                                />
                                <Input
                                    value={data.color}
                                    onChange={e => setData('color', e.target.value)}
                                    className="bg-[#102216] border-[#23482f] text-white h-12 uppercase flex-1"
                                    maxLength={7}
                                />
                            </div>
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="icon" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Icon Name</Label>
                            <div className="relative">
                                <Input
                                    id="icon"
                                    value={data.icon}
                                    onChange={e => setData('icon', e.target.value)}
                                    className="bg-[#102216] border-[#23482f] text-white focus:ring-primary h-12 pl-12"
                                    placeholder="savings"
                                />
                                <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                    <span className="material-symbols-outlined">{data.icon || 'savings'}</span>
                                </div>
                            </div>
                            <p className="text-[10px] text-gray-500">Google Material Icon name</p>
                        </div>

                        <div className="flex flex-col gap-4 md:col-span-2 border-t border-[#23482f]/50 pt-4">
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked: boolean) => setData('is_active', checked)}
                                />
                                <div className="flex flex-col gap-0.5">
                                    <Label htmlFor="is_active" className="text-sm font-bold text-white cursor-pointer">Active Status</Label>
                                    <span className="text-xs text-[#92c9a4]">Inactive reserves are archived and hidden from widgets.</span>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="description" className="text-xs font-black uppercase tracking-widest text-[#92c9a4]">Description (Optional)</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('description', e.target.value)}
                                className="bg-[#102216] border-[#23482f] text-white focus:ring-primary min-h-[100px]"
                                placeholder="What is this reserve for?"
                            />
                        </div>
                    </div>

                    <div className="flex gap-4 mt-4">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => window.history.back()}
                            className="flex-1 text-[#92c9a4] hover:text-white hover:bg-white/5 font-bold h-14"
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={processing}
                            className="flex-[2] bg-primary px-8 py-6 text-base font-black text-[#102216] hover:bg-green-400 shadow-[0_4px_20px_rgba(19,236,91,0.2)] h-14"
                        >
                            {processing ? 'Saving Changes...' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </MainLayout>
    );
}
