<?php

use App\Models\Connection;
use App\Models\FeedDigest;
use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

it('renders the feed with digest and ranked items', function () {
    $user = User::factory()->create();
    FeedDigest::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString(), 'content' => 'Digest X']);
    FeedItem::factory()->for($user)->create(['title' => 'Item visible']);
    FeedItem::factory()->for($user)->create(['title' => 'Item oculto', 'hidden_at' => now()]);
    FeedItem::factory()->create();

    $this->actingAs($user)->get(route('feed.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('feed/index')
            ->where('digest.content', 'Digest X')
            ->has('items', 1)
            ->where('items.0.title', 'Item visible')
            ->has('sources'));
});

it('records signals from the ui', function () {
    $user = User::factory()->create();
    $item = FeedItem::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('feed.signal', $item), ['signal' => 'save'])
        ->assertRedirect();

    expect($item->fresh()->is_saved)->toBeTrue();

    $this->actingAs($user)
        ->post(route('feed.signal', $item), ['signal' => 'hide'])
        ->assertRedirect();

    expect($item->fresh()->hidden_at)->not->toBeNull();
});

it('creates and deletes feed sources', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('feed.sources.store'), [
        'kind' => 'rss',
        'name' => 'HN',
        'config' => ['url' => 'https://news.ycombinator.com/rss'],
        'enabled' => true,
    ])->assertRedirect(route('feed.settings'));

    $source = FeedSource::firstOrFail();
    expect($source->user_id)->toBe($user->id);

    $this->actingAs($user)->delete(route('feed.sources.destroy', $source))->assertRedirect();
    expect(FeedSource::find($source->id))->toBeNull();
});

it('stores reddit sources with a connection', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create(['kind' => 'reddit']);

    $this->actingAs($user)->post(route('feed.sources.store'), [
        'kind' => 'reddit',
        'name' => 'r/laravel',
        'config' => ['subreddit' => 'laravel', 'sort' => 'hot'],
        'connection_id' => $connection->id,
        'enabled' => true,
    ])->assertRedirect();

    expect(FeedSource::first()->connection_id)->toBe($connection->id);
});

it('404s foreign items and sources', function () {
    $item = FeedItem::factory()->create();
    $source = FeedSource::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('feed.signal', $item), ['signal' => 'like'])->assertNotFound();
    $this->actingAs(User::factory()->create())->delete(route('feed.sources.destroy', $source))->assertNotFound();
});
