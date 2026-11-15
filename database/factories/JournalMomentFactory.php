<?php

namespace Database\Factories;

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalMoment>
 */
class JournalMomentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'journal_day_id' => fn (array $attributes) => JournalDay::firstOrCreate([
                'user_id' => $attributes['user_id'],
                'date' => '2000-01-01',
            ])->id,
            'occurred_at' => now()->subDay(),
            'title' => $this->faker->sentence(),
            'body' => $this->faker->paragraph(),
            'tags' => null,
            'is_private' => false,
        ];
    }
}
