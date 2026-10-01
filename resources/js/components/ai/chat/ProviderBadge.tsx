import { Cpu, Repeat } from 'lucide-react';
import type { ChatTurnMeta } from '@/types/chat';

interface ProviderBadgeProps {
    meta: ChatTurnMeta;
}

/**
 * Qué proveedor respondió el último turno y, cuando la cadena tuvo que saltar
 * al siguiente, que la respuesta vino del de respaldo. Vive junto al título
 * del hilo (o sobre el composer en el chat general) porque es información del
 * turno, no del mensaje.
 */
export function ProviderBadge({ meta }: ProviderBadgeProps) {
    return (
        <span
            className="flex shrink-0 items-center gap-1.5"
            data-test="provider-badge"
            title={
                meta.model
                    ? `${meta.provider ?? 'Proveedor'} · ${meta.model}`
                    : (meta.provider ?? 'Proveedor')
            }
        >
            <span className="inline-flex items-center gap-1 rounded-full border border-border bg-card px-2 py-0.5 text-[10px] font-bold text-muted-foreground">
                <Cpu className="h-3 w-3" aria-hidden="true" />
                {meta.provider ?? 'Proveedor'}
            </span>

            {meta.fallback && (
                <span
                    className="inline-flex items-center gap-1 rounded-full border border-primary/30 bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary"
                    data-test="provider-fallback"
                >
                    <Repeat className="h-3 w-3" aria-hidden="true" />
                    Fallback: 2.º proveedor
                </span>
            )}
        </span>
    );
}