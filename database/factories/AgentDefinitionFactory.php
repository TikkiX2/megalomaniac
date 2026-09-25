<?php

namespace Database\Factories;

use App\Models\AgentDefinition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentDefinition>
 */
class AgentDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'instructions' => 'Revisá mis datos y generá un informe breve.',
            'tools_policy' => ['internal' => [], 'integrations' => []],
            'schedule_type' => 'interval',
            'schedule_value' => '1h',
            'timezone' => 'UTC',
            'enabled' => true,
            'max_runs_per_day' => 24,
            'max_tokens_per_run' => 2000,
            'last_run_at' => null,
            'next_run_at' => now()->addHour(),
            'failure_count' => 0,
        ];
    }

    public function due(): static
    {
        return $this->state(fn (array $attributes) => ['next_run_at' => now()->subMinute()]);
    }
}
