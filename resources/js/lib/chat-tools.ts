import type { ToolActivity } from '@/types/chat';

export const TOOL_LABELS: Record<string, string> = {
    GymQueryTool: 'Consultando entrenamientos',
    GymActionTool: 'Actualizando entrenamiento',
    FinanceQueryTool: 'Consultando finanzas',
    NutritionQueryTool: 'Consultando nutrición',
    GroceryQueryTool: 'Consultando compras',
    PeopleQueryTool: 'Consultando personas',
    PeopleActionTool: 'Actualizando personas',
    ActionTool: 'Ejecutando acción',
};

export interface ToolGroup {
    name: string;
    label: string;
    count: number;
    failedCount: number;
    running: boolean;
}

export function toolLabel(name: string): string {
    return TOOL_LABELS[name] ?? `Usando ${name}`;
}

export function groupTools(tools: ToolActivity[]): ToolGroup[] {
    const groups = new Map<string, ToolGroup>();

    for (const tool of tools) {
        const existing = groups.get(tool.name);

        if (existing === undefined) {
            groups.set(tool.name, {
                name: tool.name,
                label: toolLabel(tool.name),
                count: 1,
                failedCount: tool.status === 'failed' ? 1 : 0,
                running: tool.status === 'running',
            });

            continue;
        }

        existing.count += 1;
        existing.running = existing.running || tool.status === 'running';

        if (tool.status === 'failed') {
            existing.failedCount += 1;
        }
    }

    return [...groups.values()];
}
