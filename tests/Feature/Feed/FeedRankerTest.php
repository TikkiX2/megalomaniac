<?php

use App\Ai\Support\AiProviderResolver;
use App\Feed\FeedRanker;
use App\Feed\FeedScoringAgent;
use App\Models\FeedItem;
use App\Models\FeedPreference;
use App\Models\User;
use Laravel\Ai\Embeddings;

function feedUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'ai_enabled' => true,
        'ai_provider_url' => 'http://ai.test/v1',
        'ai_provider_key' => 'k',
        'ai_model' => 'm',
    ], $attributes));
}

it('resolves embeddings providers', function () {
    expect(AiProviderResolver::embeddingsFor(feedUser(['ai_embeddings_model' => 'emb-1'])))->toBe(['user', 'emb-1'])
        ->and(AiProviderResolver::embeddingsFor(User::factory()->create()))->toBe([null, null]);

    config(['ai.providers.openai.key' => 'server-key']);
    expect(AiProviderResolver::embeddingsFor(User::factory()->create()))->toBe(['openai', null]);
});

it('ranks by embedding similarity when available', function () {
    Embeddings::fake();

    $user = feedUser(['ai_embeddings_model' => 'emb-1']);
    FeedPreference::forUser($user)->update(['embedding' => [1.0, 0.0]]);

    $near = FeedItem::factory()->for($user)->create(['title' => 'Cercano', 'embedding' => [1.0, 0.0]]);
    $far = FeedItem::factory()->for($user)->create(['title' => 'Lejano', 'embedding' => [0.0, 1.0]]);

    $ranked = app(FeedRanker::class)->top($user, 10);

    expect($ranked->first()->id)->toBe($near->id)
        ->and($ranked->last()->id)->toBe($far->id);

    Embeddings::assertNothingGenerated();
});

it('falls back to lexical ranking without embeddings', function () {
    $user = feedUser();
    FeedPreference::forUser($user)->update(['topic_weights' => ['laravel' => 5.0]]);

    $relevant = FeedItem::factory()->for($user)->create(['title' => 'Novedades de Laravel 12', 'summary' => 'laravel laravel']);
    $noise = FeedItem::factory()->for($user)->create(['title' => 'Receta de pan', 'summary' => 'harina agua']);

    $ranked = app(FeedRanker::class)->top($user, 10);

    expect($ranked->first()->id)->toBe($relevant->id)
        ->and($ranked->last()->id)->toBe($noise->id);
});

it('uses llm batch scoring when embeddings are unavailable and a provider exists', function () {
    $user = feedUser();
    FeedPreference::forUser($user)->update(['topic_weights' => []]);

    $first = FeedItem::factory()->for($user)->create(['title' => 'A']);
    $second = FeedItem::factory()->for($user)->create(['title' => 'B']);

    FeedScoringAgent::fake([json_encode([
        ['id' => $second->id, 'score' => 0.9],
        ['id' => $first->id, 'score' => 0.1],
    ])]);

    $ranked = app(FeedRanker::class)->top($user, 10);

    expect($ranked->first()->id)->toBe($second->id)
        ->and($second->fresh()->score)->toBe(0.9);
});

it('excludes hidden and out-of-window items', function () {
    $user = feedUser();

    FeedItem::factory()->for($user)->create(['hidden_at' => now()]);
    FeedItem::factory()->for($user)->create(['published_at' => now()->subDays(30)]);
    $visible = FeedItem::factory()->for($user)->create();

    $ranked = app(FeedRanker::class)->top($user, 10);

    expect($ranked)->toHaveCount(1)
        ->and($ranked->first()->id)->toBe($visible->id);
});
