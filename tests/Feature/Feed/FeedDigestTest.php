<?php

use App\Feed\DigestAgent;
use App\Feed\FeedDigestAgent;
use App\Feed\FeedLearner;
use App\Models\Connection;
use App\Models\FeedDigest;
use App\Models\FeedItem;
use App\Models\FeedPreference;
use App\Models\FeedSource;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('learns topic weights and counters from signals', function () {
    $user = User::factory()->create();
    $item = FeedItem::factory()->for($user)->create(['title' => 'Laravel y PHP moderno', 'summary' => 'laravel']);

    app(FeedLearner::class)->record($item, 'like');
    app(FeedLearner::class)->record($item, 'like');

    $preferences = FeedPreference::forUser($user);

    expect($preferences->likes)->toBe(1)
        ->and($preferences->topic_weights['laravel'])->toBeGreaterThan(0)
        ->and($item->signals()->count())->toBe(1);
});

it('hides on hide and saves on save', function () {
    $user = User::factory()->create();
    $hidden = FeedItem::factory()->for($user)->create();
    $saved = FeedItem::factory()->for($user)->create();

    app(FeedLearner::class)->record($hidden, 'hide');
    app(FeedLearner::class)->record($saved, 'save');

    expect($hidden->fresh()->hidden_at)->not->toBeNull()
        ->and($saved->fresh()->is_saved)->toBeTrue();
});

it('updates the embedding centroid on positive signals', function () {
    $user = User::factory()->create();
    $item = FeedItem::factory()->for($user)->create(['embedding' => [1.0, 0.0]]);

    app(FeedLearner::class)->record($item, 'like');

    expect(FeedPreference::forUser($user)->embedding)->toEqual([1.0, 0.0]);
});

it('generates a digest with the agent and notifies telegram', function () {
    FeedDigestAgent::fake(['## Digest de prueba']);

    $user = User::factory()->create([
        'ai_enabled' => true,
        'ai_provider_url' => 'http://ai.test/v1',
        'ai_provider_key' => 'k',
        'ai_model' => 'm',
    ]);
    Connection::factory()->for($user)->create([
        'kind' => 'telegram',
        'base_url' => 'https://api.telegram.org',
        'credentials' => ['token' => 'bot'],
        'options' => ['chat_id' => '1'],
    ]);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $items = FeedItem::factory()->for($user)->count(3)->create();

    $digest = app(DigestAgent::class)->generate($user, $items);

    expect($digest->content)->toContain('Digest de prueba')
        ->and($digest->item_ids)->toHaveCount(3)
        ->and($digest->sent_at)->not->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
});

it('falls back to a deterministic digest without ai provider', function () {
    $user = User::factory()->create();
    $items = FeedItem::factory()->for($user)->count(2)->create();

    $digest = app(DigestAgent::class)->generate($user, $items);

    expect($digest->content)->toContain('Digest del día')
        ->and($digest->sent_at)->toBeNull();
});

it('generates due digests only after the configured hour', function () {
    FeedDigestAgent::fake(['## Due']);
    config(['feed.digest_hour' => 8]);

    $user = User::factory()->create([
        'ai_enabled' => true,
        'ai_provider_url' => 'http://ai.test/v1',
        'ai_provider_key' => 'k',
        'ai_model' => 'm',
    ]);
    FeedSource::factory()->for($user)->create();
    FeedItem::factory()->for($user)->create();

    $this->travelTo(now()->setTime(7, 0));
    expect(app(DigestAgent::class)->generateDue())->toBe(0);

    $this->travelTo(now()->setTime(9, 0));
    expect(app(DigestAgent::class)->generateDue())->toBe(1)
        ->and(FeedDigest::query()->count())->toBe(1);
});
