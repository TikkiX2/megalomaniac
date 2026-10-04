<?php

declare(strict_types=1);

use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\Exceptions\SourceException;
use App\Inspiration\SourceManager;
use App\Inspiration\Sources\Api\AreNaSource;
use App\Inspiration\Sources\Api\ArtInstituteChicagoSource;
use App\Inspiration\Sources\Api\ArtStationSource;
use App\Inspiration\Sources\Api\DeviantArtSource;
use App\Inspiration\Sources\Api\GelbooruSource;
use App\Inspiration\Sources\Api\MetMuseumSource;
use App\Inspiration\Sources\Api\OpenverseSource;
use App\Inspiration\Sources\Api\WallhavenSource;
use App\Inspiration\Sources\Api\ZerochanSource;
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
    'zerochan' => ['zerochan', ZerochanSource::class],
    'gelbooru' => ['gelbooru', GelbooruSource::class],
    'arena' => ['arena', AreNaSource::class],
    'met' => ['met', MetMuseumSource::class],
    'aic' => ['aic', ArtInstituteChicagoSource::class],
]);

dataset('tier1ApiHttpErrors', function (): array {
    $sources = [
        'deviantart' => ['deviantart', DeviantArtSource::class],
        'artstation' => ['artstation', ArtStationSource::class],
        'wallhaven' => ['wallhaven', WallhavenSource::class],
        'openverse' => ['openverse', OpenverseSource::class],
        'zerochan' => ['zerochan', ZerochanSource::class],
        'gelbooru' => ['gelbooru', GelbooruSource::class],
        'arena' => ['arena', AreNaSource::class],
        'met' => ['met', MetMuseumSource::class],
        'aic' => ['aic', ArtInstituteChicagoSource::class],
    ];

    $cases = [];

    foreach ($sources as $key => [$sourceKey, $class]) {
        foreach ([500, 403, 404] as $status) {
            $cases[$key.' '.$status] = [$sourceKey, $class, $status];
        }
    }

    return $cases;
});

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
        'zerochan' => [
            '*zerochan.net*' => Http::response($body, $status),
        ],
        'gelbooru' => [
            '*gelbooru.com/index.php*' => Http::response($body, $status),
        ],
        'arena' => [
            '*api.are.na/v2/search*' => Http::response($body, $status),
        ],
        'met' => [
            '*collectionapi.metmuseum.org/public/collection/v1/search*' => Http::response($body, $status),
            '*collectionapi.metmuseum.org/public/collection/v1/objects/*' => Http::response(inspirationFixture('met/object.json'), $status),
        ],
        'aic' => [
            '*api.artic.edu/api/v1/artworks/search*' => Http::response($body, $status),
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

it('keeps valid items while discarding malformed ones for :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/malformed.json');

    $page = (new $class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->toHaveCount(1)
        ->and($page->hasMore)->toBeFalse()
        ->and($page->items[0]->source)->toBe($key)
        ->and($page->items[0]->sourceId)->not->toBe('')
        ->and($page->items[0]->pageUrl)->not->toBe('')
        ->and($page->items[0]->imageUrl)->not->toBe('');
})->with('tier1ApiSources');

it('accepts a top-level list payload for artstation', function (): void {
    Http::fake([
        '*artstation.com/api/v2/feeds/projects.json*' => Http::response(inspirationFixture('artstation/list.json')),
    ]);

    $page = (new ArtStationSource)->search('portrait', 1, new SourceQuery);

    expect($page->items)->toHaveCount(1)
        ->and($page->hasMore)->toBeFalse()
        ->and($page->items[0]->sourceId)->toBe('201');
});

it('throws a source exception on an http error', function (string $key, string $class, int $status): void {
    fakeTier1Http($key, $key.'/search.json', $status);

    (new $class)->search('portrait', 1, new SourceQuery);
})->with('tier1ApiHttpErrors')->throws(SourceException::class);

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
        ->and($capabilities->supportsExplore)->toBe(in_array($key, ['deviantart', 'artstation', 'zerochan'], true))
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

    expect($keys)->toContain(
        'deviantart',
        'artstation',
        'wallhaven',
        'openverse',
        'zerochan',
        'gelbooru',
        'arena',
        'met',
        'aic',
    );
});

it('sends the configured user agent header to zerochan', function (): void {
    Http::fake([
        '*zerochan.net*' => Http::response(inspirationFixture('zerochan/search.json')),
    ]);

    $source = new ZerochanSource;
    $source->setCredentials(['user_agent' => 'MegalomaniacTest/9.9']);
    $source->search('portrait', 1, new SourceQuery);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('User-Agent', 'MegalomaniacTest/9.9'));
});

it('falls back to the default user agent when zerochan has no override', function (): void {
    Http::fake([
        '*zerochan.net*' => Http::response(inspirationFixture('zerochan/search.json')),
    ]);

    (new ZerochanSource)->search('portrait', 1, new SourceQuery);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('User-Agent', 'MegalomaniacInspiration/1.0'));
});

it('drops the gelbooru explicit filter when maturity is allowed', function (): void {
    Http::fake([
        '*gelbooru.com/index.php*' => Http::response(inspirationFixture('gelbooru/search.json')),
    ]);

    (new GelbooruSource)->search('portrait', 1, new SourceQuery(maturity: 'allowed'));

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'gelbooru.com')) {
            return false;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['tags'] ?? null) === 'portrait';
    });
});

it('applies the gelbooru explicit filter when maturity is safe', function (): void {
    Http::fake([
        '*gelbooru.com/index.php*' => Http::response(inspirationFixture('gelbooru/search.json')),
    ]);

    (new GelbooruSource)->search('portrait', 1, new SourceQuery(maturity: 'safe'));

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'gelbooru.com')) {
            return false;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['tags'] ?? null) === 'portrait -rating:explicit';
    });
});

it('reports connectivity through test() for :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/search.json');

    expect((new $class)->test())->toBeTrue();
})->with('tier1ApiSources');

it('reports a failed connectivity test through test() for :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/search.json', 500);

    expect((new $class)->test())->toBeFalse();
})->with('tier1ApiSources');
