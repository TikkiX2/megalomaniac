<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TaskMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskMilestone>
 */
class TaskMilestoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'due_date' => $this->faker->dateTimeBetween('now', '+3 months'),
            'status' => $this->faker->randomElement(['pending', 'completed', 'cancelled']),
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
