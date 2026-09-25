<?php

namespace Database\Factories;

use App\Models\FeedItem;
use App\Models\FeedSignal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedSignal>
 */
class FeedSignalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'feed_item_id' => FeedItem::factory(),
            'user_id' => fn (array $attributes) => FeedItem::find($attributes['feed_item_id'])->user_id,
            'type' => 'like',
        ];
    }
}
