import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { aiModuleUrl } from '@/lib/ai-modules';

interface ModuleAiButtonProps {
    /** Key del módulo (`gym`, `nutrition`, …). */
    module: string;
    className?: string;
}

/**
 * Entrada al asistente de un módulo desde la página del módulo. Ghost/sm para
 * que quepa junto al título sin competir con las acciones propias de la
 * pantalla.
 */
export function ModuleAiButton({ module, className }: ModuleAiButtonProps) {
    return (
        <Button
            asChild
            variant="ghost"
            size="sm"
            className={className ?? 'shrink-0 text-muted-foreground hover:text-primary'}
            data-test={`module-ai-button-${module}`}
        >
            <Link href={aiModuleUrl(module)}>
                <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                    smart_toy
                </span>
                Asistente IA
            </Link>
        </Button>
    );
}

export default ModuleAiButton;