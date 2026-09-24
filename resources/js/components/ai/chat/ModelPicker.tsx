import { Link } from '@inertiajs/react';
import { Check, ChevronDown, Settings2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface ModelPickerProps {
    models: string[];
    value: string | null;
    onChange: (model: string) => void;
    disabled?: boolean;
}

export function ModelPicker({ models, value, onChange, disabled = false }: ModelPickerProps) {
    const current = value ?? models[0] ?? 'Modelo';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={disabled || models.length === 0}
                    className="max-w-44 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                >
                    <span className="truncate">{current}</span>
                    <ChevronDown className="h-3.5 w-3.5 shrink-0" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="max-h-80 overflow-y-auto border-border bg-card">
                <DropdownMenuLabel className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                    Modelo
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {models.map((model) => (
                    <DropdownMenuItem key={model} onSelect={() => onChange(model)}>
                        <Check
                            className={
                                model === current ? 'mr-2 h-3.5 w-3.5 text-primary' : 'mr-2 h-3.5 w-3.5 opacity-0'
                            }
                        />
                        <span className="truncate">{model}</span>
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href="/settings/ai">
                        <Settings2 className="mr-2 h-3.5 w-3.5" />
                        Gestionar en Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
