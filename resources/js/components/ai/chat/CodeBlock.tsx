import { Check, Copy } from 'lucide-react';
import { useRef, useState, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function CodeBlock({ children, className }: { children: ReactNode; className?: string }) {
    const preRef = useRef<HTMLPreElement>(null);
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        const text = preRef.current?.textContent ?? '';

        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            // clipboard no disponible
        }
    };

    return (
        <div className={cn('group relative my-3 overflow-hidden rounded-xl border border-border bg-background', className)}>
            <button
                type="button"
                onClick={copy}
                aria-label="Copiar código"
                className="absolute right-2 top-2 z-10 rounded-md border border-border bg-card p-1.5 text-muted-foreground opacity-0 transition-opacity hover:text-foreground focus-visible:opacity-100 group-hover:opacity-100"
            >
                {copied ? <Check className="h-3.5 w-3.5 text-primary" /> : <Copy className="h-3.5 w-3.5" />}
            </button>
            <pre ref={preRef} className="overflow-x-auto p-4 text-xs leading-relaxed">
                {children}
            </pre>
        </div>
    );
}
