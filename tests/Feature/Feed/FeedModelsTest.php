<?php

use App\Models\FeedDigest;
use App\Models\FeedItem;
use App\Models\FeedPreference;
use App\Models\FeedSignal;
use App\Models\FeedSource;
use App\Models\User;
use Illuminate\Database\QueryException;

it('creates feed sources with casts and scopes', function () {
    $user = User::factory()->create();
    FeedSource::factory()->for($user)->create(['kind' => 'rss', 'config' => ['url' => 'https://x.test/feed']]);
    FeedSource::factory()->for($user)->create(['enabled' => false]);
    FeedSource::factory()->create();

    $source = FeedSource::query()->forUser($user)->where('enabled', true)->first();

    expect($source->config)->toBe(['url' => 'https://x.test/feed'])
        ->and($source->enabled)->toBeTrue()
        ->and(FeedSource::query()->forUser($user)->count())->toBe(2)
        ->and(FeedSource::query()->forUser($user)->enabled()->count())->toBe(1);
});

it('dedupes items per source and exposes visible/saved scopes', function () {
    $item = FeedItem::factory()->create(['external_id' => 'abc']);
    FeedItem::factory()->create(['feed_source_id' => $item->feed_source_id, 'external_id' => 'abc']);
})->throws(QueryException::class);

it('scopes items by visibility and saved flag', function () {
    $user = User::factory()->create();
    FeedItem::factory()->for($user)->create(['hidden_at' => null, 'is_saved' => false]);
    FeedItem::factory()->for($user)->create(['hidden_at' => now(), 'is_saved' => false]);
    FeedItem::factory()->for($user)->create(['hidden_at' => null, 'is_saved' => true]);

    expect(FeedItem::query()->forUser($user)->count())->toBe(3)
        ->and(FeedItem::query()->forUser($user)->visible()->count())->toBe(2)
        ->and(FeedItem::query()->forUser($user)->visible()->saved()->count())->toBe(1);
});

it('records unique signals', function () {
    $item = FeedItem::factory()->create();

    FeedSignal::factory()->create(['feed_item_id' => $item->id, 'user_id' => $item->user_id, 'type' => 'like']);
    FeedSignal::factory()->create(['feed_item_id' => $item->id, 'user_id' => $item->user_id, 'type' => 'like']);
})->throws(QueryException::class);

it('keeps one preference row per user and one digest per date', function () {
    $user = User::factory()->create();

    $preference = FeedPreference::forUser($user);
    $again = FeedPreference::forUser($user);

    expect($preference->id)->toBe($again->id)
        ->and(FeedPreference::query()->count())->toBe(1);

    FeedDigest::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString()]);
    FeedDigest::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString()]);
})->throws(QueryException::class);
