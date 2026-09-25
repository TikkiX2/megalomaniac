import { Bot } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export function AgentPicker({
    agents,
    value,
    onChange,
    disabled,
}: {
    agents: { key: string; name: string }[];
    value: string;
    onChange: (key: string) => void;
    disabled?: boolean;
}) {
    return (
        <Select value={value} onValueChange={onChange} disabled={disabled}>
            <SelectTrigger
                aria-label="Agente"
                className="h-8 w-auto gap-1.5 border-border bg-transparent px-2 text-xs text-muted-foreground"
            >
                <Bot className="h-3.5 w-3.5" />
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="megalomaniac">Megalomaniac</SelectItem>
                {agents.map((agent) => (
                    <SelectItem key={agent.key} value={agent.key}>
                        {agent.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
