import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MainLayout from '@/layouts/main-layout';
import finance from '@/routes/finance';
import type { Currency } from '@/types/finance';

interface Props {
    currencies: Currency[];
}

export default function CurrenciesIndex({ currencies }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        name: '',
        symbol: '',
        is_active: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(finance.currencies.store().url, {
            onSuccess: () => reset(),
        });
    };

    return (
        <MainLayout>
            <Head title="Currencies" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                            Currencies</h2>
                        <p className="mt-1 text-base font-medium text-[#e8b4b4]">
                            Manage the symbols of your value.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    {/* Create Form */}
                    <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6 h-fit shadow-xl">
                        <h3 className="mb-6 text-sm font-black uppercase tracking-widest text-white">Add New Currency</h3>
                        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="code" className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">ISO Code (e.g. USD, ARS)</Label>
                                <Input
                                    id="code"
                                    maxLength={3}
                                    value={data.code}
                                    onChange={e => setData('code', e.target.value.toUpperCase())}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white h-10"
                                    placeholder="USD"
                                    required
                                />
                                {errors.code && <span className="text-[10px] font-bold text-rose-500">{errors.code}</span>}
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="name" className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white h-10"
                                    placeholder="US Dollar"
                                    required
                                />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="symbol" className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Symbol</Label>
                                <Input
                                    id="symbol"
                                    value={data.symbol}
                                    onChange={e => setData('symbol', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white h-10"
                                    placeholder="$"
                                    required
                                />
                            </div>
                            <Button disabled={processing} className="mt-2 bg-primary font-black text-white hover:bg-primary/90">
                                {processing ? '...' : 'Create Currency'}
                            </Button>
                        </form>
                    </div>

                    {/* Currencies Table */}
                    <div className="lg:col-span-2 overflow-hidden rounded-2xl bg-[#2b1a1a] border border-[#3e2121] shadow-xl">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="border-b border-[#3e2121] bg-[#1c0f0f]/50">
                                    <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Code</th>
                                    <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Name</th>
                                    <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Symbol</th>
                                    <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Status</th>
                                    <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#3e2121]/30">
                                {currencies.map((c) => (
                                    <tr key={c.id} className="group hover:bg-white/[0.02] transition-colors">
                                        <td className="px-6 py-4 font-black text-white">{c.code}</td>
                                        <td className="px-6 py-4 text-sm font-bold text-[#e8b4b4]">{c.name}</td>
                                        <td className="px-6 py-4 text-sm font-black text-primary">{c.symbol}</td>
                                        <td className="px-6 py-4">
                                            <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-widest border ${c.is_active ? 'bg-primary/10 text-primary border-primary/20' : 'bg-rose-500/10 text-rose-500 border-rose-500/20'
                                                }`}>
                                                {c.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right whitespace-nowrap">
                                            {c.is_active ? (
                                                <Link
                                                    href={finance.currencies.destroy(c.id).url}
                                                    method="delete"
                                                    as="button"
                                                    className="text-rose-500 hover:text-rose-400 transition-colors"
                                                >
                                                    <span className="material-symbols-outlined text-[18px]">block</span>
                                                </Link>
                                            ) : (
                                                <Link
                                                    href={finance.currencies.restore(c.id).url}
                                                    method="patch"
                                                    as="button"
                                                    className="text-primary hover:text-primary transition-colors"
                                                >
                                                    <span className="material-symbols-outlined text-[18px]">settings_backup_restore</span>
                                                </Link>
                                            )}
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

