<?php

namespace Database\Factories;

use App\Models\JournalReflection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalReflection>
 */
class JournalReflectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'week_start' => '2026-11-16',
            'body' => $this->faker->paragraph(),
            'source' => 'fallback',
            'model' => null,
            'generated_at' => now(),
        ];
    }
}
