import { Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export function SkillsPicker({
    skills,
    selected,
    onChange,
    disabled,
}: {
    skills: { key: string; name: string }[];
    selected: string[];
    onChange: (keys: string[]) => void;
    disabled?: boolean;
}) {
    const label = selected.length > 0 ? `Skills: ${selected.length}` : 'Skills';

    const toggle = (key: string) => {
        onChange(selected.includes(key) ? selected.filter((skill) => skill !== key) : [...selected, key]);
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={disabled}
                    aria-label="Elegir skills"
                    className="h-8 gap-1.5 px-2 text-xs text-muted-foreground"
                >
                    <Sparkles className="h-3.5 w-3.5" />
                    {label}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-64">
                <DropdownMenuLabel>Skills de este turno</DropdownMenuLabel>
                {skills.length === 0 ? (
                    <DropdownMenuItem disabled>No hay skills cargadas</DropdownMenuItem>
                ) : (
                    skills.map((skill) => (
                        <DropdownMenuCheckboxItem
                            key={skill.key}
                            checked={selected.includes(skill.key)}
                            onSelect={(event) => {
                                event.preventDefault();
                                toggle(skill.key);
                            }}
                        >
                            {skill.name}
                        </DropdownMenuCheckboxItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
