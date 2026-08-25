<?php

namespace Database\Factories;

use App\Models\TaskSavedView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskSavedView>
 */
class TaskSavedViewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'view_type' => $this->faker->randomElement(['table', 'kanban', 'calendar', 'list', 'gallery', 'timeline']),
            'filters' => ['status' => 'Pending'],
            'sort' => ['field' => 'due_date', 'direction' => 'asc'],
            'group_by' => $this->faker->randomElement([null, 'status', 'priority', 'project_id']),
            'is_default' => false,
        ];
    }
}
