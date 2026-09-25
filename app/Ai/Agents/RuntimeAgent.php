<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ActionTool;
use App\Ai\Tools\FinanceQueryTool;
use App\Ai\Tools\GroceryQueryTool;
use App\Ai\Tools\IntegrationCallTool;
use App\Ai\Tools\IntegrationCatalogTool;
use App\Ai\Tools\NutritionQueryTool;
use App\Ai\Tools\WorkoutQueryTool;
use App\Integrations\IntegrationExecutor;
use App\Models\AgentDefinition;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class RuntimeAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public AgentDefinition $definition,
        public array $context = [],
    ) {}

    public function instructions(): string
    {
        $context = json_encode($this->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<TEXT
        Sos el agente "{$this->definition->name}" del cockpit personal Megalomaniac.
        {$this->definition->instructions}

        Contexto de tus ejecuciones recientes (JSON):
        {$context}

        Reglas:
        - Trabajá solo con los datos reales del usuario (usá las herramientas disponibles).
        - No inventes datos; si falta información, decilo en el informe.
        - Las acciones de escritura quedan pendientes de aprobación del usuario; mencionalo si proponés alguna.
        - Cuando corras como agente programado, respondé SOLO con un objeto JSON válido con esta forma:
          {"report": "informe en markdown", "suggestions": "[{\\"title\\": \\"...\\", \\"content\\": \\"...\\"}]", "notify": "mensaje corto opcional"}
          (hasta 3 sugerencias; "suggestions" y "notify" pueden ser cadenas vacías).
        TEXT;
    }

    public function tools(): iterable
    {
        $policy = $this->definition->tools_policy ?? [];
        $internal = (array) ($policy['internal'] ?? []);
        $integrations = (array) ($policy['integrations'] ?? []);

        $tools = [];
        $map = [
            'workout_query' => WorkoutQueryTool::class,
            'finance_query' => FinanceQueryTool::class,
            'nutrition_query' => NutritionQueryTool::class,
            'grocery_query' => GroceryQueryTool::class,
            'actions' => ActionTool::class,
        ];

        foreach ($map as $name => $class) {
            if (in_array($name, $internal, true) || in_array('*', $internal, true)) {
                $tools[] = new $class($this->definition->user);
            }
        }

        if ($integrations !== []) {
            $tools[] = new IntegrationCatalogTool($this->definition->user, $integrations);
            $tools[] = new IntegrationCallTool($this->definition->user, app(IntegrationExecutor::class), $integrations);
        }

        return $tools;
    }
}
