import { Head, Link, useForm } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { IncomeSource, Currency } from '@/types/finance';
import finance from '@/routes/finance';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Props {
    sources: IncomeSource[];
    currencies: Currency[];
}

export default function IncomeSourcesIndex({ sources, currencies }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        description: '',
        default_currency_id: currencies[0]?.id.toString() || '',
        is_active: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(finance.incomeSources.store().url, {
            onSuccess: () => reset(),
        });
    };

    return (
        <MainLayout>
            <Head title="Income Sources" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
                <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-black tracking-tight text-white">Income Sources</h1>
                        <p className="text-[#e8b4b4] font-medium mt-1">Manage where your money comes from</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    {/* Create Form */}
                    <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6 shadow-xl h-fit">
                        <h2 className="mb-6 text-xl font-bold text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-primary">add_circle</span>
                            New Source
                        </h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary"
                                    placeholder="e.g. Salary, Freelance..."
                                    required
                                />
                                {errors.name && <p className="text-xs text-rose-400">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Description</Label>
                                <Input
                                    id="description"
                                    value={data.description || ''}
                                    onChange={(e) => setData('description', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary"
                                    placeholder="Brief description..."
                                />
                                {errors.description && <p className="text-xs text-rose-400">{errors.description}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Default Currency</Label>
                                <Select
                                    value={data.default_currency_id}
                                    onValueChange={(v) => setData('default_currency_id', v)}
                                >
                                    <SelectTrigger className="bg-[#1c0f0f] border-[#3e2121] text-white">
                                        <SelectValue placeholder="Select currency" />
                                    </SelectTrigger>
                                    <SelectContent className="bg-[#2b1a1a] border-[#3e2121] text-white">
                                        {currencies.map((c) => (
                                            <SelectItem key={c.id} value={c.id.toString()}>
                                                {c.code} ({c.symbol})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-primary font-black text-white hover:bg-primary/90 shadow-[0_0_15px_rgba(239,68,68,0.2)]"
                            >
                                {processing ? 'Creating...' : 'Create Source'}
                            </Button>
                        </form>
                    </div>

                    {/* Sources List */}
                    <div className="lg:col-span-2 overflow-hidden rounded-2xl border border-[#3e2121] bg-[#2b1a1a]/50 backdrop-blur-sm">
                        <table className="w-full border-collapse text-left">
                            <thead>
                                <tr className="border-b border-[#3e2121] bg-[#2b1a1a]">
                                    <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Source</th>
                                    <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Currency</th>
                                    <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Status</th>
                                    <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-[#e8b4b4] text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#3e2121]">
                                {sources.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-12 text-center text-[#e8b4b4]">
                                            No income sources registered yet.
                                        </td>
                                    </tr>
                                ) : (
                                    sources.map((s) => (
                                        <tr key={s.id} className="hover:bg-white/5 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="font-bold text-white">{s.name}</div>
                                                <div className="text-xs text-[#e8b4b4]">{s.description || 'No description'}</div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="inline-flex items-center gap-1 rounded-full bg-[#1c0f0f] px-2.5 py-1 text-xs font-bold text-primary border border-primary/20">
                                                    {s.default_currency?.code}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-widest border ${s.is_active ? 'bg-primary/10 text-primary border-primary/20' : 'bg-rose-500/10 text-rose-400 border-rose-400/20'}`}>
                                                    {s.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Link
                                                        href={finance.incomeSources.destroy(s.id).url}
                                                        method="delete"
                                                        as="button"
                                                        className="rounded-lg p-2 text-rose-500 hover:bg-rose-500/10 transition-all"
                                                    >
                                                        <span className="material-symbols-outlined text-[20px]">delete</span>
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
