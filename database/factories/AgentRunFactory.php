<?php

namespace Database\Factories;

use App\Models\AgentDefinition;
use App\Models\AgentRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentRun>
 */
class AgentRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agent_definition_id' => AgentDefinition::factory(),
            'user_id' => fn (array $attributes) => AgentDefinition::find($attributes['agent_definition_id'])->user_id,
            'status' => 'success',
            'triggered_by' => 'schedule',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'report' => fake()->paragraph(),
            'suggestions_created' => 0,
            'approvals_created' => 0,
            'usage' => ['promptTokens' => 10, 'completionTokens' => 20],
            'error' => null,
            'context' => null,
            'notified_at' => null,
        ];
    }
}
