import GroceryLayout from '@/layouts/grocery-layout';
import { Head } from '@inertiajs/react';

interface Props {
    items: any[];
}

export default function GroceryPage({ items }: Props) {
    return (
        <GroceryLayout>
            <Head title="Groceries & Stock" />
            <div className="p-6 max-w-7xl mx-auto animate-in fade-in slide-in-from-bottom-4 duration-700 text-white">
                <header className="flex justify-between items-end mb-10">
                    <div>
                        <h1 className="text-4xl font-black tracking-tight leading-none text-white">Grocery List</h1>
                        <p className="mt-2 text-[#92c9a4] font-medium uppercase text-xs tracking-widest flex items-center gap-2">
                            <span className="material-symbols-outlined text-[16px] text-primary">inventory_2</span>
                            Household Essentials & Stock
                        </p>
                    </div>
                    <button className="bg-primary text-[#102216] px-6 py-2.5 rounded-xl font-black text-sm shadow-[0_0_15px_rgba(19,236,91,0.2)] hover:bg-green-400 transition-all active:scale-95">
                        Add Item
                    </button>
                </header>

                <div className="grid grid-cols-1 gap-8">
                    <section className="bg-[#193322] rounded-2xl shadow-xl border border-[#23482f] overflow-hidden">
                        <div className="p-6 border-b border-[#23482f] bg-white/5 flex items-center justify-between">
                            <h2 className="text-lg font-black text-white leading-none">Shopping Items</h2>
                            <div className="text-[10px] font-black text-[#92c9a4] uppercase tracking-widest">{items.filter(i => i.is_purchased).length} / {items.length} Completed</div>
                        </div>
                        <div className="divide-y divide-[#23482f]">
                            {items.length > 0 ? items.map(item => (
                                <div key={item.id} className={`p-6 flex items-center gap-6 hover:bg-white/5 transition-all group ${item.is_purchased ? 'bg-black/10' : ''}`}>
                                    <div className="relative flex items-center justify-center">
                                        <input
                                            type="checkbox"
                                            readOnly
                                            checked={item.is_purchased}
                                            className="h-6 w-6 rounded-lg border-[#23482f] bg-[#102216] text-primary focus:ring-primary focus:ring-offset-[#102216] cursor-pointer appearance-none checked:bg-primary checked:border-primary transition-all transition-bounce"
                                        />
                                        {item.is_purchased && <span className="material-symbols-outlined absolute text-[#102216] font-black leading-none text-base pointer-events-none">check</span>}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className={`text-lg font-bold truncate ${item.is_purchased ? 'text-[#92c9a4] line-through' : 'text-white'}`}>
                                            {item.name}
                                        </p>
                                        <div className="flex items-center gap-2 mt-1">
                                            <span className="text-[10px] font-black text-[#92c9a4] uppercase tracking-wider">{item.quantity} {item.unit}</span>
                                            <span className="text-[#23482f] text-[10px]">•</span>
                                            <span className="text-[10px] font-black text-primary uppercase tracking-widest">{item.category}</span>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-6">
                                        {item.price && <span className="text-xl font-black text-white tracking-widest">${item.price}</span>}
                                        <button className="text-[#23482f] hover:text-rose-500 transition-colors p-1">
                                            <span className="material-symbols-outlined">delete</span>
                                        </button>
                                    </div>
                                </div>
                            )) : (
                                <div className="p-20 text-center opacity-40">
                                    <div className="bg-[#102216] w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 border border-[#23482f]">
                                        <span className="material-symbols-outlined text-4xl">shopping_cart_off</span>
                                    </div>
                                    <p className="text-lg font-black text-white mb-1 uppercase tracking-widest leading-none">Empty List</p>
                                    <p className="text-xs font-bold text-[#92c9a4] uppercase tracking-tighter italic">Your shopping stack is ready for new items</p>
                                </div>
                            )}
                        </div>
                    </section>
                </div>

                {/* Visual Accent */}
                <div className="mt-10 grid grid-cols-1 md:grid-cols-3 gap-6 opacity-80">
                    <div className="bg-[#193322] p-6 rounded-2xl border border-[#23482f] flex gap-4 items-center">
                        <div className="bg-primary/10 text-primary p-3 rounded-xl border border-primary/20">
                            <span className="material-symbols-outlined">local_shipping</span>
                        </div>
                        <div>
                            <p className="text-[10px] font-black text-[#92c9a4] uppercase tracking-widest">Efficiency</p>
                            <p className="text-white font-bold leading-tight">Optimized Route</p>
                        </div>
                    </div>
                    <div className="bg-[#193322] p-6 rounded-2xl border border-[#23482f] flex gap-4 items-center">
                        <div className="bg-primary/10 text-primary p-3 rounded-xl border border-primary/20">
                            <span className="material-symbols-outlined">savings</span>
                        </div>
                        <div>
                            <p className="text-[10px] font-black text-[#92c9a4] uppercase tracking-widest">Monthly Budget</p>
                            <p className="text-white font-bold leading-tight">$420 Remaining</p>
                        </div>
                    </div>
                </div>
            </div>
        </GroceryLayout>
    );
}
