<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskMilestone>
 */
class TaskMilestoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => \App\Models\Project::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'due_date' => $this->faker->dateTimeBetween('now', '+3 months'),
            'status' => $this->faker->randomElement(['pending', 'completed', 'cancelled']),
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
