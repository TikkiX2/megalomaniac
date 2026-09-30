<?php

namespace App\Ai\Tools;

use App\Ai\Agents\AgentDefinitionService;
use App\Ai\Memory\MemoryCatalog;
use App\Ai\Skills\SkillCatalog;
use App\Integrations\IntegrationExecutor;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\Finance\FinanceService;
use App\Services\Freelance\FreelanceService;
use App\Services\Grocery\GroceryService;
use App\Services\Gym\RoutineService;
use App\Services\Gym\WorkoutSessionService;
use App\Services\Health\HealthService;
use App\Services\Nutrition\NutritionService;
use App\Services\People\PeopleService;
use App\Services\Projects\ProjectService;
use App\Services\Supplement\SupplementService;
use App\Services\Tasks\TaskService;
use Laravel\Ai\Contracts\Tool;

final class ToolCatalog
{
    /**
     * @return array<string, array{label: string, tools: array<int, class-string<Tool>>}>
     */
    public static function groups(): array
    {
        return [
            'tasks' => ['label' => 'Tareas', 'tools' => [TaskQueryTool::class, ProjectActionTool::class, TaskActionTool::class]],
            'workout' => ['label' => 'Entrenamientos', 'tools' => [GymQueryTool::class, GymActionTool::class]],
            'finance' => ['label' => 'Finanzas', 'tools' => [FinanceQueryTool::class, FinanceActionTool::class]],
            'nutrition' => ['label' => 'Nutrición', 'tools' => [NutritionQueryTool::class, NutritionActionTool::class]],
            'grocery' => ['label' => 'Compras', 'tools' => [GroceryQueryTool::class, GroceryActionTool::class]],
            'supplements' => ['label' => 'Suplementos', 'tools' => [SupplementQueryTool::class, SupplementActionTool::class]],
            'freelance' => ['label' => 'Freelance', 'tools' => [FreelanceQueryTool::class, FreelanceActionTool::class]],
            'people' => ['label' => 'Personas', 'tools' => [PeopleQueryTool::class, PeopleActionTool::class]],
            'health' => ['label' => 'Salud', 'tools' => [HealthQueryTool::class, HealthActionTool::class]],
            'actions' => ['label' => 'Todas las acciones', 'tools' => self::actionTools()],
            'integrations' => ['label' => 'Integraciones', 'tools' => [IntegrationCatalogTool::class, IntegrationCallTool::class]],
            'agents' => ['label' => 'Agentes', 'tools' => [ManageAgentsTool::class]],
            'skills' => ['label' => 'Skills', 'tools' => [LoadSkillTool::class]],
            'memory' => ['label' => 'Memoria', 'tools' => [RememberMemoryTool::class, ForgetMemoryTool::class, PromoteMemoryTool::class]],
            'web' => ['label' => 'Web', 'tools' => [WebSearchTool::class, WebFetchTool::class]],
        ];
    }

    /**
     * Every write tool, exposed through the "actions" group so a bare write
     * verb gives the model the module action tools even without a module
     * keyword. toolsFor() dedupes by class.
     *
     * @return array<int, class-string<Tool>>
     */
    public static function actionTools(): array
    {
        return [
            ProjectActionTool::class,
            TaskActionTool::class,
            GymActionTool::class,
            FinanceActionTool::class,
            NutritionActionTool::class,
            GroceryActionTool::class,
            SupplementActionTool::class,
            FreelanceActionTool::class,
            PeopleActionTool::class,
            HealthActionTool::class,
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
    public static function toolsFor(User $user, array $groups, ?ChatThread $thread = null): array
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
                $tools[$class] ??= self::make($user, $class, $thread);
            }
        }

        return array_values($tools);
    }

    /**
     * @param  class-string<Tool>  $class
     */
    private static function make(User $user, string $class, ?ChatThread $thread = null): Tool
    {
        return match ($class) {
            IntegrationCatalogTool::class => new IntegrationCatalogTool($user),
            IntegrationCallTool::class => new IntegrationCallTool($user, app(IntegrationExecutor::class)),
            ManageAgentsTool::class => new ManageAgentsTool($user, app(AgentDefinitionService::class)),
            LoadSkillTool::class => new LoadSkillTool($user, app(SkillCatalog::class)),
            RememberMemoryTool::class => new RememberMemoryTool($user, app(MemoryCatalog::class), $thread),
            ForgetMemoryTool::class => new ForgetMemoryTool($user, app(MemoryCatalog::class), $thread),
            PromoteMemoryTool::class => new PromoteMemoryTool($user, app(MemoryCatalog::class)),
            GymActionTool::class => new GymActionTool($user, app(WorkoutSessionService::class), app(RoutineService::class)),
            ProjectActionTool::class => new ProjectActionTool($user, app(ProjectService::class)),
            TaskActionTool::class => new TaskActionTool($user, app(TaskService::class)),
            FinanceActionTool::class => new FinanceActionTool($user, app(FinanceService::class)),
            NutritionActionTool::class => new NutritionActionTool($user, app(NutritionService::class)),
            GroceryActionTool::class => new GroceryActionTool($user, app(GroceryService::class)),
            SupplementQueryTool::class => new SupplementQueryTool($user, app(SupplementService::class)),
            SupplementActionTool::class => new SupplementActionTool($user, app(SupplementService::class)),
            FreelanceActionTool::class => new FreelanceActionTool($user, app(FreelanceService::class)),
            PeopleQueryTool::class => new PeopleQueryTool($user, app(PeopleService::class)),
            PeopleActionTool::class => new PeopleActionTool($user, app(PeopleService::class)),
            HealthQueryTool::class => new HealthQueryTool($user, app(HealthService::class)),
            HealthActionTool::class => new HealthActionTool($user, app(HealthService::class)),
            default => new $class($user),
        };
    }
}
