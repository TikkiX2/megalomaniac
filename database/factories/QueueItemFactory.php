<?php

namespace Database\Factories;

use App\Models\QueueItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueItem>
 */
class QueueItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->word(),
            'type' => fake()->randomElement(QueueItem::TYPES),
            'position' => fake()->numberBetween(1, 5),
            'source' => null,
            'external_id' => null,
            'cover_url' => null,
            'year' => fake()->numberBetween(1950, 2026),
            'creator' => null,
        ];
    }
}
