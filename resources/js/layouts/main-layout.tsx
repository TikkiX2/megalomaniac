import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';

interface MainLayoutProps {
    children: ReactNode;
}

export default function MainLayout({ children }: MainLayoutProps) {
    const { url, props } = usePage();
    const { auth } = props as any;
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    const navItems = [
        { name: 'Dashboard', icon: 'dashboard', href: '/dashboard' },
        { name: 'Freelance', icon: 'work', href: '/freelance/dashboard' },
        { name: 'Finance', icon: 'payments', href: '/finance/dashboard' },
        { name: 'Workout Log', icon: 'exercise', href: '/fitness/gym' },
        { name: 'Nutrition', icon: 'restaurant', href: '/fitness/nutrition' },
        { name: 'Supplements', icon: 'medication', href: '/fitness/supplements' },
        { name: 'Grocery List', icon: 'shopping_cart', href: '/fitness/groceries' },
    ];

    return (
        <div className="flex h-screen w-full overflow-hidden bg-[#102216] text-white antialiased selection:bg-primary selection:text-[#102216]">
            {/* Sidebar Navigation */}
            <aside className={`fixed inset-y-0 left-0 z-50 w-64 flex-col border-r border-[#23482f] bg-[#102216] transition-transform lg:static lg:flex ${isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}`}>
                <div className="flex bg-[#102216] h-full flex-col justify-between p-4">
                    <div className="flex flex-col gap-8">
                        {/* Brand */}
                        <div className="flex items-center gap-3 px-2">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary shadow-[0_0_15px_rgba(19,236,91,0.3)]">
                                <span className="material-symbols-outlined text-[#102216] font-bold">bolt</span>
                            </div>
                            <div className="flex flex-col">
                                <h1 className="text-base font-bold leading-tight text-white">Megalomaniac</h1>
                            </div>
                        </div>

                        {/* Nav Links */}
                        <nav className="flex flex-col gap-1.5">
                            {navItems.map((item) => {
                                const isActive = url.startsWith(item.href);
                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className={`group flex items-center gap-3 rounded-lg px-3 py-2.5 transition-all duration-200 ${isActive
                                            ? 'bg-[#193322] border border-[#23482f] text-white shadow-sm'
                                            : 'text-[#92c9a4] hover:bg-white/5 hover:text-white'
                                            }`}
                                    >
                                        <span className={`material-symbols-outlined text-[22px] transition-colors ${isActive ? 'text-primary fill-1' : 'group-hover:text-primary'}`}>
                                            {item.icon}
                                        </span>
                                        <span className="text-sm font-semibold">{item.name}</span>
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>

                    <div className="flex flex-col gap-2">
                        <Link
                            href="/settings/profile"
                            className="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-[#92c9a4] transition-colors hover:bg-white/5 hover:text-white"
                        >
                            <span className="material-symbols-outlined text-[22px]">settings</span>
                            <span className="text-sm font-semibold">Settings</span>
                        </Link>

                        {/* User Profile */}
                        <div className="mt-4 flex items-center gap-3 border-t border-[#23482f] pt-4">
                            <div className="h-10 w-10 overflow-hidden rounded-full border-2 border-[#23482f] bg-primary/10 flex items-center justify-center text-primary font-bold shrink-0">
                                {auth.user.avatar ? (
                                    <img
                                        src={auth.user.avatar}
                                        alt={auth.user.name}
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    auth.user.name.charAt(0)
                                )}
                            </div>
                            <div className="flex flex-col overflow-hidden text-left">
                                <p className="truncate text-sm font-bold text-white leading-tight">{auth.user.name}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            {/* Mobile Overlay */}
            {isMobileMenuOpen && (
                <div
                    className="fixed inset-0 z-40 bg-[#102216]/60 backdrop-blur-sm lg:hidden transition-opacity duration-300"
                    onClick={() => setIsMobileMenuOpen(false)}
                />
            )}

            {/* Main Content Area */}
            <main className="flex h-full flex-1 flex-col overflow-y-auto bg-[#102216]">
                {/* Mobile Header */}
                <header className="flex items-center justify-between border-b border-[#23482f] bg-[#102216] p-4 lg:hidden sticky top-0 z-10">
                    <div className="flex items-center gap-3">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary">
                            <span className="material-symbols-outlined text-[#102216] text-[18px] font-bold">bolt</span>
                        </div>
                        <span className="text-lg font-bold text-white">Megalomaniac</span>
                    </div>
                    <button
                        onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                        className="rounded-lg p-1.5 text-white bg-white/5 hover:bg-white/10 active:scale-95 transition-all"
                    >
                        <span className="material-symbols-outlined">{isMobileMenuOpen ? 'close' : 'menu'}</span>
                    </button>
                </header>

                <div className="relative z-0 h-full">
                    {children}
                </div>
            </main>
        </div>
    );
}
