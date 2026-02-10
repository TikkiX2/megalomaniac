import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import SupplementLayout from '@/layouts/supplement-layout';

interface Supplement {
    id: number;
    name: string;
    brand: string | null;
    dosage_amount: string | null;
    frequency: string | null;
    stock_quantity: number;
    low_stock_threshold: number;
    image_url: string | null;
}

interface Props {
    supplements: Supplement[];
    recentLogs: any[];
}

export default function SupplementPage({ supplements, recentLogs }: Props) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingSupplement, setEditingSupplement] = useState<Supplement | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        brand: '',
        dosage_amount: '',
        frequency: '',
        stock_quantity: 0,
        low_stock_threshold: 5,
        image_url: '',
    });

    const handleOpenModal = (supplement: Supplement | null = null) => {
        if (supplement) {
            setEditingSupplement(supplement);
            setData({
                name: supplement.name,
                brand: supplement.brand || '',
                dosage_amount: supplement.dosage_amount || '',
                frequency: supplement.frequency || '',
                stock_quantity: supplement.stock_quantity,
                low_stock_threshold: supplement.low_stock_threshold,
                image_url: supplement.image_url || '',
            });
        } else {
            setEditingSupplement(null);
            reset();
        }
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setEditingSupplement(null);
        reset();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingSupplement) {
            put(`/supplements/items/${editingSupplement.id}`, {
                onSuccess: () => handleCloseModal(),
            });
        } else {
            post('/supplements/items', {
                onSuccess: () => handleCloseModal(),
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this supplement?')) {
            router.delete(`/supplements/items/${id}`);
        }
    };

    const handleLogIntake = (id: number) => {
        router.post(`/supplements/${id}/log`, {}, {
            preserveScroll: true,
        });
    };

    const stackStrength = supplements.length > 0
        ? Math.round((supplements.filter(s => s.stock_quantity > s.low_stock_threshold).length / supplements.length) * 100)
        : 100;

    return (
        <SupplementLayout>
            <Head title="Supplements" />
            <div className="p-6 max-w-7xl mx-auto animate-in fade-in slide-in-from-bottom-4 duration-700 text-white">
                <header className="flex justify-between items-end mb-10">
                    <div>
                        <h1 className="text-4xl font-black tracking-tight leading-none text-white">Supplements</h1>
                        <p className="mt-2 text-[#92c9a4] font-medium uppercase text-xs tracking-widest">Stack & Inventory</p>
                    </div>
                    <button
                        onClick={() => handleOpenModal()}
                        className="bg-primary text-[#102216] px-6 py-2.5 rounded-xl font-black text-sm shadow-[0_0_15px_rgba(19,236,91,0.2)] hover:bg-green-400 transition-all active:scale-95"
                    >
                        Add Supplement
                    </button>
                </header>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <div className="lg:col-span-8 flex flex-col gap-6">
                        <section className="bg-[#193322] rounded-2xl shadow-xl border border-[#23482f] overflow-hidden">
                            <div className="p-6 border-b border-[#23482f] bg-white/5">
                                <h2 className="text-lg font-black text-white leading-none">Inventory Stack</h2>
                            </div>
                            <div className="divide-y divide-[#23482f]">
                                {supplements.map(supp => (
                                    <div key={supp.id} className="p-6 flex justify-between items-center hover:bg-white/5 transition-all group">
                                        <div className="flex items-center gap-5">
                                            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#102216] border border-[#23482f] group-hover:border-primary/40 transition-colors">
                                                <span className="material-symbols-outlined text-primary text-[28px]">pill</span>
                                            </div>
                                            <div>
                                                <p className="font-black text-xl text-white group-hover:text-primary transition-colors">{supp.name}</p>
                                                <p className="text-xs font-bold text-[#92c9a4] uppercase tracking-tight">{supp.brand || 'Premium Stack'} • {supp.dosage_amount}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-8">
                                            <div className="text-right hidden sm:block">
                                                <p className={`text-lg font-black ${supp.stock_quantity <= supp.low_stock_threshold ? 'text-rose-500' : 'text-primary'}`}>
                                                    {supp.stock_quantity} <span className="text-xs font-bold text-[#92c9a4]">left</span>
                                                </p>
                                                <p className="text-[10px] font-bold text-[#92c9a4] uppercase tracking-tighter">Current Stock</p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <button
                                                    onClick={() => handleLogIntake(supp.id)}
                                                    className="bg-[#102216] text-white border border-[#23482f] px-5 py-2.5 rounded-xl font-black text-[11px] uppercase tracking-widest hover:border-primary/50 transition-all active:scale-95"
                                                >
                                                    Log Intake
                                                </button>
                                                <button
                                                    onClick={() => handleOpenModal(supp)}
                                                    className="p-2 text-[#92c9a4] hover:text-primary transition-colors"
                                                >
                                                    <span className="material-symbols-outlined text-xl">edit</span>
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(supp.id)}
                                                    className="p-2 text-[#92c9a4] hover:text-rose-500 transition-colors"
                                                >
                                                    <span className="material-symbols-outlined text-xl">delete</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>

                    <div className="lg:col-span-4">
                        <section className="bg-[#193322] p-6 rounded-2xl shadow-xl border border-[#23482f] sticky top-8">
                            <div className="flex items-center justify-between mb-8">
                                <h2 className="text-lg font-black text-white leading-none">Recent Activity</h2>
                                <span className="material-symbols-outlined text-primary">history</span>
                            </div>
                            <ul className="space-y-6">
                                {recentLogs.length > 0 ? recentLogs.map(log => (
                                    <li key={log.id} className="relative pl-6 before:absolute before:left-0 before:top-2 before:bottom-2 before:w-[2px] before:bg-primary before:rounded-full">
                                        <p className="font-bold text-white text-sm">{log.supplement?.name}</p>
                                        <p className="text-[10px] font-black text-[#92c9a4] uppercase tracking-wider mt-1">{new Date(log.taken_at).toLocaleString('en-US', { hour: '2-digit', minute: '2-digit', month: 'short', day: 'numeric' })}</p>
                                    </li>
                                )) : (
                                    <div className="py-20 text-center opacity-40">
                                        <span className="material-symbols-outlined text-5xl mb-2">event_busy</span>
                                        <p className="text-[10px] font-bold uppercase tracking-widest">No recent intake</p>
                                    </div>
                                )}
                            </ul>

                            <div className="mt-8 pt-8 border-t border-[#23482f]">
                                <div className="bg-[#102216] p-4 rounded-xl border border-[#23482f]">
                                    <p className="text-xs font-black text-[#92c9a4] uppercase tracking-widest mb-1">Stack Strength</p>
                                    <div className="text-2xl font-black text-white leading-none">{stackStrength}%</div>
                                    <div className="mt-3 h-1 w-full bg-[#193322] rounded-full">
                                        <div className="bg-primary h-full rounded-full" style={{ width: `${stackStrength}%` }}></div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            {/* Supplement Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
                    <div className="bg-[#102216] border border-[#23482f] rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col shadow-2xl animate-in fade-in zoom-in-95 duration-300">
                        <div className="p-6 border-b border-[#23482f] flex items-center justify-between bg-[#193322]">
                            <h3 className="text-2xl font-black text-white">
                                {editingSupplement ? 'Edit Supplement' : 'Add New Supplement'}
                            </h3>
                            <button
                                onClick={handleCloseModal}
                                className="text-[#92c9a4] hover:text-white transition-colors"
                            >
                                <span className="material-symbols-outlined">close</span>
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto p-6 md:p-8 space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <label className="text-xs font-black text-[#92c9a4] uppercase tracking-widest">Name</label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={e => setData('name', e.target.value)}
                                        className="w-full bg-[#193322] border border-[#23482f] rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary transition-all outline-none"
                                        placeholder="e.g. Whey Protein"
                                        required
                                    />
                                    {errors.name && <p className="text-red-400 text-xs mt-1">{errors.name}</p>}
                                </div>
                                <div className="space-y-2">
                                    <label className="text-xs font-black text-[#92c9a4] uppercase tracking-widest">Brand</label>
                                    <input
                                        type="text"
                                        value={data.brand}
                                        onChange={e => setData('brand', e.target.value)}
                                        className="w-full bg-[#193322] border border-[#23482f] rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary transition-all outline-none"
                                        placeholder="e.g. Optimum Nutrition"
                                    />
                                    {errors.brand && <p className="text-red-400 text-xs mt-1">{errors.brand}</p>}
                                </div>
                                <div className="space-y-2">
                                    <label className="text-xs font-black text-[#92c9a4] uppercase tracking-widest">Dosage</label>
                                    <input
                                        type="text"
                                        value={data.dosage_amount}
                                        onChange={e => setData('dosage_amount', e.target.value)}
                                        className="w-full bg-[#193322] border border-[#23482f] rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary transition-all outline-none"
                                        placeholder="e.g. 1 Scoop (30g)"
                                    />
                                    {errors.dosage_amount && <p className="text-red-400 text-xs mt-1">{errors.dosage_amount}</p>}
                                </div>
                                <div className="space-y-2">
                                    <label className="text-xs font-black text-[#92c9a4] uppercase tracking-widest">Frequency</label>
                                    <input
                                        type="text"
                                        value={data.frequency}
                                        onChange={e => setData('frequency', e.target.value)}
                                        className="w-full bg-[#193322] border border-[#23482f] rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary transition-all outline-none"
                                        placeholder="e.g. Daily"
                                    />
                                    {errors.frequency && <p className="text-red-400 text-xs mt-1">{errors.frequency}</p>}
                                </div>
                                <div className="space-y-2">
                                    <label className="text-xs font-black text-[#92c9a4] uppercase tracking-widest">Current Stock</label>
                                    <input
                                        type="number"
                                        value={data.stock_quantity}
                                        onChange={e => setData('stock_quantity', parseInt(e.target.value))}
                                        className="w-full bg-[#193322] border border-[#23482f] rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary transition-all outline-none"
                                        required
                                    />
                                    {errors.stock_quantity && <p className="text-red-400 text-xs mt-1">{errors.stock_quantity}</p>}
                                </div>
                                <div className="space-y-2">
                                    <label className="text-xs font-black text-[#92c9a4] uppercase tracking-widest">Low Stock Alert</label>
                                    <input
                                        type="number"
                                        value={data.low_stock_threshold}
                                        onChange={e => setData('low_stock_threshold', parseInt(e.target.value))}
                                        className="w-full bg-[#193322] border border-[#23482f] rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary transition-all outline-none"
                                        required
                                    />
                                    {errors.low_stock_threshold && <p className="text-red-400 text-xs mt-1">{errors.low_stock_threshold}</p>}
                                </div>
                            </div>
                        </form>

                        <div className="p-6 border-t border-[#23482f] bg-[#193322] flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={handleCloseModal}
                                className="px-6 py-2 rounded-xl font-bold text-[#92c9a4] hover:text-white transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleSubmit}
                                disabled={processing}
                                className="bg-primary hover:bg-primary-hover text-[#102216] px-8 py-2 rounded-xl font-black transition-all shadow-lg hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? 'Saving...' : (editingSupplement ? 'Update' : 'Save')}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </SupplementLayout>
    );
}
