import { Sparkles, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

interface AiInsightCardProps {
    title: string;
    insight: string | null;
    loading: boolean;
    icon?: string;
    className?: string;
}

export function AiInsightCard({ title, insight, loading, icon = 'sparkles', className }: AiInsightCardProps) {
    return (
        <div className={cn('rounded-2xl bg-[#2b1a1a] border border-[#3e2121] p-6 shadow-lg relative overflow-hidden', className)}>
            <div className="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-primary/5 blur-2xl pointer-events-none" />
            <div className="relative z-10">
                <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 border border-primary/20 text-primary">
                            <span className="material-symbols-outlined text-[20px]">{icon}</span>
                        </span>
                        <div>
                            <h3 className="text-sm font-black uppercase tracking-widest text-white">{title}</h3>
                            <p className="text-[11px] font-bold text-muted-foreground">AI-powered</p>
                        </div>
                    </div>
                    <span className="flex items-center gap-1 rounded-full bg-primary/10 border border-primary/20 px-2 py-1 text-[10px] font-black uppercase tracking-widest text-primary">
                        <Sparkles className="h-3 w-3" />
                        IA
                    </span>
                </div>

                {loading ? (
                    <div className="flex items-center gap-3 py-6 justify-center">
                        <Loader2 className="h-5 w-5 text-primary animate-spin" />
                        <span className="text-xs font-bold text-muted-foreground">Analizando datos...</span>
                    </div>
                ) : insight ? (
                    <div className="rounded-xl bg-[#1c0f0f] border border-[#3e2121]/50 p-4">
                        <p className="text-sm text-[#e8b4b4] leading-relaxed whitespace-pre-wrap">{insight}</p>
                    </div>
                ) : (
                    <div className="flex flex-col items-center justify-center py-6 text-center rounded-xl bg-[#1c0f0f] border border-dashed border-[#3e2121]">
                        <span className="material-symbols-outlined text-2xl text-muted-foreground mb-2">psychology</span>
                        <p className="text-xs font-bold text-muted-foreground">Sin datos suficientes para generar insights</p>
                        <p className="text-[10px] text-muted-foreground/60 mt-1">Registra más actividad para obtener análisis IA</p>
                    </div>
                )}
            </div>
        </div>
    );
}
