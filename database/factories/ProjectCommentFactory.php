<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectComment>
 */
class ProjectCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'content' => json_encode(['text' => $this->faker->paragraph()]), // Simple JSON for now
            'parent_id' => null,
        ];
    }
}
