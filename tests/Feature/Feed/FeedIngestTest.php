<?php

use App\Feed\FeedIngestor;
use App\Models\Connection;
use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function rssXml(): string
{
    return <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0"><channel><title>Blog</title>
      <item><title>Post 1</title><link>https://blog.test/1</link><description>Resumen</description><pubDate>Mon, 01 Sep 2025 10:00:00 GMT</pubDate><guid>p1</guid></item>
      <item><title>Post 2</title><link>https://blog.test/2</link></item>
    </channel></rss>
    XML;
}

it('ingests rss items and dedupes on re-ingest', function () {
    Http::fake(['blog.test/*' => Http::response(rssXml(), 200)]);

    $source = FeedSource::factory()->create(['kind' => 'rss', 'config' => ['url' => 'https://blog.test/feed.xml']]);

    $ingestor = app(FeedIngestor::class);

    expect($ingestor->ingest($source))->toBe(2)
        ->and($ingestor->ingest($source))->toBe(0)
        ->and(FeedItem::query()->forUser($source->user)->count())->toBe(2)
        ->and(FeedItem::first()->external_id)->toBe('p1')
        ->and($source->fresh()->fetch_error)->toBeNull();
});

it('ingests reddit listings with the stored connection', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create([
        'kind' => 'reddit',
        'auth_type' => 'oauth2',
        'base_url' => 'https://oauth.reddit.com',
        'credentials' => ['access_token' => 'at', 'expires_at' => now()->addHour()->toIso8601String()],
    ]);
    Http::fake(['oauth.reddit.com/*' => Http::response([
        'data' => ['children' => [['data' => ['name' => 't3_x', 'title' => 'Post Reddit', 'url' => 'https://reddit.test/x', 'author' => 'u', 'created_utc' => 1750000000]]]],
    ], 200)]);

    $source = FeedSource::factory()->for($user)->create([
        'kind' => 'reddit',
        'connection_id' => $connection->id,
        'config' => ['subreddit' => 'laravel', 'sort' => 'hot'],
    ]);

    expect(app(FeedIngestor::class)->ingest($source))->toBe(1);

    $item = FeedItem::first();
    expect($item->external_id)->toBe('t3_x')
        ->and($item->title)->toBe('Post Reddit');
});

it('ingests youtube channel uploads', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create([
        'kind' => 'youtube',
        'base_url' => 'https://www.googleapis.com',
        'credentials' => ['api_key' => 'yt'],
    ]);

    Http::fake([
        'www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU1']]]],
        ], 200),
        'www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
            'items' => [['snippet' => ['title' => 'Video 1', 'description' => 'D', 'publishedAt' => '2025-09-01T10:00:00Z', 'resourceId' => ['videoId' => 'v1']]]],
        ], 200),
    ]);

    $source = FeedSource::factory()->for($user)->create([
        'kind' => 'youtube',
        'connection_id' => $connection->id,
        'config' => ['channel_ids' => ['UC1']],
    ]);

    expect(app(FeedIngestor::class)->ingest($source))->toBe(1);

    $item = FeedItem::first();
    expect($item->external_id)->toBe('v1')
        ->and($item->url)->toContain('youtube.com/watch?v=v1');
});

it('records source errors without aborting', function () {
    Http::fake(['blog.test/*' => Http::response('nope', 500)]);

    $source = FeedSource::factory()->create(['kind' => 'rss', 'config' => ['url' => 'https://blog.test/feed.xml']]);

    expect(app(FeedIngestor::class)->ingest($source))->toBe(0)
        ->and($source->fresh()->fetch_error)->not->toBeNull()
        ->and($source->fresh()->last_fetched_at)->not->toBeNull();
});

it('prunes old items but keeps saved ones', function () {
    Http::fake(['blog.test/*' => Http::response(rssXml(), 200)]);

    $source = FeedSource::factory()->create(['kind' => 'rss', 'config' => ['url' => 'https://blog.test/feed.xml']]);
    $old = FeedItem::factory()->create(['feed_source_id' => $source->id, 'user_id' => $source->user_id, 'fetched_at' => now()->subDays(200), 'is_saved' => false]);
    $oldSaved = FeedItem::factory()->create(['feed_source_id' => $source->id, 'user_id' => $source->user_id, 'fetched_at' => now()->subDays(200), 'is_saved' => true]);

    app(FeedIngestor::class)->ingest($source);

    expect(FeedItem::find($old->id))->toBeNull()
        ->and(FeedItem::find($oldSaved->id))->not->toBeNull();
});
