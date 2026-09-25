<?php

namespace Database\Factories;

use App\Models\FeedSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedSource>
 */
class FeedSourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => 'rss',
            'name' => fake()->unique()->words(2, true),
            'config' => ['url' => 'https://blog.test/feed.xml'],
            'enabled' => true,
            'connection_id' => null,
            'last_fetched_at' => null,
            'fetch_error' => null,
        ];
    }
}
