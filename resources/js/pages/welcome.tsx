import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login, register } from '@/routes';
import type { SharedData } from '@/types';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage<SharedData>().props;

    return (
        <div className="flex min-h-screen flex-col items-center bg-background text-white p-6 lg:justify-center lg:p-8 selection:bg-primary selection:text-white antialiased">
            <Head title="Welcome to Megalomaniac Pro">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>

            <header className="mb-12 w-full max-w-4xl text-sm">
                <nav className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary shadow-[0_0_15px_rgba(239,68,68,0.3)]">
                            <span className="material-symbols-outlined text-white font-bold">bolt</span>
                        </div>
                        <span className="text-xl font-black text-white tracking-tighter">Megalomaniac<span className="text-primary">Pro</span></span>
                    </div>
                    <div className="flex items-center gap-6">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-block rounded-xl border border-border px-6 py-2.5 text-sm font-bold text-white hover:bg-white/5 transition-all"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <div className="flex items-center gap-4">
                                <Link
                                    href={login()}
                                    className="text-sm font-bold text-muted-foreground hover:text-white transition-colors"
                                >
                                    Log in
                                </Link>
                                {canRegister && (
                                    <Link
                                        href={register()}
                                        className="inline-block rounded-xl bg-primary px-6 py-2.5 text-sm font-black text-white shadow-[0_0_15px_rgba(239,68,68,0.2)] hover:bg-primary/90 transition-all active:scale-95"
                                    >
                                        Get Started
                                    </Link>
                                )}
                            </div>
                        )}
                    </div>
                </nav>
            </header>

            <main className="flex w-full max-w-5xl flex-col items-center gap-16 lg:flex-row">
                <div className="flex-1 flex flex-col gap-8 animate-in slide-in-from-left-8 duration-1000">
                    <div className="flex flex-col gap-4">
                        <span className="px-3 py-1 bg-primary/10 border border-primary/20 rounded-full text-[10px] font-black uppercase tracking-widest text-primary w-fit">Version 2.0 Now Live</span>
                        <h1 className="text-6xl font-black leading-[1.05] tracking-tight text-white lg:text-7xl">
                            Unlock Your <br />
                            <span className="text-primary italic">Peak Potential.</span>
                        </h1>
                        <p className="text-lg font-medium text-muted-foreground max-w-md leading-relaxed">
                            The elite fitness companion for professionals. Track workouts, optimize nutrition, and monitor your stack with precision.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-4">
                        <Link
                            href={register()}
                            className="inline-block rounded-2xl bg-primary px-10 py-5 text-lg font-black text-white shadow-[0_8px_30px_rgba(239,68,68,0.3)] hover:bg-primary/90 hover:-translate-y-1 transition-all active:translate-y-0"
                        >
                            Start Free Trial
                        </Link>
                        <button className="inline-block rounded-2xl border border-border bg-card px-10 py-5 text-lg font-black text-white hover:bg-white/5 transition-all">
                            View Features
                        </button>
                    </div>

                    <div className="flex items-center gap-6 mt-4">
                        <div className="flex -space-x-3">
                            {[1, 2, 3, 4].map(i => (
                                <div key={i} className="h-10 w-10 rounded-full border-2 border-background bg-gray-800" />
                            ))}
                        </div>
                        <p className="text-sm font-bold text-muted-foreground">Joined by <span className="text-white">+2,400</span> athletes</p>
                    </div>
                </div>

                <div className="relative flex-1 w-full max-w-md lg:max-w-none animate-in fade-in zoom-in duration-1000 delay-300">
                    <div className="aspect-[4/5] rounded-[2rem] bg-gradient-to-br from-card to-background border border-border shadow-2xl overflow-hidden relative group">
                        <div className="absolute inset-0 bg-cover bg-center opacity-30 group-hover:scale-110 transition-transform duration-1000" style={{ backgroundImage: "url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&q=80')" }}></div>
                        <div className="absolute inset-0 bg-gradient-to-t from-background via-transparent to-transparent"></div>

                        <div className="absolute bottom-8 left-8 right-8 bg-white/5 backdrop-blur-md rounded-2xl border border-white/10 p-6">
                            <div className="flex items-center gap-4 mb-3">
                                <div className="h-10 w-10 rounded-full bg-primary flex items-center justify-center">
                                    <span className="material-symbols-outlined text-white font-bold">trending_up</span>
                                </div>
                                <div className="flex flex-col">
                                    <span className="text-xs font-black uppercase tracking-widest text-muted-foreground">Daily Goal</span>
                                    <span className="text-white font-bold">Chest & Triceps Focus</span>
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <div className="flex-1 h-1.5 rounded-full bg-primary/20">
                                    <div className="h-full w-4/5 bg-primary rounded-full shadow-[0_0_8px_rgba(239,68,68,0.5)]"></div>
                                </div>
                                <span className="text-[10px] font-black text-white">80%</span>
                            </div>
                        </div>
                    </div>

                    {/* Floating elements */}
                    <div className="absolute -top-6 -right-6 bg-primary text-white font-black p-4 rounded-2xl rotate-12 shadow-xl animate-bounce">
                        New Stack!
                    </div>
                </div>
            </main>

            <footer className="mt-20 w-full max-w-4xl border-t border-border pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
                <p className="text-xs font-bold text-muted-foreground uppercase tracking-widest">© 2026 Megalomaniac Pro. Peak Performance Guaranteed.</p>
                <div className="flex gap-6 text-xs font-black uppercase tracking-widest text-muted-foreground">
                    <a href="#" className="hover:text-white transition-colors">Privacy</a>
                    <a href="#" className="hover:text-white transition-colors">Terms</a>
                    <a href="#" className="hover:text-white transition-colors">Twitter</a>
                </div>
            </footer>
        </div>
    );
}
