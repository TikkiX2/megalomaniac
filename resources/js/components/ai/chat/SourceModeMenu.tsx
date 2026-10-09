import { Link } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { edit as aiSettingsEdit } from '@/routes/settings/ai';
import type { SourceMode } from '@/types/chat';

const MODE_LABELS: Record<SourceMode, string> = {
    web: 'Web',
    local: 'Mis fuentes',
    both: 'Ambos',
    off: 'Off',
};

interface SourceModeMenuProps {
    mode: SourceMode;
    forceWeb: boolean;
    deepContext: boolean;
    onChangeMode?: (mode: SourceMode) => void;
    onChangeForceWeb?: (forceWeb: boolean) => void;
    onChangeDeepContext?: (value: boolean) => void;
    hasTavilyKey: boolean;
    disabled?: boolean;
}

export function SourceModeMenu({
    mode,
    forceWeb,
    deepContext,
    onChangeMode,
    onChangeForceWeb,
    onChangeDeepContext,
    hasTavilyKey,
    disabled = false,
}: SourceModeMenuProps) {
    const webEnabled = mode === 'web' || mode === 'both';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={disabled}
                    aria-label="Elegir fuentes de la respuesta"
                    className="h-8 gap-1.5 px-2 text-xs text-muted-foreground"
                >
                    <Globe className="h-3.5 w-3.5" />
                    Fuentes: {MODE_LABELS[mode]}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-64 border-border bg-card">
                <DropdownMenuLabel className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                    Fuentes
                </DropdownMenuLabel>
                <DropdownMenuSeparator />

                {onChangeMode !== undefined ? (
                    <DropdownMenuRadioGroup value={mode} onValueChange={(value) => onChangeMode(value as SourceMode)}>
                        {(Object.keys(MODE_LABELS) as SourceMode[]).map((key) => (
                            <DropdownMenuRadioItem key={key} value={key}>
                                {MODE_LABELS[key]}
                            </DropdownMenuRadioItem>
                        ))}
                    </DropdownMenuRadioGroup>
                ) : (
                    // En la página inicial el hilo aún no existe: el modo se fija al crearlo
                    // (por defecto "both"), así que solo se ofrece "Buscar siempre".
                    <p className="px-2 py-1.5 text-xs text-muted-foreground">El modo se define al crear el hilo (Ambos).</p>
                )}

                {onChangeForceWeb !== undefined && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            checked={forceWeb}
                            disabled={!webEnabled}
                            onSelect={(event) => {
                                event.preventDefault();
                                onChangeForceWeb(!forceWeb);
                            }}
                        >
                            Buscar siempre (esta pregunta)
                        </DropdownMenuCheckboxItem>
                    </>
                )}

                {onChangeDeepContext !== undefined && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            checked={deepContext}
                            disabled={mode === 'off'}
                            onSelect={(event) => {
                                event.preventDefault();
                                onChangeDeepContext(!deepContext);
                            }}
                        >
                            <span className="flex flex-col gap-0.5">
                                <span>Comprensión extendida</span>
                                <span className="text-[11px] font-normal text-muted-foreground">
                                    Hasta ~15k tokens por pregunta
                                </span>
                            </span>
                        </DropdownMenuCheckboxItem>
                    </>
                )}

                {webEnabled && !hasTavilyKey && (
                    <>
                        <DropdownMenuSeparator />
                        <p className="px-2 py-1.5 text-[10px] leading-snug text-muted-foreground">
                            Sin API key de Tavily —{' '}
                            <Link href={aiSettingsEdit.url()} className="text-primary hover:underline">
                                configúrala en Ajustes → IA
                            </Link>
                        </p>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
