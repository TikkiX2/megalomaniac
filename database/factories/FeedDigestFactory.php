<?php

namespace Database\Factories;

use App\Models\FeedDigest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedDigest>
 */
class FeedDigestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'content' => fake()->paragraph(),
            'item_ids' => [],
            'sent_at' => null,
        ];
    }
}
