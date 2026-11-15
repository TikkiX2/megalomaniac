<?php

namespace Database\Factories;

use App\Models\JournalDay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalDay>
 */
class JournalDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => '2026-11-01',
            'body' => $this->faker->paragraph(),
            'mood' => null,
            'tags' => null,
            'is_private' => false,
        ];
    }
}
