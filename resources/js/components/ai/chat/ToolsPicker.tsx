import { SlidersHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ToolPolicy } from '@/types/chat';

export function ToolsPicker({
    groups,
    policy,
    onChange,
    disabled,
}: {
    groups: { key: string; label: string }[];
    policy: ToolPolicy;
    onChange: (policy: ToolPolicy) => void;
    disabled?: boolean;
}) {
    const label =
        policy.mode === 'manual'
            ? `Herramientas: ${policy.groups.length}`
            : 'Herramientas: Auto';

    const toggle = (key: string) => {
        const current = policy.mode === 'manual' ? policy.groups : [];
        const next = current.includes(key) ? current.filter((group) => group !== key) : [...current, key];

        onChange(next.length > 0 ? { mode: 'manual', groups: next } : { mode: 'auto', groups: [] });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={disabled}
                    aria-label="Elegir herramientas"
                    className="h-8 gap-1.5 px-2 text-xs text-muted-foreground"
                >
                    <SlidersHorizontal className="h-3.5 w-3.5" />
                    {label}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-56">
                <DropdownMenuLabel>Herramientas del agente</DropdownMenuLabel>
                <DropdownMenuItem onSelect={() => onChange({ mode: 'auto', groups: [] })}>
                    {policy.mode === 'auto' ? '✓ ' : ''}Auto (según tu mensaje)
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                {groups.map((group) => (
                    <DropdownMenuCheckboxItem
                        key={group.key}
                        checked={policy.mode === 'manual' && policy.groups.includes(group.key)}
                        onSelect={(event) => {
                            event.preventDefault();
                            toggle(group.key);
                        }}
                    >
                        {group.label}
                    </DropdownMenuCheckboxItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
