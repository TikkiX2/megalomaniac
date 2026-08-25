import { useState } from 'react';
import { X, Lightbulb, Dumbbell, ShoppingCart, DollarSign } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface Suggestion {
    id: number;
    type: string;
    title: string;
    content: string;
    data: { action_url?: string } | null;
}

const typeConfig: Record<string, { label: string; icon: React.ElementType; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    workout_reminder: { label: 'Entrenamiento', icon: Dumbbell, variant: 'destructive' },
    workout_frequency: { label: 'Frecuencia', icon: Dumbbell, variant: 'default' },
    low_stock: { label: 'Stock', icon: ShoppingCart, variant: 'secondary' },
    budget_alert: { label: 'Finanzas', icon: DollarSign, variant: 'outline' },
};

interface SuggestionCardProps {
    suggestion: Suggestion;
    onDismiss: (id: number) => void;
}

export function SuggestionCard({ suggestion, onDismiss }: SuggestionCardProps) {
    const [dismissing, setDismissing] = useState(false);
    const config = typeConfig[suggestion.type] ?? { label: suggestion.type, icon: Lightbulb, variant: 'secondary' as const };
    const Icon = config.icon;

    const handleDismiss = async () => {
        setDismissing(true);
        try {
            const res = await fetch(`/ai/suggestions/${suggestion.id}/dismiss`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((c) => c.startsWith('XSRF-TOKEN='))
                            ?.split('=')[1] ?? ''
                    ),
                },
            });
            if (res.ok) {
                onDismiss(suggestion.id);
            }
        } catch {
            setDismissing(false);
        }
    };

    return (
        <div className={cn(
            'relative rounded-xl border border-[#3e2121] bg-[#2b1a1a] p-4 transition-colors hover:border-primary/30'
        )}>
            <Button
                variant="ghost"
                size="icon"
                onClick={handleDismiss}
                disabled={dismissing}
                className="absolute right-2 top-2 h-6 w-6 text-muted-foreground hover:text-white"
            >
                <X className="h-3.5 w-3.5" />
            </Button>

            <div className="flex items-start gap-3">
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10">
                    <Icon className="h-4 w-4 text-primary" />
                </div>
                <div className="min-w-0 flex-1 pr-6">
                    <div className="flex items-center gap-2 mb-1">
                        <Badge variant={config.variant} className="text-[10px]">
                            {config.label}
                        </Badge>
                    </div>
                    <h4 className="text-sm font-semibold text-white mb-1">{suggestion.title}</h4>
                    <p className="text-xs text-[#e8b4b4] leading-relaxed">{suggestion.content}</p>
                </div>
            </div>
        </div>
    );
}
