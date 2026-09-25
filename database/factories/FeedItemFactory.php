<?php

namespace Database\Factories;

use App\Models\FeedItem;
use App\Models\FeedSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FeedItem>
 */
class FeedItemFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(6);
        $url = 'https://blog.test/'.fake()->unique()->slug(3);

        return [
            'feed_source_id' => FeedSource::factory(),
            'user_id' => fn (array $attributes) => FeedSource::find($attributes['feed_source_id'])->user_id,
            'external_id' => (string) Str::uuid(),
            'title' => $title,
            'url' => $url,
            'author' => fake()->name(),
            'summary' => fake()->paragraph(),
            'content_hash' => sha1($title.$url),
            'published_at' => now()->subHours(fake()->numberBetween(1, 72)),
            'fetched_at' => now(),
            'embedding' => null,
            'score' => null,
            'scored_at' => null,
            'is_saved' => false,
            'hidden_at' => null,
        ];
    }
}
