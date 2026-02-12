import { Head, Link, useForm } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { WithdrawalCategory } from '@/types/finance';
import finance from '@/routes/finance';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    categories: (WithdrawalCategory & { withdrawals_count: number })[];
}

export default function WithdrawalCategoriesIndex({ categories }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        color: '#92c9a4',
        icon: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(finance.withdrawalCategories.store().url, {
            onSuccess: () => reset(),
        });
    };

    return (
        <MainLayout>
            <Head title="Withdrawal Categories" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in duration-700">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div className="flex items-center gap-4">
                        <Link href={finance.withdrawals.index().url} className="rounded-full p-2 hover:bg-white/5 text-[#92c9a4] transition-all">
                            <span className="material-symbols-outlined">arrow_back</span>
                        </Link>
                        <div>
                            <h2 className="text-3xl font-black tracking-tight text-white lg:text-4xl">
                                Categories</h2>
                            <p className="mt-1 text-base font-medium text-[#92c9a4]">
                                Organize your withdrawals and services.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    {/* Create Form */}
                    <div className="rounded-2xl bg-[#193322] border border-[#23482f] p-6 h-fit shadow-xl">
                        <h3 className="mb-6 text-sm font-black uppercase tracking-widest text-white">Add New Category</h3>
                        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="name" className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    className="bg-[#102216] border-[#23482f] text-white h-10"
                                    placeholder="e.g. Rent, Utilities"
                                    required
                                />
                                {errors.name && <span className="text-[10px] font-bold text-rose-500">{errors.name}</span>}
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="color" className="text-[10px] font-black uppercase tracking-widest text-[#92c9a4]">Color</Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="color"
                                        type="color"
                                        value={data.color}
                                        onChange={e => setData('color', e.target.value)}
                                        className="bg-[#102216] border-[#23482f] h-10 w-16 p-1 cursor-pointer"
                                        required
                                    />
                                    <Input
                                        value={data.color}
                                        onChange={e => setData('color', e.target.value)}
                                        className="bg-[#102216] border-[#23482f] text-white h-10 uppercase"
                                        maxLength={7}
                                    />
                                </div>
                            </div>
                            <Button disabled={processing} className="mt-2 bg-primary font-black text-[#102216] hover:bg-green-400">
                                {processing ? '...' : 'Create Category'}
                            </Button>
                        </form>
                    </div>

                    {/* Categories List */}
                    <div className="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {categories.map((category) => (
                            <div key={category.id} className="relative overflow-hidden rounded-xl bg-[#193322] border border-[#23482f] p-4 group hover:border-primary/30 transition-all">
                                <div className="flex items-center justify-between mb-2">
                                    <div className="flex items-center gap-3">
                                        <div
                                            className="h-10 w-10 rounded-lg flex items-center justify-center shadow-lg"
                                            style={{ backgroundColor: category.color }}
                                        >
                                            <span className="material-symbols-outlined text-[#102216] font-bold">
                                                {category.icon || 'category'}
                                            </span>
                                        </div>
                                        <div>
                                            <h4 className="text-white font-bold">{category.name}</h4>
                                            <p className="text-xs text-[#92c9a4] font-medium">{category.withdrawals_count} withdrawals</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center">
                                        <Link
                                            href={finance.withdrawalCategories.destroy(category.id).url}
                                            method="delete"
                                            as="button"
                                            className="text-rose-500 hover:text-rose-400 opacity-0 group-hover:opacity-100 transition-opacity p-2"
                                        >
                                            <span className="material-symbols-outlined text-[20px]">delete</span>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
