import { Head, Link, useForm } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import { PurchaseCategory } from '@/types/finance';
import finance from '@/routes/finance';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    categories: (PurchaseCategory & { purchases_count: number })[];
}

export default function PurchaseCategoriesIndex({ categories }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        icon: 'shopping_bag',
        color: '#ef4444',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(finance.categories.store().url, {
            onSuccess: () => reset(),
        });
    };

    return (
        <MainLayout>
            <Head title="Purchase Categories" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6 lg:p-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
                <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-black tracking-tight text-white">Categories</h1>
                        <p className="text-[#e8b4b4] font-medium mt-1">Organize your expenses with style</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-4">
                    {/* Create Form */}
                    <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6 shadow-xl h-fit">
                        <h2 className="mb-6 text-xl font-bold text-white flex items-center gap-2">
                            <span className="material-symbols-outlined text-primary">add_circle</span>
                            New Category
                        </h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary"
                                    placeholder="e.g. Food, Transport..."
                                    required
                                />
                                {errors.name && <p className="text-xs text-rose-400">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="icon" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Icon Name (Material)</Label>
                                <Input
                                    id="icon"
                                    value={data.icon || ''}
                                    onChange={(e) => setData('icon', e.target.value)}
                                    className="bg-[#1c0f0f] border-[#3e2121] text-white focus:ring-primary font-mono"
                                    placeholder="shopping_bag, home, etc."
                                />
                                {errors.icon && <p className="text-xs text-rose-400">{errors.icon}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="color" className="text-xs font-black uppercase tracking-widest text-[#e8b4b4]">Color</Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="color"
                                        type="color"
                                        value={data.color || '#ef4444'}
                                        onChange={(e) => setData('color', e.target.value)}
                                        className="w-12 h-10 p-1 bg-[#1c0f0f] border-[#3e2121] cursor-pointer"
                                    />
                                    <Input
                                        value={data.color || '#ef4444'}
                                        onChange={(e) => setData('color', e.target.value)}
                                        className="bg-[#1c0f0f] border-[#3e2121] text-white flex-1 font-mono"
                                    />
                                </div>
                                {errors.color && <p className="text-xs text-rose-400">{errors.color}</p>}
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-primary font-black text-white hover:bg-primary/90 shadow-[0_0_15px_rgba(239,68,68,0.2)]"
                            >
                                {processing ? 'Creating...' : 'Create Category'}
                            </Button>
                        </form>
                    </div>

                    {/* Categories Grid */}
                    <div className="lg:col-span-3 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        {categories.length === 0 ? (
                            <div className="col-span-full rounded-2xl border border-[#3e2121] border-dashed p-12 text-center text-[#e8b4b4]">
                                <span className="material-symbols-outlined text-4xl mb-2">category</span>
                                <p>No categories found. Create your first one to start tracking!</p>
                            </div>
                        ) : (
                            categories.map((c) => (
                                <div
                                    key={c.id}
                                    className="group relative overflow-hidden rounded-2xl border border-[#3e2121] bg-[#2b1a1a]/40 p-5 transition-all hover:bg-[#2b1a1a]/60 hover:border-primary/30"
                                >
                                    <div
                                        className="absolute top-0 left-0 w-1 h-full"
                                        style={{ backgroundColor: c.color || '#ef4444' }}
                                    />
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-4">
                                            <div
                                                className="flex h-12 w-12 items-center justify-center rounded-xl border border-white/5"
                                                style={{ backgroundColor: `${c.color || '#ef4444'}20`, color: c.color || '#ef4444' }}
                                            >
                                                <span className="material-symbols-outlined">{c.icon || 'category'}</span>
                                            </div>
                                            <div>
                                                <h3 className="font-bold text-white group-hover:text-primary transition-colors">{c.name}</h3>
                                                <p className="text-xs text-[#e8b4b4] font-medium">{c.purchases_count} transactions</p>
                                            </div>
                                        </div>

                                        <Link
                                            href={finance.categories.destroy(c.id).url}
                                            method="delete"
                                            as="button"
                                            className="rounded-lg p-2 text-rose-500/50 hover:text-rose-400 hover:bg-rose-500/10 opacity-0 group-hover:opacity-100 transition-all"
                                        >
                                            <span className="material-symbols-outlined text-[18px]">delete</span>
                                        </Link>
                                    </div>

                                    <div className="mt-4 h-1 w-full rounded-full bg-white/5 overflow-hidden">
                                        <div
                                            className="h-full rounded-full opacity-30"
                                            style={{
                                                width: '100%',
                                                backgroundColor: c.color || '#ef4444'
                                            }}
                                        />
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
