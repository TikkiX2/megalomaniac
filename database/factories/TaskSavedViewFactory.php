<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskSavedView>
 */
class TaskSavedViewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'name' => $this->faker->words(2, true),
            'view_type' => $this->faker->randomElement(['table', 'kanban', 'calendar', 'list', 'gallery', 'timeline']),
            'filters' => ['status' => 'Pending'],
            'sort' => ['field' => 'due_date', 'direction' => 'asc'],
            'group_by' => $this->faker->randomElement([null, 'status', 'priority', 'project_id']),
            'is_default' => false,
        ];
    }
}
