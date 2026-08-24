<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectTask>
 */
class ProjectTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => \App\Models\Project::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['To Do', 'In Progress', 'Done']),
            'priority' => $this->faker->randomElement(['Low', 'Normal', 'High']),
            'due_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'notion_page_id' => null,
            'notion_last_sync' => null,
            'is_archived' => false,
        ];
    }
}
