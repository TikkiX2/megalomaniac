import { useState } from 'react';
import { csrfHeaders } from '@/lib/csrf';
import { Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface PromptItem {
    connection_id: number;
    connection_name: string;
    name: string;
    title: string | null;
    description: string | null;
    arguments: { name: string; description?: string; required?: boolean }[];
}

export function PromptsMenu({
    onInsert,
    disabled,
}: {
    onInsert: (text: string) => void;
    disabled?: boolean;
}) {
    const [prompts, setPrompts] = useState<PromptItem[] | null>(null);
    const [loading, setLoading] = useState(false);

    const load = async () => {
        setLoading(true);

        try {
            const response = await fetch('/ai/mcp-prompts', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            const payload = await response.json();
            setPrompts(payload.prompts ?? []);
        } catch {
            setPrompts([]);
        } finally {
            setLoading(false);
        }
    };

    const use = async (prompt: PromptItem) => {
        const args: Record<string, string> = {};

        for (const argument of prompt.arguments ?? []) {
            const value = window.prompt(`${argument.description ?? argument.name}${argument.required ? ' *' : ''}`);

            if (value === null) {
                return;
            }

            if (value !== '') {
                args[argument.name] = value;
            }
        }

        const response = await fetch('/ai/mcp-prompts/render', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify({ connection_id: prompt.connection_id, name: prompt.name, arguments: args }),
        });

        const payload = await response.json();

        if (payload.ok && payload.text) {
            onInsert(payload.text);
        } else {
            window.alert(payload.error ?? 'No se pudo renderizar el prompt.');
        }
    };

    return (
        <DropdownMenu
            onOpenChange={(open) => {
                if (open && prompts === null) {
                    void load();
                }
            }}
        >
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={disabled}
                    aria-label="Prompts MCP"
                    className="h-8 gap-1.5 px-2 text-xs text-muted-foreground"
                >
                    <Sparkles className="h-3.5 w-3.5" />
                    Prompts
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-64">
                <DropdownMenuLabel>Prompts de MCPs conectados</DropdownMenuLabel>
                {loading && <DropdownMenuItem disabled>Cargando…</DropdownMenuItem>}
                {!loading && (prompts?.length ?? 0) === 0 && (
                    <DropdownMenuItem disabled>Sin prompts disponibles</DropdownMenuItem>
                )}
                {prompts?.map((prompt) => (
                    <DropdownMenuItem
                        key={`${prompt.connection_id}:${prompt.name}`}
                        onSelect={() => void use(prompt)}
                    >
                        <span className="flex flex-col">
                            <span className="font-bold">{prompt.title ?? prompt.name}</span>
                            <span className="text-xs text-muted-foreground">{prompt.connection_name}</span>
                        </span>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
