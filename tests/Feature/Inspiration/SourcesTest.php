<?php

declare(strict_types=1);

use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\Exceptions\SourceException;
use App\Inspiration\SourceManager;
use App\Inspiration\Sources\Api\ArtStationSource;
use App\Inspiration\Sources\Api\DeviantArtSource;
use App\Inspiration\Sources\Api\OpenverseSource;
use App\Inspiration\Sources\Api\WallhavenSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Read a raw fixture body; adapters never see the files directly.
 */
function inspirationFixture(string $path): string
{
    $contents = file_get_contents(base_path('tests/Fixtures/Inspiration/'.$path));

    if ($contents === false) {
        throw new RuntimeException("Missing inspiration fixture [{$path}].");
    }

    return $contents;
}

dataset('tier1ApiSources', [
    'deviantart' => ['deviantart', DeviantArtSource::class],
    'artstation' => ['artstation', ArtStationSource::class],
    'wallhaven' => ['wallhaven', WallhavenSource::class],
    'openverse' => ['openverse', OpenverseSource::class],
]);

/**
 * Stub every host the given adapter talks to, serving the fixture body.
 */
function fakeTier1Http(string $key, string $fixture, int $status = 200): void
{
    $body = inspirationFixture($fixture);

    Http::fake(match ($key) {
        'deviantart' => [
            '*oauth2/token*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            '*api/v1/oauth2/search/art*' => Http::response($body, $status),
            '*api/v1/oauth2/browse/popular*' => Http::response($body, $status),
        ],
        'artstation' => [
            '*artstation.com/api/v2/feeds/projects.json*' => Http::response($body, $status),
        ],
        'wallhaven' => [
            '*wallhaven.cc/api/v1/search*' => Http::response($body, $status),
        ],
        'openverse' => [
            '*api.openverse.org/v1/images*' => Http::response($body, $status),
        ],
        default => throw new InvalidArgumentException("Unknown tier 1 source [{$key}]."),
    });
}

beforeEach(function (): void {
    config([
        'inspiration.deviantart.client_id' => 'test-client-id',
        'inspiration.deviantart.client_secret' => 'test-client-secret',
    ]);
});

it('maps a happy payload into a non-empty page for :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/search.json');

    $page = (new $class)->search('portrait', 1, new SourceQuery);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->items)->not->toBeEmpty();

    foreach ($page->items as $item) {
        expect($item->source)->toBe($key)
            ->and($item->sourceId)->toBeString()->not->toBe('')
            ->and($item->pageUrl)->not->toBe('')
            ->and($item->imageUrl)->not->toBe('');
    }
})->with('tier1ApiSources');

it('returns an empty page for an empty payload from :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/empty.json');

    $page = (new $class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->toBe([])
        ->and($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();
})->with('tier1ApiSources');

it('discards malformed items without throwing for :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/malformed.json');

    $page = (new $class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->toBe([])
        ->and($page->hasMore)->toBeFalse();
})->with('tier1ApiSources');

it('throws a source exception on an http error from :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/search.json', 500);

    (new $class)->search('portrait', 1, new SourceQuery);
})->with('tier1ApiSources')->throws(SourceException::class);

it('returns an empty page without any request when the query is blank for :key', function (string $key, string $class): void {
    Http::fake();

    $page = (new $class)->search('   ', 1, new SourceQuery);

    expect($page->items)->toBe([])
        ->and($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();

    Http::assertNothingSent();
})->with('tier1ApiSources');

it('explores :key without throwing and returns parsed items', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/search.json');

    $page = (new $class)->explore(1, new SourceQuery);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->items)->not->toBeEmpty();

    foreach ($page->items as $item) {
        expect($item->source)->toBe($key)
            ->and($item->imageUrl)->not->toBe('');
    }
})->with('tier1ApiSources');

it('declares the expected capabilities for :key', function (string $key, string $class): void {
    $capabilities = (new $class)->capabilities();

    expect($capabilities->supportsSearch)->toBeTrue()
        ->and($capabilities->needsKey)->toBeFalse()
        ->and($capabilities->supportsExplore)->toBe(in_array($key, ['deviantart', 'artstation'], true))
        ->and((new $class)->isConfigured())->toBeTrue();
})->with('tier1ApiSources');

it('reports deviantart as unconfigured without app credentials', function (): void {
    config([
        'inspiration.deviantart.client_id' => null,
        'inspiration.deviantart.client_secret' => null,
    ]);

    expect((new DeviantArtSource)->isConfigured())->toBeFalse();
});

it('caches the deviantart oauth token across searches', function (): void {
    fakeTier1Http('deviantart', 'deviantart/search.json');

    $source = new DeviantArtSource;
    $source->search('portrait', 1, new SourceQuery);
    $source->search('portrait', 2, new SourceQuery);

    $tokenRequests = Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'oauth2/token'))->count();

    expect($tokenRequests)->toBe(1);
});

it('wraps an unparseable body into a source exception', function (): void {
    Http::fake([
        '*wallhaven.cc/api/v1/search*' => Http::response('<html>not json</html>', 200),
    ]);

    (new WallhavenSource)->search('portrait', 1, new SourceQuery);
})->throws(SourceException::class);

it('registers every tier 1 adapter in the source manager', function (): void {
    $keys = app(SourceManager::class)->all()->keys()->all();

    expect($keys)->toContain('deviantart', 'artstation', 'wallhaven', 'openverse');
});

it('reports connectivity through test() for :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/search.json');

    expect((new $class)->test())->toBeTrue();
})->with('tier1ApiSources');

it('reports a failed connectivity test through test() for :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/search.json', 500);

    expect((new $class)->test())->toBeFalse();
})->with('tier1ApiSources');
