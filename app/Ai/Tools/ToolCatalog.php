<?php

namespace App\Ai\Tools;

use App\Ai\Agents\AgentDefinitionService;
use App\Integrations\IntegrationExecutor;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;

final class ToolCatalog
{
    /**
     * @return array<string, array{label: string, tools: array<int, class-string<Tool>>}>
     */
    public static function groups(): array
    {
        return [
            'tasks' => ['label' => 'Tareas', 'tools' => [TaskQueryTool::class]],
            'workout' => ['label' => 'Entrenamientos', 'tools' => [WorkoutQueryTool::class]],
            'finance' => ['label' => 'Finanzas', 'tools' => [FinanceQueryTool::class]],
            'nutrition' => ['label' => 'Nutrición', 'tools' => [NutritionQueryTool::class]],
            'grocery' => ['label' => 'Compras', 'tools' => [GroceryQueryTool::class]],
            'actions' => ['label' => 'Acciones (crear/actualizar)', 'tools' => [ActionTool::class]],
            'integrations' => ['label' => 'Integraciones', 'tools' => [IntegrationCatalogTool::class, IntegrationCallTool::class]],
            'agents' => ['label' => 'Agentes', 'tools' => [ManageAgentsTool::class]],
            'web' => ['label' => 'Web', 'tools' => [WebSearchTool::class, WebFetchTool::class]],
        ];
    }

    /**
     * @return string[]
     */
    public static function allGroups(): array
    {
        return array_keys(self::groups());
    }

    public static function isValidGroup(string $group): bool
    {
        return array_key_exists($group, self::groups());
    }

    /**
     * @param  string[]  $groups  ['*'] = todas
     * @return array<int, Tool>
     */
    public static function toolsFor(User $user, array $groups): array
    {
        if (in_array('*', $groups, true)) {
            $groups = self::allGroups();
        }

        $tools = [];

        foreach (self::groups() as $key => $group) {
            if (! in_array($key, $groups, true)) {
                continue;
            }

            foreach ($group['tools'] as $class) {
                $tools[] = self::make($user, $class);
            }
        }

        return $tools;
    }

    /**
     * @param  class-string<Tool>  $class
     */
    private static function make(User $user, string $class): Tool
    {
        return match ($class) {
            IntegrationCatalogTool::class => new IntegrationCatalogTool($user),
            IntegrationCallTool::class => new IntegrationCallTool($user, app(IntegrationExecutor::class)),
            ManageAgentsTool::class => new ManageAgentsTool($user, app(AgentDefinitionService::class)),
            default => new $class($user),
        };
    }
}
