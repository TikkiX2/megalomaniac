import { Loader2 } from 'lucide-react';
import { useState, useEffect } from 'react';
import { SuggestionCard } from '@/components/ai/SuggestionCard';

interface Suggestion {
    id: number;
    type: string;
    title: string;
    content: string;
    data: { action_url?: string } | null;
}

interface SuggestionListProps {
    className?: string;
}

export function SuggestionList({ className }: SuggestionListProps) {
    const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetch('/ai/suggestions')
            .then((res) => (res.ok ? res.json() : []))
            .then((data) => setSuggestions(data))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const handleDismiss = (id: number) => {
        setSuggestions((prev) => prev.filter((s) => s.id !== id));
    };

    if (loading) {
        return (
            <div className="rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6 shadow-lg">
                <div className="flex items-center gap-3 mb-4">
                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 border border-primary/20 text-primary">
                        <span className="material-symbols-outlined text-[20px]">lightbulb</span>
                    </span>
                    <h3 className="text-sm font-black uppercase tracking-widest text-white">Sugerencias IA</h3>
                </div>
                <div className="flex items-center gap-3 py-4 justify-center">
                    <Loader2 className="h-4 w-4 text-primary animate-spin" />
                    <span className="text-xs font-bold text-muted-foreground">Cargando sugerencias...</span>
                </div>
            </div>
        );
    }

    if (suggestions.length === 0) {
        return null;
    }

    return (
        <div className={className}>
            <div className="flex items-center gap-3 mb-4">
                <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 border border-primary/20 text-primary">
                    <span className="material-symbols-outlined text-[20px]">lightbulb</span>
                </span>
                <div>
                    <h3 className="text-sm font-black uppercase tracking-widest text-white">Sugerencias IA</h3>
                    <p className="text-[11px] font-bold text-muted-foreground">{suggestions.length} {suggestions.length === 1 ? 'sugerencia activa' : 'sugerencias activas'}</p>
                </div>
            </div>
            <div className="flex flex-col gap-3">
                {suggestions.map((s) => (
                    <SuggestionCard key={s.id} suggestion={s} onDismiss={handleDismiss} />
                ))}
            </div>
        </div>
    );
}
