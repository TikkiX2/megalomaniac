<?php

declare(strict_types=1);

use App\Inspiration\Contracts\Source;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\Exceptions\SourceException;
use App\Inspiration\SourceManager;
use App\Inspiration\Sources\Api\AreNaSource;
use App\Inspiration\Sources\Api\ArtInstituteChicagoSource;
use App\Inspiration\Sources\Api\ArtStationSource;
use App\Inspiration\Sources\Api\BandcampSource;
use App\Inspiration\Sources\Api\DeviantArtSource;
use App\Inspiration\Sources\Api\DiscogsSource;
use App\Inspiration\Sources\Api\EuropeanaSource;
use App\Inspiration\Sources\Api\FlickrSource;
use App\Inspiration\Sources\Api\GelbooruSource;
use App\Inspiration\Sources\Api\GiphySource;
use App\Inspiration\Sources\Api\MetMuseumSource;
use App\Inspiration\Sources\Api\OpenverseSource;
use App\Inspiration\Sources\Api\PexelsSource;
use App\Inspiration\Sources\Api\PixabaySource;
use App\Inspiration\Sources\Api\PixivSource;
use App\Inspiration\Sources\Api\RijksmuseumSource;
use App\Inspiration\Sources\Api\TumblrSource;
use App\Inspiration\Sources\Api\UnsplashSource;
use App\Inspiration\Sources\Api\WallhavenSource;
use App\Inspiration\Sources\Api\WikiArtSource;
use App\Inspiration\Sources\Api\ZerochanSource;
use App\Inspiration\Sources\Scrape\AwwwardsSource;
use App\Inspiration\Sources\Scrape\BehanceSource;
use App\Inspiration\Sources\Scrape\BrutalistSource;
use App\Inspiration\Sources\Scrape\CaraSource;
use App\Inspiration\Sources\Scrape\DarkModeDesignSource;
use App\Inspiration\Sources\Scrape\DesignspirationSource;
use App\Inspiration\Sources\Scrape\DribbbleSource;
use App\Inspiration\Sources\Scrape\GodlySource;
use App\Inspiration\Sources\Scrape\LapaNinjaSource;
use App\Inspiration\Sources\Scrape\MobbinSource;
use App\Inspiration\Sources\Scrape\NewgroundsSource;
use App\Inspiration\Sources\Scrape\PinterestSource;
use App\Inspiration\Sources\Scrape\PosterSpySource;
use App\Inspiration\Sources\Scrape\SaveeSource;
use App\Inspiration\Sources\Scrape\TrendListSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

dataset('keyedApiSources', [
    'flickr' => ['flickr', FlickrSource::class],
    'tumblr' => ['tumblr', TumblrSource::class],
    'unsplash' => ['unsplash', UnsplashSource::class],
    'pexels' => ['pexels', PexelsSource::class],
    'pixabay' => ['pixabay', PixabaySource::class],
    'discogs' => ['discogs', DiscogsSource::class],
    'giphy' => ['giphy', GiphySource::class],
    'europeana' => ['europeana', EuropeanaSource::class],
    'rijksmuseum' => ['rijksmuseum', RijksmuseumSource::class],
    'wikiart' => ['wikiart', WikiArtSource::class],
]);

dataset('keyedApiHttpErrors', function (): array {
    $sources = [
        'flickr' => ['flickr', FlickrSource::class],
        'tumblr' => ['tumblr', TumblrSource::class],
        'unsplash' => ['unsplash', UnsplashSource::class],
        'pexels' => ['pexels', PexelsSource::class],
        'pixabay' => ['pixabay', PixabaySource::class],
        'discogs' => ['discogs', DiscogsSource::class],
        'giphy' => ['giphy', GiphySource::class],
        'europeana' => ['europeana', EuropeanaSource::class],
        'rijksmuseum' => ['rijksmuseum', RijksmuseumSource::class],
        'wikiart' => ['wikiart', WikiArtSource::class],
    ];

    $cases = [];

    foreach ($sources as $key => [$sourceKey, $class]) {
        // 401 is included on purpose: a rejected key must surface as a
        // SourceException so the manager can degrade instead of crashing.
        foreach ([500, 403, 404, 401] as $status) {
            $cases[$key.' '.$status] = [$sourceKey, $class, $status];
        }
    }

    return $cases;
});

dataset('scrapeSources', [
    'designspiration' => ['designspiration', DesignspirationSource::class],
    'savee' => ['savee', SaveeSource::class],
    'trendlist' => ['trendlist', TrendListSource::class],
    'posterspy' => ['posterspy', PosterSpySource::class],
    'lapaninja' => ['lapaninja', LapaNinjaSource::class],
    'godly' => ['godly', GodlySource::class],
    'darkmode' => ['darkmode', DarkModeDesignSource::class],
    'brutalist' => ['brutalist', BrutalistSource::class],
    'behance' => ['behance', BehanceSource::class],
    'dribbble' => ['dribbble', DribbbleSource::class],
    'awwwards' => ['awwwards', AwwwardsSource::class],
    'newgrounds' => ['newgrounds', NewgroundsSource::class],
]);

/**
 * Scrape adapters with no native search box: search() delegates to explore().
 */
dataset('scrapeSourcesWithoutSearch', [
    'darkmode' => ['darkmode', DarkModeDesignSource::class],
    'brutalist' => ['brutalist', BrutalistSource::class],
]);

/**
 * Batch C sources whose fixtures carry titled cards; the generic happy test
 * only asserts the URL fields, so titles get their own dataset case.
 */
dataset('scrapeSourcesBatchC', [
    'behance' => ['behance', BehanceSource::class],
    'dribbble' => ['dribbble', DribbbleSource::class],
    'awwwards' => ['awwwards', AwwwardsSource::class],
]);

/**
 * Tier 3 adapters: opt-in sources backed by a network API.
 */
dataset('tier3ApiSources', [
    'pixiv' => ['pixiv', PixivSource::class],
    'bandcamp' => ['bandcamp', BandcampSource::class],
]);

/**
 * Tier 3 best-effort scrapers that read JSON embedded in the HTML shell. A
 * shell without decodable JSON (bot-wall / redesign) must raise a
 * SourceException so the manager can serve the cached payload.
 */
dataset('embeddedJsonScrapeSources', [
    'pinterest' => ['pinterest', PinterestSource::class],
    'cara' => ['cara', CaraSource::class],
]);

/**
 * Tier 3 HTTP failure matrix. Pixiv's token endpoint stays healthy so the
 * failure under test is the content request, not the OAuth exchange.
 */
dataset('tier3ApiHttpErrors', function (): array {
    $sources = [
        'pixiv' => ['pixiv', PixivSource::class],
        'bandcamp' => ['bandcamp', BandcampSource::class],
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
 * The canonical first credential field for an adapter, read from config.
 *
 * @param  class-string<Source>  $class
 */
function inspirationCredentialField(string $class): string
{
    /** @var array<string, array<int, string>> $fields */
    $fields = config('inspiration.credential_fields', []);
    $key = (new $class)->key();

    return $fields[$key][0] ?? 'key';
}

/**
 * Build a keyed adapter carrying the canonical credential bag.
 *
 * The field name comes from `inspiration.credential_fields` so token-based
 * sources (Discogs) and key-based sources are both covered.
 *
 * @param  class-string<Source>  $class
 */
function keyedInspirationSource(string $class): Source
{
    $source = new $class;
    $field = inspirationCredentialField($class);
    $source->setCredentials([$field => 'test-'.$field]);

    return $source;
}

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
            '*collectionapi.metmuseum.org/public/collection/v1/objects/*' => function (Request $request) use ($status) {
                $id = (int) basename((string) parse_url($request->url(), PHP_URL_PATH));
                $object = json_decode(inspirationFixture('met/object.json'), true);
                $object = is_array($object) ? $object : [];
                $object['objectID'] = $id;
                $object['objectURL'] = 'https://www.metmuseum.org/art/collection/search/'.$id;
                $object['primaryImage'] = 'https://images.metmuseum.org/CRDImages/ep/original/DT'.$id.'.jpg';
                $object['primaryImageSmall'] = 'https://images.metmuseum.org/CRDImages/ep/web-large/DT'.$id.'.jpg';
                $object['title'] = 'Object '.$id;

                return Http::response($object, $status);
            },
        ],
        'aic' => [
            '*api.artic.edu/api/v1/artworks/search*' => Http::response($body, $status),
        ],
        'flickr' => [
            '*api.flickr.com/services/rest*' => Http::response($body, $status),
        ],
        'tumblr' => [
            '*api.tumblr.com/v2/tagged*' => Http::response($body, $status),
        ],
        'unsplash' => [
            '*api.unsplash.com/search/photos*' => Http::response($body, $status),
        ],
        'pexels' => [
            '*api.pexels.com/v1/search*' => Http::response($body, $status),
        ],
        'pixabay' => [
            '*pixabay.com/api*' => Http::response($body, $status),
        ],
        'discogs' => [
            '*api.discogs.com/database/search*' => Http::response($body, $status),
        ],
        'giphy' => [
            '*api.giphy.com/v1/gifs/search*' => Http::response($body, $status),
            '*api.giphy.com/v1/gifs/trending*' => Http::response($body, $status),
        ],
        'europeana' => [
            '*api.europeana.eu/record/v2/search.json*' => Http::response($body, $status),
        ],
        'rijksmuseum' => [
            '*rijksmuseum.nl/api/en/collection*' => Http::response($body, $status),
        ],
        'wikiart' => [
            '*wikiart.org/en/api/2/*' => Http::response($body, $status),
        ],
        default => throw new InvalidArgumentException("Unknown tier 1 source [{$key}]."),
    });
}

/**
 * The URL patterns that stub every host a scrape adapter talks to.
 *
 * Brutalist has two feeds (primary + fallback), so the stub must cover both
 * hosts; the rest expose a single host.
 *
 * @return array<int, string>
 */
function scrapeHttpPatterns(string $key): array
{
    return match ($key) {
        'designspiration' => ['*designspiration.net*'],
        'savee' => ['*savee.it*'],
        'trendlist' => ['*trendlist.org*'],
        'posterspy' => ['*posterspy.com*'],
        'lapaninja' => ['*lapa.ninja*'],
        'godly' => ['*godly.website*'],
        'darkmode' => ['*darkmodedesign.com*'],
        'brutalist' => ['*brutalistwebsites.com*', '*brutalweb.xyz*'],
        'behance' => ['*behance.net*'],
        'dribbble' => ['*dribbble.com*'],
        'awwwards' => ['*awwwards.com*'],
        'newgrounds' => ['*newgrounds.com*'],
        default => throw new InvalidArgumentException("Unknown scrape source [{$key}]."),
    };
}

/**
 * Stub every host the given scrape adapter talks to, serving the fixture body.
 */
function fakeScrapeHttp(string $key, string $fixture, int $status = 200): void
{
    $body = inspirationFixture($fixture);
    $stubs = [];

    foreach (scrapeHttpPatterns($key) as $pattern) {
        $stubs[$pattern] = Http::response($body, $status);
    }

    Http::fake($stubs);
}

/**
 * Stub every host a tier 3 adapter talks to, serving the fixture body.
 *
 * Pixiv needs its OAuth exchange stubbed on top of the content endpoint; the
 * token stub is always healthy so a caller can force an HTTP error on the
 * search/discover request alone.
 */
function fakeTier3Http(string $key, string $fixture, int $status = 200): void
{
    $body = inspirationFixture($fixture);

    Http::fake(match ($key) {
        'pixiv' => [
            '*oauth.secure.pixiv.net/auth/token*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            '*app-api.pixiv.net/v1/search/illust*' => Http::response($body, $status),
        ],
        'bandcamp' => [
            '*bandcamp.com/api/discover/1/get_discover_items*' => Http::response($body, $status),
        ],
        default => throw new InvalidArgumentException("Unknown tier 3 source [{$key}]."),
    });
}

/**
 * The URL patterns that stub every host an embedded-JSON tier 3 adapter talks to.
 *
 * @return array<int, string>
 */
function embeddedHttpPatterns(string $key): array
{
    return match ($key) {
        'pinterest' => ['*pinterest.com*'],
        'cara' => ['*cara.app*'],
        default => throw new InvalidArgumentException("Unknown embedded-JSON source [{$key}]."),
    };
}

/**
 * Stub every host an embedded-JSON adapter talks to, serving the fixture body.
 */
function fakeEmbeddedHttp(string $key, string $fixture, int $status = 200): void
{
    fakeEmbeddedBody($key, inspirationFixture($fixture), $status);
}

/**
 * Stub every host an embedded-JSON adapter talks to with an inline body.
 */
function fakeEmbeddedBody(string $key, string $body, int $status = 200): void
{
    $stubs = [];

    foreach (embeddedHttpPatterns($key) as $pattern) {
        $stubs[$pattern] = Http::response($body, $status);
    }

    Http::fake($stubs);
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

it('resolves distinct met objects per id', function (): void {
    fakeTier1Http('met', 'met/search.json');

    $page = (new MetMuseumSource)->search('portrait', 1, new SourceQuery);

    $ids = array_map(static fn ($item): string => $item->sourceId, $page->items);

    expect($page->items)->toHaveCount(3)
        ->and($ids)->toBe(['101', '102', '103'])
        ->and(array_unique($ids))->toHaveCount(3);
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
        'flickr',
        'tumblr',
        'unsplash',
        'pexels',
        'pixabay',
        'discogs',
        'giphy',
        'europeana',
        'rijksmuseum',
        'wikiart',
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

it('maps a happy payload into a non-empty page for keyed :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/search.json');

    $page = keyedInspirationSource($class)->search('portrait', 1, new SourceQuery);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->items)->not->toBeEmpty();

    foreach ($page->items as $item) {
        expect($item->source)->toBe($key)
            ->and($item->sourceId)->toBeString()->not->toBe('')
            ->and($item->pageUrl)->not->toBe('')
            ->and($item->imageUrl)->not->toBe('');
    }
})->with('keyedApiSources');

it('returns an empty page for an empty payload from keyed :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/empty.json');

    $page = keyedInspirationSource($class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->toBe([])
        ->and($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();
})->with('keyedApiSources');

it('keeps valid items while discarding malformed ones for keyed :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/malformed.json');

    $page = keyedInspirationSource($class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->toHaveCount(1)
        ->and($page->hasMore)->toBeFalse()
        ->and($page->items[0]->source)->toBe($key)
        ->and($page->items[0]->sourceId)->not->toBe('')
        ->and($page->items[0]->pageUrl)->not->toBe('')
        ->and($page->items[0]->imageUrl)->not->toBe('');
})->with('keyedApiSources');

it('throws a source exception on an http error for keyed :key', function (string $key, string $class, int $status): void {
    fakeTier1Http($key, $key.'/search.json', $status);

    keyedInspirationSource($class)->search('portrait', 1, new SourceQuery);
})->with('keyedApiHttpErrors')->throws(SourceException::class);

it('returns an empty page without any request when the keyed query is blank for :key', function (string $key, string $class): void {
    Http::fake();

    $page = keyedInspirationSource($class)->search('   ', 1, new SourceQuery);

    expect($page->items)->toBe([])
        ->and($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();

    Http::assertNothingSent();
})->with('keyedApiSources');

it('explores keyed :key without throwing and returns parsed items', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/search.json');

    $page = keyedInspirationSource($class)->explore(1, new SourceQuery);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->items)->not->toBeEmpty();

    foreach ($page->items as $item) {
        expect($item->source)->toBe($key)
            ->and($item->imageUrl)->not->toBe('');
    }
})->with('keyedApiSources');

it('declares the expected capabilities for keyed :key', function (string $key, string $class): void {
    $source = keyedInspirationSource($class);
    $capabilities = $source->capabilities();

    expect($capabilities->supportsSearch)->toBeTrue()
        ->and($capabilities->supportsExplore)->toBe(in_array($key, ['flickr', 'giphy', 'wikiart'], true))
        ->and($capabilities->needsKey)->toBeTrue()
        ->and($capabilities->hasMaturityLevels)->toBe(in_array($key, ['flickr', 'pixabay', 'giphy'], true))
        ->and($source->isConfigured())->toBeTrue();
})->with('keyedApiSources');

it('reports keyed :key as unconfigured without credentials', function (string $key, string $class): void {
    expect((new $class)->isConfigured())->toBeFalse();
})->with('keyedApiSources');

it('treats an empty credential value as unconfigured for keyed :key', function (string $key, string $class): void {
    $source = new $class;
    $source->setCredentials([inspirationCredentialField($class) => '']);

    expect($source->isConfigured())->toBeFalse();
})->with('keyedApiSources');

it('reports connectivity through test() for keyed :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/search.json');

    expect(keyedInspirationSource($class)->test())->toBeTrue();
})->with('keyedApiSources');

it('reports a failed connectivity test through test() for keyed :key', function (string $key, string $class): void {
    fakeTier1Http($key, $key.'/search.json', 500);

    expect(keyedInspirationSource($class)->test())->toBeFalse();
})->with('keyedApiSources');

it('sends the unsplash client id header', function (): void {
    Http::fake([
        '*api.unsplash.com/search/photos*' => Http::response(inspirationFixture('unsplash/search.json')),
    ]);

    keyedInspirationSource(UnsplashSource::class)->search('portrait', 1, new SourceQuery);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Client-ID test-key'));
});

it('sends the pexels bearer token', function (): void {
    Http::fake([
        '*api.pexels.com/v1/search*' => Http::response(inspirationFixture('pexels/search.json')),
    ]);

    keyedInspirationSource(PexelsSource::class)->search('portrait', 1, new SourceQuery);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('applies the flickr safe search filter when maturity is safe', function (): void {
    Http::fake([
        '*api.flickr.com/services/rest*' => Http::response(inspirationFixture('flickr/search.json')),
    ]);

    keyedInspirationSource(FlickrSource::class)->search('portrait', 1, new SourceQuery(maturity: 'safe'));

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['api_key'] ?? null) === 'test-key'
            && ($query['safe_search'] ?? null) === '1'
            && ($query['format'] ?? null) === 'json'
            && ($query['nojsoncallback'] ?? null) === '1';
    });
});

it('drops the flickr safe search filter when maturity is allowed', function (): void {
    Http::fake([
        '*api.flickr.com/services/rest*' => Http::response(inspirationFixture('flickr/search.json')),
    ]);

    keyedInspirationSource(FlickrSource::class)->search('portrait', 1, new SourceQuery(maturity: 'allowed'));

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['safe_search'] ?? null) === '3';
    });
});

it('applies the pixabay safe search filter when maturity is safe', function (): void {
    Http::fake([
        '*pixabay.com/api*' => Http::response(inspirationFixture('pixabay/search.json')),
    ]);

    keyedInspirationSource(PixabaySource::class)->search('portrait', 1, new SourceQuery(maturity: 'safe'));

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['key'] ?? null) === 'test-key'
            && ($query['safesearch'] ?? null) === '1';
    });
});

it('drops the pixabay safe search filter when maturity is allowed', function (): void {
    Http::fake([
        '*pixabay.com/api*' => Http::response(inspirationFixture('pixabay/search.json')),
    ]);

    keyedInspirationSource(PixabaySource::class)->search('portrait', 1, new SourceQuery(maturity: 'allowed'));

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['safesearch'] ?? null) === '0';
    });
});

it('never advertises pagination for tumblr even with a next link', function (): void {
    Http::fake([
        '*api.tumblr.com/v2/tagged*' => Http::response(inspirationFixture('tumblr/search.json')),
    ]);

    $page = keyedInspirationSource(TumblrSource::class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->not->toBeEmpty()
        ->and($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();
});

it('clamps the pixabay pagination to the 500 hit cap', function (): void {
    Http::fake([
        '*pixabay.com/api*' => Http::response(['totalHits' => 1000, 'hits' => []]),
    ]);

    $source = keyedInspirationSource(PixabaySource::class);

    $withinCap = $source->search('portrait', 20, new SourceQuery);
    $atCap = $source->search('portrait', 21, new SourceQuery);

    expect($withinCap->hasMore)->toBeTrue()
        ->and($withinCap->nextPage)->toBe(21)
        ->and($atCap->hasMore)->toBeFalse()
        ->and($atCap->nextPage)->toBeNull();
});

it('sends the discogs token as an authorization header', function (): void {
    Http::fake([
        '*api.discogs.com/database/search*' => Http::response(inspirationFixture('discogs/search.json')),
    ]);

    keyedInspirationSource(DiscogsSource::class)->search('portrait', 1, new SourceQuery);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Discogs token=test-token'));
});

it('builds discogs release page urls from the result id', function (): void {
    Http::fake([
        '*api.discogs.com/database/search*' => Http::response(inspirationFixture('discogs/search.json')),
    ]);

    $page = keyedInspirationSource(DiscogsSource::class)->search('portrait', 1, new SourceQuery);

    expect($page->items[0]->sourceId)->toBe('249504')
        ->and($page->items[0]->pageUrl)->toBe('https://www.discogs.com/release/249504')
        ->and($page->items[0]->imageUrl)->toBe('https://i.discogs.com/cover-249504.jpg')
        ->and($page->items[0]->thumbnailUrl)->toBe('https://i.discogs.com/thumb-249504.jpg');
});

it('applies the giphy rating filter when maturity is safe', function (): void {
    Http::fake([
        '*api.giphy.com/v1/gifs/search*' => Http::response(inspirationFixture('giphy/search.json')),
    ]);

    keyedInspirationSource(GiphySource::class)->search('portrait', 1, new SourceQuery(maturity: 'safe'));

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['api_key'] ?? null) === 'test-key'
            && ($query['rating'] ?? null) === 'pg'
            && ($query['limit'] ?? null) === '24';
    });
});

it('relaxes the giphy rating filter when maturity is allowed', function (): void {
    Http::fake([
        '*api.giphy.com/v1/gifs/search*' => Http::response(inspirationFixture('giphy/search.json')),
    ]);

    keyedInspirationSource(GiphySource::class)->search('portrait', 1, new SourceQuery(maturity: 'allowed'));

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['rating'] ?? null) === 'r';
    });
});

it('offsets giphy pages and uses the trending endpoint for explore', function (): void {
    Http::fake([
        '*api.giphy.com/v1/gifs/search*' => Http::response(inspirationFixture('giphy/search.json')),
        '*api.giphy.com/v1/gifs/trending*' => Http::response(inspirationFixture('giphy/search.json')),
    ]);

    $source = keyedInspirationSource(GiphySource::class);
    $source->search('portrait', 3, new SourceQuery);
    $source->explore(1, new SourceQuery);

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), '/gifs/search')) {
            return false;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['offset'] ?? null) === '48'
            && ($query['q'] ?? null) === 'portrait';
    });

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/gifs/trending'));
});

it('advertises more giphy pages when a full page is returned', function (): void {
    $data = [];

    for ($i = 1; $i <= 24; $i++) {
        $data[] = [
            'id' => 'gif-'.$i,
            'slug' => 'gif-'.$i,
            'title' => 'Gif '.$i,
            'images' => [
                'original' => ['url' => 'https://media.giphy.com/media/gif-'.$i.'/giphy.gif'],
                'fixed_width' => ['url' => 'https://media.giphy.com/media/gif-'.$i.'/200w.gif'],
            ],
        ];
    }

    Http::fake([
        '*api.giphy.com/v1/gifs/search*' => Http::response(['data' => $data]),
    ]);

    $page = keyedInspirationSource(GiphySource::class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->toHaveCount(24)
        ->and($page->hasMore)->toBeTrue()
        ->and($page->nextPage)->toBe(2);
});

it('sends the europeana key and start offset', function (): void {
    Http::fake([
        '*api.europeana.eu/record/v2/search.json*' => Http::response(inspirationFixture('europeana/search.json')),
    ]);

    keyedInspirationSource(EuropeanaSource::class)->search('portrait', 2, new SourceQuery);

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['wskey'] ?? null) === 'test-key'
            && ($query['rows'] ?? null) === '24'
            && ($query['start'] ?? null) === '25'
            && ($query['query'] ?? null) === 'portrait';
    });
});

it('drops europeana items that cannot be redistributed', function (): void {
    Http::fake([
        '*api.europeana.eu/record/v2/search.json*' => Http::response([
            'totalResults' => 2,
            'items' => [
                [
                    'id' => '/a',
                    'guid' => 'https://www.europeana.eu/en/item/a',
                    'edmPreview' => ['https://api.europeana.eu/thumb/a'],
                    'previewNoDistribute' => true,
                ],
                [
                    'id' => '/b',
                    'guid' => 'https://www.europeana.eu/en/item/b',
                    'edmPreview' => ['https://api.europeana.eu/thumb/b'],
                    'previewNoDistribute' => false,
                ],
            ],
        ]),
    ]);

    $page = keyedInspirationSource(EuropeanaSource::class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->toHaveCount(1)
        ->and($page->items[0]->sourceId)->toBe('/b')
        ->and($page->items[0]->imageUrl)->toBe('https://api.europeana.eu/thumb/b');
});

it('sends the rijksmuseum key and image-only filter', function (): void {
    Http::fake([
        '*rijksmuseum.nl/api/en/collection*' => Http::response(inspirationFixture('rijksmuseum/search.json')),
    ]);

    keyedInspirationSource(RijksmuseumSource::class)->search('portrait', 2, new SourceQuery);

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['key'] ?? null) === 'test-key'
            && ($query['q'] ?? null) === 'portrait'
            && ($query['ps'] ?? null) === '24'
            && ($query['p'] ?? null) === '2'
            && ($query['imgonly'] ?? null) === 'true';
    });
});

it('advertises more rijksmuseum pages when a full page is returned', function (): void {
    $artObjects = [];

    for ($i = 1; $i <= 24; $i++) {
        $artObjects[] = [
            'objectNumber' => 'SK-'.$i,
            'title' => 'Object '.$i,
            'principalOrFirstMaker' => 'Maker '.$i,
            'webImage' => ['url' => 'https://example.org/'.$i.'.jpg'],
            'links' => ['web' => 'https://www.rijksmuseum.nl/en/collection/SK-'.$i],
        ];
    }

    Http::fake([
        '*rijksmuseum.nl/api/en/collection*' => Http::response(['count' => 24, 'artObjects' => $artObjects]),
    ]);

    $page = keyedInspirationSource(RijksmuseumSource::class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->toHaveCount(24)
        ->and($page->hasMore)->toBeTrue()
        ->and($page->nextPage)->toBe(2);
});

it('builds wikiart page urls and sends the term and key', function (): void {
    Http::fake([
        '*wikiart.org/en/api/2/*' => Http::response(inspirationFixture('wikiart/search.json')),
    ]);

    $page = keyedInspirationSource(WikiArtSource::class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->toHaveCount(2)
        ->and($page->items[0]->sourceId)->toBe('57727444edc2cb3880cb7bf6')
        ->and($page->items[0]->pageUrl)->toBe('https://www.wikiart.org/en/leonardo-da-vinci/mona-lisa')
        ->and($page->items[0]->author)->toBe('Leonardo da Vinci')
        // PaintingSearch sends `url: null`, so the slug is derived from the title.
        ->and($page->items[1]->pageUrl)->toBe('https://www.wikiart.org/en/giuseppe-arcimboldo/portrait-of-eve');

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), '/PaintingSearch')) {
            return false;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['term'] ?? null) === 'portrait'
            && ($query['key'] ?? null) === 'test-key';
    });
});

it('explores wikiart through the most viewed feed', function (): void {
    Http::fake([
        '*wikiart.org/en/api/2/*' => Http::response(inspirationFixture('wikiart/search.json')),
    ]);

    $page = keyedInspirationSource(WikiArtSource::class)->explore(1, new SourceQuery);

    expect($page->items)->not->toBeEmpty()
        ->and($page->hasMore)->toBeFalse();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'MostViewedPaintings'));
});

it('scrapes a happy feed into a non-empty page for :key', function (string $key, string $class): void {
    fakeScrapeHttp($key, $key.'/feed.html');

    $page = (new $class)->search('portrait', 1, new SourceQuery);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->items)->not->toBeEmpty()
        ->and($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();

    foreach ($page->items as $item) {
        expect($item->source)->toBe($key)
            ->and($item->sourceId)->toBeString()->not->toBe('')
            ->and($item->pageUrl)->not->toBe('')
            ->and($item->imageUrl)->not->toBe('');
    }
})->with('scrapeSources');

it('parses a non-empty title for batch C :key', function (string $key, string $class): void {
    fakeScrapeHttp($key, $key.'/feed.html');

    $page = (new $class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->not->toBeEmpty();

    foreach ($page->items as $item) {
        expect($item->title)->toBeString()->not->toBe('');
    }
})->with('scrapeSourcesBatchC');

it('explores the :key feed without throwing and returns parsed items', function (string $key, string $class): void {
    fakeScrapeHttp($key, $key.'/feed.html');

    $page = (new $class)->explore(1, new SourceQuery);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->items)->not->toBeEmpty()
        ->and($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();

    foreach ($page->items as $item) {
        expect($item->source)->toBe($key)
            ->and($item->imageUrl)->not->toBe('');
    }
})->with('scrapeSources');

it('requests the expected search url for :key', function (string $key, string $class): void {
    fakeScrapeHttp($key, $key.'/feed.html');

    (new $class)->search('portrait', 1, new SourceQuery);

    $expected = match ($key) {
        'designspiration' => 'https://www.designspiration.net/search/portrait/',
        'savee' => 'https://savee.it/search/?q=portrait',
        'trendlist' => 'https://trendlist.org/?search=portrait',
        'posterspy' => 'https://posterspy.com/?s=portrait',
        'lapaninja' => 'https://www.lapa.ninja/search?s=portrait',
        'godly' => 'https://godly.website/search?q=portrait',
        'behance' => 'https://www.behance.net/search/projects?search=portrait',
        'dribbble' => 'https://dribbble.com/search/shots?q=portrait',
        'awwwards' => 'https://www.awwwards.com/search/?q=portrait',
        'newgrounds' => 'https://www.newgrounds.com/search/conduct/post?query=portrait&category=art',
        // No native search box: search() delegates to the explore feed.
        'darkmode' => 'https://www.darkmodedesign.com/',
        'brutalist' => 'https://brutalistwebsites.com/',
    };

    Http::assertSent(fn (Request $request): bool => $request->url() === $expected);
})->with('scrapeSources');

it('delegates search to explore and logs a warning for :key', function (string $key, string $class): void {
    Log::spy();
    fakeScrapeHttp($key, $key.'/feed.html');

    $page = (new $class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->not->toBeEmpty();

    Log::shouldHaveReceived('warning')->once();
})->with('scrapeSourcesWithoutSearch');

it('requests the expected explore url for :key', function (string $key, string $class): void {
    fakeScrapeHttp($key, $key.'/feed.html');

    (new $class)->explore(1, new SourceQuery);

    $expected = match ($key) {
        'designspiration' => 'https://www.designspiration.net/explore/',
        'savee' => 'https://savee.it/',
        'trendlist' => 'https://trendlist.org/',
        'posterspy' => 'https://posterspy.com/',
        'lapaninja' => 'https://www.lapa.ninja/',
        'godly' => 'https://godly.website/',
        'darkmode' => 'https://www.darkmodedesign.com/',
        'brutalist' => 'https://brutalistwebsites.com/',
        'behance' => 'https://www.behance.net/galleries',
        'dribbble' => 'https://dribbble.com/shots/popular',
        'awwwards' => 'https://www.awwwards.com/websites/',
        'newgrounds' => 'https://www.newgrounds.com/art/browse?sort=date',
    };

    Http::assertSent(fn (Request $request): bool => $request->url() === $expected);
})->with('scrapeSources');

it('degrades to an empty page when :key markup is broken', function (string $key, string $class): void {
    fakeScrapeHttp($key, $key.'/broken.html');

    $search = (new $class)->search('portrait', 1, new SourceQuery);
    $explore = (new $class)->explore(1, new SourceQuery);

    expect($search->items)->toBe([])
        ->and($search->hasMore)->toBeFalse()
        ->and($search->nextPage)->toBeNull()
        ->and($explore->items)->toBe([])
        ->and($explore->hasMore)->toBeFalse()
        ->and($explore->nextPage)->toBeNull();
})->with('scrapeSources');

it('returns an empty page without a request for page two of :key', function (string $key, string $class): void {
    Http::fake();

    $page = (new $class)->search('portrait', 2, new SourceQuery);

    expect($page->items)->toBe([])
        ->and($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();

    // Scraped sites expose one page per term; page 2 must not hit the network.
    Http::assertNothingSent();
})->with('scrapeSources');

it('returns an empty page without any request when the scrape query is blank for :key', function (string $key, string $class): void {
    Http::fake();

    $page = (new $class)->search('   ', 1, new SourceQuery);

    expect($page->items)->toBe([])
        ->and($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();

    Http::assertNothingSent();
})->with('scrapeSources');

it('declares the expected capabilities for scrape :key', function (string $key, string $class): void {
    $source = new $class;
    $capabilities = $source->capabilities();

    $supportsSearch = match ($key) {
        // Only Dark Mode and Brutalist lack a native search box.
        'lapaninja', 'godly', 'designspiration', 'savee', 'trendlist', 'posterspy',
        'behance', 'dribbble', 'awwwards', 'newgrounds' => true,
        default => false,
    };

    expect($capabilities->supportsSearch)->toBe($supportsSearch)
        ->and($capabilities->supportsExplore)->toBeTrue()
        ->and($capabilities->needsKey)->toBeFalse()
        ->and($capabilities->hasMaturityLevels)->toBeFalse()
        ->and($capabilities->maxPageSize)->toBe(24)
        ->and($source->isConfigured())->toBeTrue();
})->with('scrapeSources');

it('reports connectivity through test() for scrape :key', function (string $key, string $class): void {
    fakeScrapeHttp($key, $key.'/feed.html');

    expect((new $class)->test())->toBeTrue();
})->with('scrapeSources');

it('reports a failed connectivity test through test() for scrape :key', function (string $key, string $class): void {
    fakeScrapeHttp($key, $key.'/feed.html', 500);

    expect((new $class)->test())->toBeFalse();
})->with('scrapeSources');

it('probes the Awwwards feed rather than the 404 search page for connectivity', function (): void {
    Http::fake(['*awwwards.com*' => Http::response(inspirationFixture('awwwards/feed.html'))]);

    expect((new AwwwardsSource)->test())->toBeTrue();

    // The live search path answers 404, so test() must stay on the feed.
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.awwwards.com/websites/');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/search/'));
});

it('does not request the secondary brutalist feed when the primary yields cards', function (): void {
    fakeScrapeHttp('brutalist', 'brutalist/feed.html');

    $page = (new BrutalistSource)->explore(1, new SourceQuery);

    expect($page->items)->not->toBeEmpty();

    // No mix: the fallback host is never touched while the primary has cards.
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'brutalweb.xyz'));
});

it('falls back to the Brutal Web feed and resolves relative urls against its own host', function (): void {
    Http::fake([
        '*brutalistwebsites.com*' => Http::response(inspirationFixture('brutalist/broken.html')),
        '*brutalweb.xyz*' => Http::response(inspirationFixture('brutalist/fallback.html')),
    ]);

    $page = (new BrutalistSource)->explore(1, new SourceQuery);

    expect($page->items)->not->toBeEmpty();

    $first = $page->items[0];

    // The active feed's host is the resolver base, not the primary host.
    expect($first->pageUrl)->toStartWith('https://brutalweb.xyz/')
        ->and($first->imageUrl)->toStartWith('https://brutalweb.xyz/')
        ->and($first->pageUrl)->not->toContain('brutalistwebsites.com')
        ->and($first->imageUrl)->not->toContain('brutalistwebsites.com');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'brutalweb.xyz'));
});

it('registers every scrape adapter in the source manager', function (): void {
    $keys = app(SourceManager::class)->all()->keys()->all();

    expect($keys)->toContain(
        'designspiration',
        'savee',
        'trendlist',
        'posterspy',
        'lapaninja',
        'godly',
        'darkmode',
        'brutalist',
        'behance',
        'dribbble',
        'awwwards',
        'newgrounds',
    );
});

/*
|--------------------------------------------------------------------------
| Tier 3 adapters (Pixiv, Bandcamp)
|--------------------------------------------------------------------------
*/

it('maps a happy payload into a non-empty page for tier 3 :key', function (string $key, string $class): void {
    fakeTier3Http($key, $key.'/search.json');

    $page = keyedInspirationSource($class)->search('portrait', 1, new SourceQuery);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->items)->not->toBeEmpty();

    foreach ($page->items as $item) {
        expect($item->source)->toBe($key)
            ->and($item->sourceId)->toBeString()->not->toBe('')
            ->and($item->pageUrl)->not->toBe('')
            ->and($item->imageUrl)->not->toBe('');
    }
})->with('tier3ApiSources');

it('returns an empty page for an empty payload from tier 3 :key', function (string $key, string $class): void {
    fakeTier3Http($key, $key.'/empty.json');

    $page = keyedInspirationSource($class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->toBe([])
        ->and($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();
})->with('tier3ApiSources');

it('keeps valid items while discarding malformed ones for tier 3 :key', function (string $key, string $class): void {
    fakeTier3Http($key, $key.'/malformed.json');

    $page = keyedInspirationSource($class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->toHaveCount(1)
        ->and($page->hasMore)->toBeFalse()
        ->and($page->items[0]->source)->toBe($key)
        ->and($page->items[0]->sourceId)->not->toBe('')
        ->and($page->items[0]->pageUrl)->not->toBe('')
        ->and($page->items[0]->imageUrl)->not->toBe('');
})->with('tier3ApiSources');

it('throws a source exception on an http error for tier 3 :key', function (string $key, string $class, int $status): void {
    fakeTier3Http($key, $key.'/search.json', $status);

    keyedInspirationSource($class)->search('portrait', 1, new SourceQuery);
})->with('tier3ApiHttpErrors')->throws(SourceException::class);

it('wraps an unparseable tier 3 body into a source exception for :key', function (string $key, string $class): void {
    Http::fake(match ($key) {
        'pixiv' => [
            '*oauth.secure.pixiv.net/auth/token*' => Http::response(['access_token' => 'fake-token']),
            '*app-api.pixiv.net/v1/search/illust*' => Http::response('<html>not json</html>', 200),
        ],
        'bandcamp' => [
            '*bandcamp.com/api/discover/1/get_discover_items*' => Http::response('<html>not json</html>', 200),
        ],
    });

    keyedInspirationSource($class)->search('portrait', 1, new SourceQuery);
})->with('tier3ApiSources')->throws(SourceException::class);

it('returns an empty page without any request when the tier 3 query is blank for :key', function (string $key, string $class): void {
    Http::fake();

    $page = keyedInspirationSource($class)->search('   ', 1, new SourceQuery);

    expect($page->items)->toBe([])
        ->and($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();

    Http::assertNothingSent();
})->with('tier3ApiSources');

it('explores tier 3 :key without throwing and returns parsed items', function (string $key, string $class): void {
    fakeTier3Http($key, $key.'/search.json');

    $page = keyedInspirationSource($class)->explore(1, new SourceQuery);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->items)->not->toBeEmpty();

    foreach ($page->items as $item) {
        expect($item->source)->toBe($key)
            ->and($item->imageUrl)->not->toBe('');
    }
})->with('tier3ApiSources');

it('declares the expected capabilities for tier 3 :key', function (string $key, string $class): void {
    $source = keyedInspirationSource($class);
    $capabilities = $source->capabilities();

    $expected = match ($key) {
        'pixiv' => ['search' => true, 'explore' => true, 'key' => true, 'maturity' => true],
        'bandcamp' => ['search' => false, 'explore' => true, 'key' => false, 'maturity' => false],
    };

    expect($capabilities->supportsSearch)->toBe($expected['search'])
        ->and($capabilities->supportsExplore)->toBe($expected['explore'])
        ->and($capabilities->needsKey)->toBe($expected['key'])
        ->and($capabilities->hasMaturityLevels)->toBe($expected['maturity'])
        ->and($capabilities->maxPageSize)->toBe(24)
        ->and($source->isConfigured())->toBeTrue();
})->with('tier3ApiSources');

it('reports connectivity through test() for tier 3 :key', function (string $key, string $class): void {
    fakeTier3Http($key, $key.'/search.json');

    expect(keyedInspirationSource($class)->test())->toBeTrue();
})->with('tier3ApiSources');

it('reports a failed connectivity test through test() for tier 3 :key', function (string $key, string $class): void {
    fakeTier3Http($key, $key.'/search.json', 500);

    expect(keyedInspirationSource($class)->test())->toBeFalse();
})->with('tier3ApiSources');

it('registers every tier 3 adapter in the source manager', function (): void {
    $keys = app(SourceManager::class)->all()->keys()->all();

    expect($keys)->toContain('pixiv', 'bandcamp');
});

it('reports pixiv as unconfigured without a refresh token', function (): void {
    expect((new PixivSource)->isConfigured())->toBeFalse();

    $source = new PixivSource;
    $source->setCredentials(['refresh_token' => '']);

    expect($source->isConfigured())->toBeFalse();
});

it('skips pixiv ugoira entries', function (): void {
    fakeTier3Http('pixiv', 'pixiv/search.json');

    $page = keyedInspirationSource(PixivSource::class)->search('portrait', 1, new SourceQuery);

    $ids = array_map(static fn ($item): string => $item->sourceId, $page->items);

    expect($ids)->toBe(['98765432', '98765433'])
        ->and($ids)->not->toContain('98765434');
});

it('maps the full pixiv item fields and maturity labels', function (): void {
    fakeTier3Http('pixiv', 'pixiv/search.json');

    $page = keyedInspirationSource(PixivSource::class)->search('portrait', 1, new SourceQuery);

    $safe = $page->items[0];
    $restricted = $page->items[1];

    expect($safe->title)->toBe('Ember Portrait')
        ->and($safe->author)->toBe('ember-artist')
        ->and($safe->authorUrl)->toBe('https://www.pixiv.net/en/users/11122233')
        ->and($safe->thumbnailUrl)->toBe('https://i.pximg.net/c/540x540_70/img-master/img/2024/01/01/00/00/00/98765432_p0_master1200.jpg')
        ->and($safe->tags)->toBe(['portrait', 'original'])
        ->and($safe->maturity)->toBe('safe')
        ->and($safe->width)->toBe(1200)
        ->and($safe->height)->toBe(1600)
        ->and($restricted->title)->toBe('Ash Study')
        ->and($restricted->author)->toBe('ash-artist')
        ->and($restricted->tags)->toBe(['portrait', 'r-18'])
        // x_restrict > 0 must surface as the mature label.
        ->and($restricted->maturity)->toBe('mature');
});

it('advertises the next pixiv page from next_url below the page size', function (): void {
    fakeTier3Http('pixiv', 'pixiv/next-page.json');

    $page = keyedInspirationSource(PixivSource::class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->toHaveCount(2)
        ->and($page->hasMore)->toBeTrue()
        ->and($page->nextPage)->toBe(2);
});

it('pins the pixiv first page when next_url is null', function (): void {
    fakeTier3Http('pixiv', 'pixiv/search.json');

    $page = keyedInspirationSource(PixivSource::class)->search('portrait', 1, new SourceQuery);

    expect($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();
});

it('caches the pixiv access token across searches', function (): void {
    fakeTier3Http('pixiv', 'pixiv/search.json');

    $source = keyedInspirationSource(PixivSource::class);
    $source->search('portrait', 1, new SourceQuery);
    $source->search('portrait', 2, new SourceQuery);

    $tokenRequests = Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'auth/token'))->count();

    expect($tokenRequests)->toBe(1);
});

it('keys the pixiv token cache per refresh token so accounts stay isolated', function (): void {
    fakeTier3Http('pixiv', 'pixiv/search.json');

    $first = new PixivSource;
    $first->setCredentials(['refresh_token' => 'account-a']);
    $first->search('portrait', 1, new SourceQuery);

    $second = new PixivSource;
    $second->setCredentials(['refresh_token' => 'account-b']);
    $second->search('portrait', 1, new SourceQuery);

    $tokenRequests = Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'auth/token'))->count();

    expect($tokenRequests)->toBe(2);
});

it('wraps an invalid pixiv refresh token into a source exception', function (): void {
    Http::fake([
        '*oauth.secure.pixiv.net/auth/token*' => Http::response(['error' => 'invalid_grant'], 400),
        '*app-api.pixiv.net/v1/search/illust*' => Http::response(['illusts' => []]),
    ]);

    keyedInspirationSource(PixivSource::class)->search('portrait', 1, new SourceQuery);
})->throws(SourceException::class, 'pixiv: token inválido o expirado — revisá tu refresh token');

it('sends the pixiv bearer token, offset and search target', function (): void {
    fakeTier3Http('pixiv', 'pixiv/search.json');

    keyedInspirationSource(PixivSource::class)->search('portrait', 2, new SourceQuery(maturity: 'allowed'));

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'app-api.pixiv.net/v1/search/illust')) {
            return false;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['word'] ?? null) === 'portrait'
            && ($query['search_target'] ?? null) === 'partial_match_for_tags'
            && ($query['offset'] ?? null) === '24'
            && ! array_key_exists('filter', $query)
            && $request->hasHeader('Authorization', 'Bearer fake-token');
    });
});

it('adds the pixiv for_ios filter when maturity is safe', function (): void {
    fakeTier3Http('pixiv', 'pixiv/search.json');

    keyedInspirationSource(PixivSource::class)->search('portrait', 1, new SourceQuery(maturity: 'safe'));

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'app-api.pixiv.net/v1/search/illust')) {
            return false;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['filter'] ?? null) === 'for_ios';
    });
});

it('delegates bandcamp search to the discover feed and logs a warning', function (): void {
    Log::spy();
    fakeTier3Http('bandcamp', 'bandcamp/search.json');

    $page = (new BandcampSource)->search('portrait', 1, new SourceQuery);

    expect($page->items)->not->toBeEmpty();

    Log::shouldHaveReceived('warning')->once();
});

it('posts the minimal discover payload to bandcamp', function (): void {
    fakeTier3Http('bandcamp', 'bandcamp/search.json');

    (new BandcampSource)->explore(1, new SourceQuery);

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'get_discover_items')) {
            return false;
        }

        $data = $request->data();

        return $request->method() === 'POST'
            && ($data['category'] ?? null) === 'music'
            && ($data['genre'] ?? null) === 'all'
            && ($data['region'] ?? null) === 'all'
            && ($data['query'] ?? null) === ''
            && ($data['count'] ?? null) === 24;
    });
});

it('builds bandcamp cover urls and prefers the album title', function (): void {
    fakeTier3Http('bandcamp', 'bandcamp/search.json');

    $page = (new BandcampSource)->explore(1, new SourceQuery);

    expect($page->items)->toHaveCount(2)
        ->and($page->items[0]->sourceId)->toBe('791510690')
        ->and($page->items[0]->pageUrl)->toBe('https://embercollective.bandcamp.com/album/ember-sessions')
        ->and($page->items[0]->imageUrl)->toBe('https://f4.bcbits.com/img/a3809045440_10.jpg')
        ->and($page->items[0]->title)->toBe('Ember Sessions')
        ->and($page->items[0]->author)->toBe('Ember Collective')
        ->and($page->items[1]->title)->toBe('Ash Tracks Deluxe')
        ->and($page->items[1]->imageUrl)->toBe('https://f4.bcbits.com/img/a9812736450_10.jpg');
});

/*
|--------------------------------------------------------------------------
| Tier 3 embedded-JSON scrapers (Pinterest, Cara) + Mobbin (account-gated)
|--------------------------------------------------------------------------
*/

it('maps the embedded feed into a non-empty page for :key', function (string $key, string $class): void {
    fakeEmbeddedHttp($key, $key.'/feed.html');

    $page = (new $class)->search('portrait', 1, new SourceQuery);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->items)->not->toBeEmpty()
        ->and($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();

    foreach ($page->items as $item) {
        expect($item->source)->toBe($key)
            ->and($item->sourceId)->toBeString()->not->toBe('')
            ->and($item->pageUrl)->not->toBe('')
            ->and($item->imageUrl)->not->toBe('');
    }
})->with('embeddedJsonScrapeSources');

it('explores the :key embedded feed and requests the curated url', function (string $key, string $class): void {
    fakeEmbeddedHttp($key, $key.'/feed.html');

    $page = (new $class)->explore(1, new SourceQuery);

    $expected = match ($key) {
        'pinterest' => 'https://www.pinterest.com/search/pins/?q=design',
        'cara' => 'https://cara.app/explore',
    };

    expect($page->items)->not->toBeEmpty()
        ->and($page->hasMore)->toBeFalse()
        ->and($page->nextPage)->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->url() === $expected);
})->with('embeddedJsonScrapeSources');

it('delegates the embedded :key search to explore and logs a warning', function (string $key, string $class): void {
    Log::spy();
    fakeEmbeddedHttp($key, $key.'/feed.html');

    $page = (new $class)->search('portrait', 1, new SourceQuery);

    expect($page->items)->not->toBeEmpty();

    Log::shouldHaveReceived('warning')->once();
})->with('embeddedJsonScrapeSources');

it('raises a source exception when the :key shell carries no embedded json', function (string $key, string $class): void {
    fakeEmbeddedHttp($key, $key.'/broken.html');

    (new $class)->explore(1, new SourceQuery);
})->with('embeddedJsonScrapeSources')->throws(SourceException::class);

it('raises a source exception from the delegated :key search', function (string $key, string $class): void {
    fakeEmbeddedHttp($key, $key.'/broken.html');

    (new $class)->search('portrait', 1, new SourceQuery);
})->with('embeddedJsonScrapeSources')->throws(SourceException::class);

it('raises a source exception when the :key payload shape is unusable', function (string $key, string $class): void {
    // Valid JSON, but no pin/post list: a site change must degrade, not blank.
    fakeEmbeddedBody($key, '<html><body><script type="application/json">{"unexpected":[]}</script></body></html>');

    (new $class)->explore(1, new SourceQuery);
})->with('embeddedJsonScrapeSources')->throws(SourceException::class);

it('returns an empty page without a request for page two or a blank query for :key', function (string $key, string $class): void {
    Http::fake();

    $pageTwo = (new $class)->search('portrait', 2, new SourceQuery);
    $blank = (new $class)->search('   ', 1, new SourceQuery);

    expect($pageTwo->items)->toBe([])
        ->and($pageTwo->hasMore)->toBeFalse()
        ->and($pageTwo->nextPage)->toBeNull()
        ->and($blank->items)->toBe([]);

    Http::assertNothingSent();
})->with('embeddedJsonScrapeSources');

it('declares the expected capabilities for embedded :key', function (string $key, string $class): void {
    $capabilities = (new $class)->capabilities();

    expect($capabilities->supportsSearch)->toBeFalse()
        ->and($capabilities->supportsExplore)->toBeTrue()
        ->and($capabilities->needsKey)->toBeFalse()
        ->and($capabilities->hasMaturityLevels)->toBeFalse()
        ->and($capabilities->maxPageSize)->toBe(24)
        ->and($capabilities->ratePerMinute)->toBe(2)
        ->and((new $class)->isConfigured())->toBeTrue();
})->with('embeddedJsonScrapeSources');

it('reports connectivity through test() for embedded :key', function (string $key, string $class): void {
    fakeEmbeddedHttp($key, $key.'/feed.html');

    expect((new $class)->test())->toBeTrue();
})->with('embeddedJsonScrapeSources');

it('reports a failed connectivity test when the :key feed is blocked', function (string $key, string $class): void {
    fakeEmbeddedHttp($key, $key.'/feed.html', 500);

    expect((new $class)->test())->toBeFalse();
})->with('embeddedJsonScrapeSources');

it('maps pinterest pins to canonical pin urls and images', function (): void {
    fakeEmbeddedHttp('pinterest', 'pinterest/feed.html');

    $page = (new PinterestSource)->explore(1, new SourceQuery);

    expect($page->items)->toHaveCount(2);

    $first = $page->items[0];
    $second = $page->items[1];

    expect($first->sourceId)->toBe('111111111111111111')
        ->and($first->pageUrl)->toBe('https://www.pinterest.com/pin/111111111111111111/')
        ->and($first->imageUrl)->toBe('https://i.pinimg.com/originals/aa/bb/cc/aa11.jpg')
        ->and($first->thumbnailUrl)->toBe('https://i.pinimg.com/236x/aa/bb/cc/aa11.jpg')
        ->and($first->title)->toBe('Ash gradient study')
        ->and($first->width)->toBe(1200)
        ->and($first->height)->toBe(1600)
        ->and($second->sourceId)->toBe('222222222222222222')
        ->and($second->pageUrl)->toBe('https://www.pinterest.com/pin/222222222222222222/')
        // An image variant served as a bare string must be tolerated.
        ->and($second->imageUrl)->toBe('https://i.pinimg.com/originals/dd/ee/ff/dd22.jpg')
        ->and($second->title)->toBe('Ember type poster')
        ->and($second->width)->toBeNull();
});

it('maps cara posts to canonical post urls and skips videos', function (): void {
    fakeEmbeddedHttp('cara', 'cara/feed.html');

    $page = (new CaraSource)->explore(1, new SourceQuery);

    expect($page->items)->toHaveCount(2);

    $first = $page->items[0];

    expect($first->sourceId)->toBe('cara-post-1')
        ->and($first->pageUrl)->toBe('https://cara.app/post/cara-post-1')
        ->and($first->imageUrl)->toBe('https://cdn.cara.app/media/ember-1.jpg')
        ->and($first->title)->toBe('Ember poster study')
        ->and($first->author)->toBe('emberartist')
        ->and($first->authorUrl)->toBe('https://cara.app/profile/emberartist')
        ->and($first->width)->toBe(1200)
        ->and($page->items[1]->sourceId)->toBe('cara-post-2');
});

it('keeps mobbin unconfigured and never touches the network', function (): void {
    Http::fake();

    $source = new MobbinSource;
    $capabilities = $source->capabilities();

    expect($source->isConfigured())->toBeFalse()
        ->and($capabilities->supportsSearch)->toBeFalse()
        ->and($capabilities->supportsExplore)->toBeTrue()
        ->and($capabilities->needsKey)->toBeFalse()
        ->and($capabilities->ratePerMinute)->toBe(2)
        ->and($source->explore(1, new SourceQuery)->items)->toBe([])
        ->and($source->search('portrait', 1, new SourceQuery)->items)->toBe([])
        ->and($source->test())->toBeFalse();

    Http::assertNothingSent();
});

it('registers the tier 3 best-effort adapters in the source manager', function (): void {
    $keys = app(SourceManager::class)->all()->keys()->all();

    expect($keys)->toContain('pinterest', 'cara', 'mobbin');
});
