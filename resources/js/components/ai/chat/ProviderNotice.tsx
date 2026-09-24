import { Link } from '@inertiajs/react';
import { Bot } from 'lucide-react';

export function ProviderNotice() {
    return (
        <div className="flex items-start gap-3 rounded-xl border border-border bg-card p-4">
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/15">
                <Bot className="h-4 w-4 text-primary" />
            </div>
            <div>
                <p className="text-sm font-bold text-foreground">Configura tu proveedor de IA</p>
                <p className="mt-1 text-xs text-muted-foreground">
                    El chat necesita una URL, una API key y un modelo. Se guardan cifrados.{' '}
                    <Link href="/settings/ai" className="font-medium text-primary hover:underline">
                        Ir a Settings → IA
                    </Link>
                </p>
            </div>
        </div>
    );
}
