import { Head, router, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GroceryLayout from '@/layouts/grocery-layout';
import groceryItems from '@/routes/grocery/items';
import { AiInsightCard } from '@/components/ai/AiInsightCard';

interface GroceryItem {
    id: number;
    name: string;
    category: string | null;
    current_stock: number;
    target_stock: number;
    unit: string | null;
    price: number | string | null;
    purchased_at: string | null;
}

interface PriceHistory {
    id: number;
    grocery_item_id: number;
    price: string;
    quantity: string;
    purchased_at: string;
    grocery_item: GroceryItem;
}

interface Props {
    items: GroceryItem[];
    categories: string[];
    history?: Record<string, PriceHistory[]>;
}

export default function GroceryPage({ items, categories, history = {} }: Props) {
    const [activeTab, setActiveTab] = useState<'inventory' | 'history'>('inventory');
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [isRestockModalOpen, setIsRestockModalOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState<string>('');
    const [editingItem, setEditingItem] = useState<GroceryItem | null>(null);
    const [groceryInsight, setGroceryInsight] = useState<string | null>(null);
    const [insightLoading, setInsightLoading] = useState(true);
    const { data, setData, post, put, processing, reset, errors, clearErrors } = useForm({
        name: '',
        category: '',
        current_stock: 0,
        target_stock: 1,
        unit: 'pcs',
        price: '',
    });

    const [restockData, setRestockData] = useState<{ id: number; quantity: number; price: number }[]>([]);

    useEffect(() => {
        fetch('/ai/insights/grocery')
            .then((res) => (res.ok ? res.json() : { insight: null }))
            .then((data) => setGroceryInsight(data.insight))
            .catch(() => {})
            .finally(() => setInsightLoading(false));
    }, []);

    const deficitItems = items.filter(item => item.current_stock < item.target_stock);

    const openRestockModal = () => {
        setRestockData(deficitItems.map(item => ({
            id: item.id,
            quantity: item.target_stock - item.current_stock,
            price: parseFloat(item.price?.toString() || '0') || 0
        })));
        setIsRestockModalOpen(true);
    };

    const handleRestockSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/grocery/bulk-restock', { items: restockData }, {
            onSuccess: () => setIsRestockModalOpen(false)
        });
    };

    const updateRestockItem = (index: number, field: 'quantity' | 'price', value: number) => {
        const newData = [...restockData];
        newData[index][field] = value;
        setRestockData(newData);
    };


    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingItem) {
            put(groceryItems.update.url(editingItem.id), {
                onSuccess: () => {
                    reset();
                    setIsAddModalOpen(false);
                    setEditingItem(null);
                },
            });
        } else {
            post(groceryItems.store.url(), {
                onSuccess: () => {
                    reset();
                    setIsAddModalOpen(false);
                },
            });
        }
    };

    const handleEdit = (item: GroceryItem) => {
        setEditingItem(item);
        setData({
            name: item.name,
            category: item.category || '',
            current_stock: item.current_stock,
            target_stock: item.target_stock,
            unit: item.unit || 'pcs',
            price: item.price?.toString() || '',
        });
        clearErrors();
        setIsAddModalOpen(true);
    };

    const handleAdd = () => {
        setEditingItem(null);
        reset();
        clearErrors();
        setIsAddModalOpen(true);
    };

    const handleConsume = (item: GroceryItem) => {
        if (item.current_stock > 0) {
            router.post(`/grocery/${item.id}/consume`);
        }
    };

    const handleDelete = (item: GroceryItem) => {
        if (confirm('Are you sure you want to delete this item?')) {
            router.delete(groceryItems.destroy.url(item.id));
        }
    };

    // Stats calculations
    const totalExpenditure = items.reduce((acc, item) => acc + ((parseFloat(item.price?.toString() || '0') || 0) * item.current_stock), 0);
    const totalItems = items.length;
    const lowStockItems = items.filter(i => i.current_stock < i.target_stock).length;

    const filteredItems = items.filter(item => {
        const matchesSearch = item.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            item.category?.toLowerCase().includes(searchQuery.toLowerCase());

        // Split item categories by comma/space and check if selected category is present
        const itemCategories = item.category?.toLowerCase().split(/[\s,]+/) || [];
        const matchesCategory = !selectedCategory || itemCategories.includes(selectedCategory.toLowerCase());

        return matchesSearch && matchesCategory;
    });

    const getCategoryIcon = (category: string | null) => {
        const cat = category?.toLowerCase() || '';
        if (cat.includes('produce') || cat.includes('veg')) return 'eco';
        if (cat.includes('protein') || cat.includes('meat')) return 'egg_alt';
        if (cat.includes('dairy')) return 'water_drop';
        if (cat.includes('grain')) return 'grain';
        return 'shopping_basket';
    };

    return (
        <GroceryLayout>
            <Head title="Groceries & Stock" />
            <div className="flex-1 w-full max-w-[1200px] mx-auto p-4 md:p-8 flex flex-col gap-8 animate-in fade-in slide-in-from-bottom-4 duration-700 text-white">
                {/* Page Header */}
                <header className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-white text-3xl md:text-5xl font-black tracking-tight leading-none">Grocery Tracker</h1>
                        <div className="flex items-center gap-2 text-[#e8b4b4] mt-2">
                            <button className="hover:text-white transition-colors p-1"><span className="material-symbols-outlined text-sm">arrow_back_ios</span></button>
                            <span className="text-sm font-black uppercase tracking-widest">{new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}</span>
                            <button className="hover:text-white transition-colors p-1"><span className="material-symbols-outlined text-sm">arrow_forward_ios</span></button>
                        </div>
                    </div>
                    <div className="flex gap-3">
                        <button
                            onClick={() => {
                                fetch('/ai/insights/grocery')
                                    .then((res) => (res.ok ? res.json() : { insight: null }))
                                    .then((data) => setGroceryInsight(data.insight))
                                    .catch(() => {});
                            }}
                            className="flex items-center gap-2 px-5 h-12 rounded-xl border border-[#3e2121] bg-[#2b1a1a] hover:bg-[#2a4d35] text-white text-xs font-black uppercase tracking-widest transition-all"
                        >
                            <span className="material-symbols-outlined text-lg">psychology</span>
                            AI Shopping List
                        </button>
                        <button className="flex items-center gap-2 px-5 h-12 rounded-xl border border-[#3e2121] bg-[#2b1a1a] hover:bg-[#2a4d35] text-white text-xs font-black uppercase tracking-widest transition-all">
                            <span className="material-symbols-outlined text-lg">download</span>
                            Export Report
                        </button>


                        <div className="flex bg-[#2b1a1a] rounded-xl p-1 border border-[#3e2121]">
                            <button
                                onClick={() => setActiveTab('inventory')}
                                className={`px-6 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all ${activeTab === 'inventory' ? 'bg-primary text-white shadow-lg' : 'text-[#e8b4b4] hover:text-white'}`}
                            >
                                Inventory
                            </button>
                            <button
                                onClick={() => {
                                    setActiveTab('history');
                                    router.visit('/grocery/history', { only: ['history'], preserveState: true });
                                }}
                                className={`px-6 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all ${activeTab === 'history' ? 'bg-primary text-white shadow-lg' : 'text-[#e8b4b4] hover:text-white'}`}
                            >
                                History
                            </button>
                        </div>


                        <button
                            onClick={openRestockModal}
                            className="flex items-center gap-2 px-5 h-12 rounded-xl border border-[#3e2121] bg-[#2b1a1a] hover:bg-[#2a4d35] text-white text-xs font-black uppercase tracking-widest transition-all relative"
                        >
                            <span className="material-symbols-outlined text-lg">shopping_cart_checkout</span>
                            Complete Shopping
                            {deficitItems.length > 0 && (
                                <span className="absolute -top-2 -right-2 bg-rose-500 text-white text-[10px] size-6 rounded-full flex items-center justify-center font-black border-2 border-[#1c0f0f]">
                                    {deficitItems.length}
                                </span>
                            )}
                        </button>

                        <Dialog open={isRestockModalOpen} onOpenChange={setIsRestockModalOpen}>
                            <DialogContent className="bg-[#1c0f0f] border-[#3e2121] text-white sm:max-w-[800px] p-8 rounded-2xl max-h-[90vh] overflow-y-auto">
                                <DialogHeader className="mb-6">
                                    <DialogTitle className="text-3xl font-black text-white uppercase tracking-tight">
                                        Restock Items
                                    </DialogTitle>
                                    <DialogDescription className="text-[#e8b4b4] text-xs font-bold uppercase tracking-widest mt-2 px-0">
                                        Confirm quantities bought and prices paid for your missing stock.
                                    </DialogDescription>
                                </DialogHeader>
                                <form onSubmit={handleRestockSubmit} className="grid gap-6">
                                    <div className="overflow-hidden rounded-xl border border-[#3e2121]">
                                        <table className="w-full text-left">
                                            <thead className="bg-[#2b1a1a]">
                                                <tr>
                                                    <th className="p-4 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em]">Item</th>
                                                    <th className="p-4 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em]">Deficit</th>
                                                    <th className="p-4 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em] w-32">Qty Bought</th>
                                                    <th className="p-4 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em] w-32">Price Paid</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-[#3e2121]">
                                                {restockData.map((data, index) => {
                                                    const item = items.find(i => i.id === data.id);
                                                    if (!item) return null;
                                                    return (
                                                        <tr key={item.id} className="bg-[#1c0f0f]/50">
                                                            <td className="p-4 font-bold text-sm">{item.name}</td>
                                                            <td className="p-4 font-bold text-rose-500">
                                                                -{item.target_stock - item.current_stock}
                                                            </td>
                                                            <td className="p-4">
                                                                <Input
                                                                    type="number"
                                                                    step="0.01"
                                                                    value={data.quantity}
                                                                    onChange={(e) => updateRestockItem(index, 'quantity', parseFloat(e.target.value) || 0)}
                                                                    className="bg-[#2b1a1a] border-[#3e2121] h-10 w-full text-center font-bold"
                                                                />
                                                            </td>
                                                            <td className="p-4">
                                                                <Input
                                                                    type="number"
                                                                    step="0.01"
                                                                    value={data.price}
                                                                    onChange={(e) => updateRestockItem(index, 'price', parseFloat(e.target.value) || 0)}
                                                                    className="bg-[#2b1a1a] border-[#3e2121] h-10 w-full text-center font-bold"
                                                                />
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                    <DialogFooter className="mt-4">
                                        <button
                                            type="submit"
                                            className="w-full bg-primary text-white py-5 rounded-xl font-black text-sm uppercase tracking-widest shadow-[0_0_20px_rgba(239,68,68,0.2)] hover:bg-[#dc2626] transition-all active:scale-95"
                                        >
                                            Confirm Restock & Update Prices
                                        </button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>

                        <Dialog open={isAddModalOpen} onOpenChange={(open) => {
                            if (!open) {
                                setIsAddModalOpen(false);
                                setEditingItem(null);
                                reset();
                            } else {
                                setIsAddModalOpen(true);
                            }
                        }}>
                            <DialogTrigger asChild>
                                <button
                                    onClick={handleAdd}
                                    className="flex items-center gap-2 px-6 h-12 rounded-xl bg-primary hover:bg-[#dc2626] text-white text-xs font-black uppercase tracking-widest transition-all shadow-[0_0_20px_rgba(239,68,68,0.3)] hover:scale-105 active:scale-95"
                                >
                                    <span className="material-symbols-outlined text-xl font-black">add</span>
                                    Add Item
                                </button>
                            </DialogTrigger>
                            <DialogContent className="bg-[#1c0f0f] border-[#3e2121] text-white sm:max-w-[450px] p-8 rounded-2xl">
                                <DialogHeader className="mb-6">
                                    <DialogTitle className="text-3xl font-black text-white uppercase tracking-tight">
                                        {editingItem ? 'Edit Item' : 'Add New Item'}
                                    </DialogTitle>
                                    <DialogDescription className="text-[#e8b4b4] text-xs font-bold uppercase tracking-widest mt-2 px-0">
                                        {editingItem ? 'Update the details for this item.' : 'Fill in the details for your new stock item.'}
                                    </DialogDescription>
                                </DialogHeader>
                                <form onSubmit={submit} className="grid gap-6">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name" className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Item Name</Label>
                                        <Input
                                            id="name"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            className="bg-[#2b1a1a] border-[#3e2121] text-white focus:ring-primary h-14 rounded-xl px-4 font-bold text-lg"
                                            placeholder="e.g. Greek Yogurt"
                                            required
                                        />
                                        {errors.name && <div className="text-rose-500 text-[10px] font-bold uppercase">{errors.name}</div>}
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="current_stock" className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Current Stock</Label>
                                            <Input
                                                id="current_stock"
                                                type="number"
                                                step="0.01"
                                                value={data.current_stock}
                                                onChange={(e) => setData('current_stock', parseFloat(e.target.value) || 0)}
                                                className="bg-[#2b1a1a] border-[#3e2121] text-white focus:ring-primary h-14 rounded-xl px-4 font-bold text-lg"
                                                required
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="target_stock" className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Target Stock</Label>
                                            <Input
                                                id="target_stock"
                                                type="number"
                                                step="0.01"
                                                value={data.target_stock}
                                                onChange={(e) => setData('target_stock', parseFloat(e.target.value) || 0)}
                                                className="bg-[#2b1a1a] border-[#3e2121] text-white focus:ring-primary h-14 rounded-xl px-4 font-bold text-lg"
                                                required
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="unit" className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Unit</Label>
                                            <Input
                                                id="unit"
                                                value={data.unit}
                                                onChange={(e) => setData('unit', e.target.value)}
                                                className="bg-[#2b1a1a] border-[#3e2121] text-white focus:ring-primary h-14 rounded-xl px-4 font-bold text-lg"
                                                placeholder="pcs, kg, etc."
                                            />
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="category" className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Category</Label>
                                            <Input
                                                id="category"
                                                list="category-suggestions"
                                                value={data.category}
                                                onChange={(e) => setData('category', e.target.value)}
                                                className="bg-[#2b1a1a] border-[#3e2121] text-white focus:ring-primary h-14 rounded-xl px-4 font-bold text-lg"
                                                placeholder="Dairy, Meat, etc."
                                            />
                                            <datalist id="category-suggestions">
                                                {categories.map((cat) => (
                                                    <option key={cat} value={cat} />
                                                ))}
                                            </datalist>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="price" className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Est. Price</Label>
                                            <Input
                                                id="price"
                                                type="number"
                                                step="0.01"
                                                value={data.price}
                                                onChange={(e) => setData('price', e.target.value)}
                                                className="bg-[#2b1a1a] border-[#3e2121] text-white focus:ring-primary h-14 rounded-xl px-4 font-bold text-lg"
                                                placeholder="0.00"
                                            />
                                        </div>
                                    </div>
                                    <DialogFooter className="mt-4">
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full bg-primary text-white py-5 rounded-xl font-black text-sm uppercase tracking-widest shadow-[0_0_20px_rgba(239,68,68,0.2)] hover:bg-[#dc2626] transition-all active:scale-95 disabled:opacity-50"
                                        >
                                            {editingItem ? 'Update Item' : 'Add to stock list'}
                                        </button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </header>

                {activeTab === 'inventory' ? (
                    <>
                        {/* Stats Cards */}
                        <section className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="rounded-2xl border border-[#3e2121] bg-[#2b1a1a] p-8 relative overflow-hidden group shadow-xl">
                                <div className="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-all duration-500 scale-125 group-hover:rotate-12">
                                    <span className="material-symbols-outlined text-7xl text-primary">payments</span>
                                </div>
                                <div className="flex flex-col gap-3 relative z-10">
                                    <p className="text-[#e8b4b4] text-xs font-black uppercase tracking-[0.2em]">Total Expenditure</p>
                                    <div className="flex items-end gap-3">
                                        <p className="text-white text-4xl font-black leading-none">${totalExpenditure.toFixed(2)}</p>
                                        <span className="flex items-center text-primary text-[10px] font-black bg-primary/10 px-2.5 py-1 rounded-full border border-primary/20 uppercase tracking-tighter">
                                            <span className="material-symbols-outlined text-sm mr-0.5">trending_up</span>
                                            +12%
                                        </span>
                                    </div>
                                    <p className="text-[#e8b4b4]/40 text-[10px] font-bold uppercase tracking-widest mt-1">Reflected from purchased items</p>
                                </div>
                            </div>
                            <div className="rounded-2xl border border-[#3e2121] bg-[#2b1a1a] p-8 relative overflow-hidden group shadow-xl">
                                <div className="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-all duration-500 scale-125 group-hover:-rotate-12 text-white">
                                    <span className="material-symbols-outlined text-7xl">shopping_basket</span>
                                </div>
                                <div className="flex flex-col gap-3 relative z-10 text-white">
                                    <p className="text-[#e8b4b4] text-xs font-black uppercase tracking-[0.2em]">Total Items</p>
                                    <p className="text-white text-4xl font-black leading-none">{totalItems}</p>
                                    <p className="text-[#e8b4b4]/40 text-[10px] font-bold uppercase tracking-widest mt-1">Current Stock Level</p>
                                </div>
                            </div>
                            <div className="rounded-2xl border border-[#3e2121] bg-[#2b1a1a] p-8 relative overflow-hidden group shadow-xl">
                                <div className="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-all duration-500 scale-125 group-hover:rotate-6 text-white">
                                    <span className="material-symbols-outlined text-7xl">analytics</span>
                                </div>
                                <div className="flex flex-col gap-3 relative z-10 text-white">
                                    <p className="text-[#e8b4b4] text-xs font-black uppercase tracking-[0.2em]">Low Stock Items</p>
                                    <p className="text-white text-4xl font-black leading-none">{lowStockItems}</p>
                                    <p className="text-[#e8b4b4]/40 text-[10px] font-bold uppercase tracking-widest mt-1">Need Restocking</p>
                                </div>
                            </div>
                        </section>

                        {/* AI Restock Suggestions */}
                        {(groceryInsight || insightLoading) && (
                            <AiInsightCard
                                title="AI Restock Suggestions"
                                insight={groceryInsight}
                                loading={insightLoading}
                                icon="shopping_cart"
                            />
                        )}

                        {Object.keys(history).length > 0 && (
                            <section className="rounded-2xl bg-card border border-border p-6">
                                <h3 className="text-sm font-black uppercase tracking-widest text-white mb-4 flex items-center gap-2">
                                    <span className="material-symbols-outlined text-primary">trending_up</span>
                                    Price History · Last 7 Purchases
                                </h3>
                                <div className="flex items-end gap-1 h-16">
                                    {Object.entries(history).slice(0, 7).map(([date, items]) => {
                                        const total = items.reduce((s, i) => s + parseFloat(i.price) * parseFloat(i.quantity), 0);
                                        const max = Math.max(...Object.values(history).flat().map(i => parseFloat(i.price) * parseFloat(i.quantity)), 1);
                                        const h = Math.max(8, (total / max) * 100);
                                        return (
                                            <div key={date} className="flex-1 flex flex-col items-center gap-1">
                                                <div className="w-full rounded-t bg-primary/80 hover:bg-primary transition-colors" style={{ height: `${h}%` }} title={`${new Date(date).toLocaleDateString()} $${total.toFixed(2)}`}></div>
                                                <span className="text-[8px] font-bold text-muted-foreground">{new Date(date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </section>
                        )}

                        {/* Receipt Upload Zone */}
                        {/* <section className="rounded-2xl border-2 border-dashed border-[#3e2121] hover:border-primary/50 bg-[#2b1a1a]/30 transition-all cursor-pointer group shadow-inner">
                            <div className="flex flex-col items-center justify-center py-12 px-4 text-center gap-5">
                                <div className="size-20 rounded-full bg-[#2b1a1a] border border-[#3e2121] flex items-center justify-center group-hover:scale-110 group-hover:border-primary/50 transition-all duration-500 shadow-lg">
                                    <span className="material-symbols-outlined text-4xl text-[#e8b4b4] group-hover:text-primary transition-colors">receipt_long</span>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <h3 className="text-white text-xl font-black tracking-tight uppercase">Upload Receipt</h3>
                                    <p className="text-[#e8b4b4] text-xs font-bold uppercase tracking-widest opacity-60">Drag & drop or click to scan (AI Processing)</p>
                                </div>
                                <button className="text-[10px] font-black text-primary hover:text-white uppercase tracking-[0.2em] mt-2 transition-colors">Browse Files</button>
                            </div>
                        </section> */}

                        {/* Data Table Section */}
                        <section className="flex flex-col gap-6">
                            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <h2 className="text-white text-2xl font-black uppercase tracking-tight">Active Stock & History</h2>
                                <div className="flex gap-3">
                                    <div className="relative">
                                        <span className="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-[#e8b4b4] text-xl">search</span>
                                        <input
                                            className="bg-[#2b1a1a] border border-[#3e2121] text-white text-sm rounded-xl pl-12 pr-6 py-3.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none placeholder-[#e8b4b4]/30 w-full md:w-80 font-bold transition-all"
                                            placeholder="Search items..."
                                            type="text"
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                        />
                                    </div>
                                    <select
                                        value={selectedCategory}
                                        onChange={(e) => setSelectedCategory(e.target.value)}
                                        className="bg-[#2b1a1a] border border-[#3e2121] text-white text-sm rounded-xl px-4 py-3.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none font-bold transition-all cursor-pointer"
                                    >
                                        <option value="">All Categories</option>
                                        {categories.map((cat) => (
                                            <option key={cat} value={cat}>{cat}</option>
                                        ))}
                                    </select>
                                    <button className="p-3.5 rounded-xl border border-[#3e2121] bg-[#2b1a1a] text-[#e8b4b4] hover:text-white hover:bg-[#2a4d35] outline-none transition-all">
                                        <span className="material-symbols-outlined text-2xl">filter_list</span>
                                    </button>
                                </div>
                            </div>

                            <div className="rounded-2xl border border-[#3e2121] bg-[#2b1a1a] overflow-hidden shadow-2xl">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left border-collapse">
                                        <thead>
                                            <tr className="bg-[#1c0f0f]/50 border-b border-[#3e2121]">
                                                <th className="p-5 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em]">Item Name</th>
                                                <th className="p-5 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em]">Category</th>
                                                <th className="p-5 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em]">Stock Level</th>
                                                <th className="p-5 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em]">Unit</th>
                                                <th className="p-5 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em]">Est. Price</th>
                                                <th className="p-5 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em]">Total Value</th>
                                                <th className="p-5 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em] text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-[#3e2121]">
                                            {filteredItems.length > 0 ? filteredItems.map((item) => (
                                                <tr key={item.id} className="group hover:bg-white/[0.03] transition-colors">
                                                    <td className="p-5">
                                                        <div className="flex items-center gap-4">
                                                            <div className="size-10 rounded-xl bg-[#3e2121] flex items-center justify-center shrink-0 border border-[#3e2121] shadow-lg group-hover:scale-110 transition-transform">
                                                                <span className="material-symbols-outlined text-white text-xl">{getCategoryIcon(item.category)}</span>
                                                            </div>
                                                            <span className="text-white font-bold text-base">{item.name}</span>
                                                        </div>
                                                    </td>
                                                    <td className="p-5">
                                                        <span className="inline-flex items-center rounded-lg bg-[#3e2121] px-3 py-1.5 text-[10px] font-black text-primary uppercase tracking-widest ring-1 ring-inset ring-[#3e2121] shadow-sm">
                                                            {item.category || 'Other'}
                                                        </span>
                                                    </td>
                                                    <td className="p-5">
                                                        <div className="flex items-center gap-3">
                                                            <span className={`text-xl font-black ${item.current_stock < item.target_stock ? 'text-rose-500' : 'text-primary'}`}>
                                                                {item.current_stock}
                                                            </span>
                                                            <span className="text-[#e8b4b4]/50 text-xs font-bold uppercase">/ {item.target_stock}</span>
                                                        </div>
                                                        {item.current_stock < item.target_stock && (
                                                            <span className="text-rose-500 text-[10px] font-black uppercase tracking-widest mt-1 block">Low Stock</span>
                                                        )}
                                                    </td>
                                                    <td className="p-5 text-[#e8b4b4] font-black text-[10px] uppercase tracking-wider">{item.unit}</td>
                                                    <td className="p-5 text-white font-bold text-sm tracking-tight">${item.price || '0.00'}</td>
                                                    <td className="p-5 text-primary font-black text-lg tracking-tight">
                                                        ${((parseFloat(item.price?.toString() || '0') || 0) * item.current_stock).toFixed(2)}
                                                    </td>
                                                    <td className="p-5 text-right">
                                                        <div className="flex justify-end gap-2">
                                                            <button
                                                                onClick={() => handleConsume(item)}
                                                                className="size-8 rounded-lg bg-[#2b1a1a] border border-[#3e2121] flex items-center justify-center text-white hover:bg-rose-500/20 hover:border-rose-500 hover:text-rose-500 transition-all"
                                                                title="Consume 1 Unit"
                                                            >
                                                                <span className="material-symbols-outlined text-lg">water_drop</span>
                                                            </button>
                                                            <button
                                                                onClick={() => handleEdit(item)}
                                                                className="text-[#e8b4b4] hover:text-white p-2 rounded-lg hover:bg-white/10 transition-all opacity-40 group-hover:opacity-100"
                                                            >
                                                                <span className="material-symbols-outlined text-xl">edit</span>
                                                            </button>
                                                            <button
                                                                onClick={() => handleDelete(item)}
                                                                className="text-[#e8b4b4] hover:text-rose-500 p-2 rounded-lg hover:bg-rose-500/10 transition-all opacity-40 group-hover:opacity-100"
                                                            >
                                                                <span className="material-symbols-outlined text-xl">delete</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )) : (
                                                <tr>
                                                    <td colSpan={8} className="p-5 text-center text-white/50">No items found</td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="bg-[#1c0f0f]/30 p-5 flex items-center justify-between border-t border-[#3e2121]">
                                    <span className="text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em]">Showing {filteredItems.length} of {items.length} records</span>
                                    <div className="flex gap-2">
                                        <button disabled className="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4] rounded-lg hover:bg-white/5 disabled:opacity-30 transition-all">Prev</button>
                                        <button className="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white bg-primary rounded-lg shadow-lg">1</button>
                                        <button disabled className="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-[#e8b4b4] rounded-lg hover:bg-white/5 disabled:opacity-30 transition-all">Next</button>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </>
                ) : (
                    <div className="grid gap-6 pb-20">
                        {Object.entries(history).length === 0 ? (
                            <div className="text-center p-12 bg-[#2b1a1a] rounded-3xl border border-[#3e2121]">
                                <span className="material-symbols-outlined text-6xl text-[#e8b4b4]/20 mb-4">history</span>
                                <p className="text-[#e8b4b4] font-black text-lg uppercase tracking-widest">No Purchase History</p>
                            </div>
                        ) : (
                            Object.entries(history).map(([date, items]) => {
                                const total = items.reduce((sum, item) => sum + (parseFloat(item.price) * parseFloat(item.quantity)), 0);
                                return (
                                    <div key={date} className="bg-[#2b1a1a] border border-[#3e2121] rounded-3xl overflow-hidden shadow-xl">
                                        <div className="p-6 bg-[#1c0f0f]/50 border-b border-[#3e2121] flex justify-between items-center">
                                            <div className="flex items-center gap-4">
                                                <div className="size-12 rounded-xl bg-[#3e2121] flex items-center justify-center border border-[#3e2121]">
                                                    <span className="material-symbols-outlined text-white">receipt_long</span>
                                                </div>
                                                <div>
                                                    <p className="text-[#e8b4b4] text-[10px] font-black uppercase tracking-widest">Date of Purchase</p>
                                                    <p className="text-white font-bold text-xl">{new Date(date).toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-[#e8b4b4] text-[10px] font-black uppercase tracking-widest">Total Spent</p>
                                                <p className="text-primary font-black text-3xl tracking-tight">${total.toFixed(2)}</p>
                                            </div>
                                        </div>
                                        <div className="p-6">
                                            <table className="w-full text-left">
                                                <thead className="bg-[#1c0f0f]/30">
                                                    <tr>
                                                        <th className="p-3 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em]">Item</th>
                                                        <th className="p-3 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em] text-center">Qty</th>
                                                        <th className="p-3 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em] text-right">Price</th>
                                                        <th className="p-3 text-[10px] font-black text-[#e8b4b4] uppercase tracking-[0.2em] text-right">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-[#3e2121]/50">
                                                    {items.map((item) => (
                                                        <tr key={item.id} className="hover:bg-white/[0.02]">
                                                            <td className="p-3 font-bold text-white text-sm">{item.grocery_item.name}</td>
                                                            <td className="p-3 font-bold text-white text-sm text-center">{item.quantity}</td>
                                                            <td className="p-3 font-bold text-[#e8b4b4] text-xs text-right">${parseFloat(item.price).toFixed(2)}</td>
                                                            <td className="p-3 font-black text-white text-sm text-right">
                                                                ${(parseFloat(item.price) * parseFloat(item.quantity)).toFixed(2)}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>
                )}
            </div>
        </GroceryLayout>
    );
}
