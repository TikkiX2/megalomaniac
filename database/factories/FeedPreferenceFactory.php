<?php

namespace Database\Factories;

use App\Models\FeedPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedPreference>
 */
class FeedPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'embedding' => null,
            'topic_weights' => [],
            'source_weights' => [],
            'likes' => 0,
            'dislikes' => 0,
            'saves' => 0,
            'opens' => 0,
        ];
    }
}
