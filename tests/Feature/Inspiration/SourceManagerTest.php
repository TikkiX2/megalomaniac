<?php

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\Exceptions\SourceException;
use App\Inspiration\InspirationCache;
use App\Inspiration\InspirationSettings;
use App\Inspiration\SourceManager;
use App\Models\InspirationCacheEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeInspirationSource;

uses(RefreshDatabase::class);

/**
 * Register the given fake sources under the tagged container binding used by
 * SourceManager, naming each one after its source key.
 *
 * @param  array<int, FakeInspirationSource>  $sources
 */
function registerInspirationFakeSources(array $sources): void
{
    foreach ($sources as $source) {
        app()->instance('inspiration.fake.'.$source->key(), $source);
    }

    app()->tag(
        array_map(fn (FakeInspirationSource $source): string => 'inspiration.fake.'.$source->key(), $sources),
        'inspiration.sources',
    );
}

function inspirationUserWithSettings(array $enabledSources, array $keys = []): User
{
    $user = User::factory()->create();

    app(InspirationSettings::class)->update($user, [
        'enabled_sources' => $enabledSources,
        'keys' => $keys,
    ]);

    return $user;
}

it('resolves the tagged source registry keyed by source key', function () {
    $ok = new FakeInspirationSource('fake-ok');
    registerInspirationFakeSources([$ok]);

    $manager = app(SourceManager::class);

    expect($manager->all())->toHaveCount(1)
        ->and($manager->get('fake-ok'))->toBe($ok)
        ->and($manager->get('missing'))->toBeNull();
});

it('throws a source exception when a down source has no cached payload', function () {
    registerInspirationFakeSources([new FakeInspirationSource('fake-down', fails: true)]);
    $user = inspirationUserWithSettings(['fake-down']);

    $manager = app(SourceManager::class);

    try {
        $manager->search($user, 'fake-down', 'portrait', 1);
        $this->fail('Expected a SourceException.');
    } catch (SourceException $exception) {
        expect($exception->previousCacheAge)->toBeNull();
    }
});

it('serves the stale cached payload instead of throwing when the source is down', function () {
    $options = new SourceQuery('safe', ['page' => 1]);
    $hash = InspirationCache::queryHash('portrait', $options);

    InspirationCacheEntry::create([
        'source' => 'fake-down',
        'kind' => 'search',
        'query_hash' => $hash,
        'payload' => [
            'items' => [['source' => 'fake-down', 'sourceId' => 'stale']],
            'has_more' => false,
            'next_page' => null,
        ],
        'fetched_at' => now()->subHours(3),
        'expires_at' => now()->subHour(),
    ]);

    registerInspirationFakeSources([new FakeInspirationSource('fake-down', fails: true)]);
    $user = inspirationUserWithSettings(['fake-down']);

    $result = app(SourceManager::class)->search($user, 'fake-down', 'portrait', 1);

    expect($result['from_cache'])->toBeTrue()
        ->and($result['age_minutes'])->toBe(180)
        ->and($result['items'])->toBe([['source' => 'fake-down', 'sourceId' => 'stale']]);
});

it('isolates a failing source in searchAll while the healthy sources respond', function () {
    registerInspirationFakeSources([
        new FakeInspirationSource('fake-ok'),
        new FakeInspirationSource('fake-down', fails: true),
    ]);

    $user = inspirationUserWithSettings(['fake-ok', 'fake-down']);

    $results = app(SourceManager::class)->searchAll($user, 'portrait');

    expect($results)->toHaveKeys(['fake-ok', 'fake-down'])
        ->and($results['fake-down']['items'])->toBe([])
        ->and($results['fake-ok']['items'])->toHaveCount(1)
        ->and($results['fake-ok']['items'][0]['sourceId'])->toBe('fake-ok-1');
});

it('marks a source down only when it failed and no cache row exists at all', function () {
    registerInspirationFakeSources([
        new FakeInspirationSource('fake-ok'),
        new FakeInspirationSource('fake-down', fails: true),
    ]);

    $user = inspirationUserWithSettings(['fake-ok', 'fake-down']);
    $manager = app(SourceManager::class);

    $manager->searchAll($user, 'portrait');

    $statuses = $manager->statuses($user);

    expect($statuses['fake-down']['down'])->toBeTrue()
        ->and($statuses['fake-down']['cache_age_minutes'])->toBeNull()
        ->and($statuses['fake-ok']['down'])->toBeFalse()
        ->and($statuses['fake-ok']['cache_age_minutes'])->toBe(0)
        ->and($statuses['fake-ok']['enabled'])->toBeTrue()
        ->and($statuses['fake-ok']['configured'])->toBeTrue();
});

it('writes through the cache and serves the first payload on the second search', function () {
    $calls = 0;

    $source = new FakeInspirationSource('fake-cache', searchCallback: function () use (&$calls): Page {
        $calls++;

        return Page::fromItems([
            new InspirationItem(
                source: 'fake-cache',
                sourceId: 'call-'.$calls,
                pageUrl: 'https://example.com/'.$calls,
                imageUrl: 'https://cdn.example.com/'.$calls.'.jpg',
            ),
        ], false, null);
    });

    registerInspirationFakeSources([$source]);
    $user = inspirationUserWithSettings(['fake-cache']);
    $manager = app(SourceManager::class);

    $first = $manager->search($user, 'fake-cache', 'portrait', 1);
    $second = $manager->search($user, 'fake-cache', 'portrait', 1);

    expect($first['from_cache'])->toBeFalse()
        ->and($first['age_minutes'])->toBeNull()
        ->and($first['items'][0]['sourceId'])->toBe('call-1')
        ->and($second['from_cache'])->toBeTrue()
        ->and($second['items'][0]['sourceId'])->toBe('call-1')
        ->and($source->searchCalls)->toBe(1)
        ->and(InspirationCacheEntry::count())->toBe(1);
});

it('keys the cache by maturity so safe and unrestricted searches stay separate', function () {
    registerInspirationFakeSources([new FakeInspirationSource('fake-maturity')]);

    $safeUser = inspirationUserWithSettings(['fake-maturity']);
    $matureUser = inspirationUserWithSettings(['fake-maturity']);
    app(InspirationSettings::class)->update($matureUser, ['maturity' => true]);

    $manager = app(SourceManager::class);

    $safe = $manager->search($safeUser, 'fake-maturity', 'portrait', 1);
    $mature = $manager->search($matureUser, 'fake-maturity', 'portrait', 1);

    expect($safe['from_cache'])->toBeFalse()
        ->and($mature['from_cache'])->toBeFalse()
        ->and(InspirationCacheEntry::count())->toBe(2);
});

it('filters activeConfigured by enabled sources that are configured', function () {
    registerInspirationFakeSources([
        new FakeInspirationSource('fake-enabled'),
        new FakeInspirationSource('fake-disabled'),
        new FakeInspirationSource('fake-keyed', needsKey: true),
    ]);

    $user = inspirationUserWithSettings(['fake-enabled', 'fake-keyed']);

    $manager = app(SourceManager::class);

    expect($manager->activeConfigured($user)->keys()->all())->toBe(['fake-enabled']);

    app(InspirationSettings::class)->update($user, [
        'enabled_sources' => ['fake-enabled', 'fake-keyed'],
        'keys' => ['fake-keyed' => 'secret'],
    ]);

    expect($manager->activeConfigured($user)->keys()->all())->toBe(['fake-enabled', 'fake-keyed']);
});

it('rate limits a source according to its capabilities', function () {
    $key = 'fake-rate-'.uniqid();

    registerInspirationFakeSources([new FakeInspirationSource($key, ratePerMinute: 1)]);
    $user = inspirationUserWithSettings([$key]);

    $manager = app(SourceManager::class);

    expect($manager->rateLimit($user, $key))->toBeTrue()
        ->and($manager->rateLimit($user, $key))->toBeFalse();
});

it('discards items missing a page url or an image url', function () {
    expect(InspirationItem::fromSource('fake', ['pageUrl' => 'https://p']))->toBeNull()
        ->and(InspirationItem::fromSource('fake', ['imageUrl' => 'https://i']))->toBeNull();

    $item = InspirationItem::fromSource('fake', [
        'sourceId' => 'abc',
        'pageUrl' => 'https://p',
        'imageUrl' => 'https://i',
        'tags' => ['one', 'two'],
    ]);

    expect($item)->not->toBeNull()
        ->and($item->source)->toBe('fake')
        ->and($item->sourceId)->toBe('abc')
        ->and($item->tags)->toBe(['one', 'two']);
});

it('builds a page from items carrying pagination flags', function () {
    $item = new InspirationItem(
        source: 'fake',
        sourceId: 'abc',
        pageUrl: 'https://p',
        imageUrl: 'https://i',
    );

    $page = Page::fromItems([$item], true, 2);

    expect($page->items)->toHaveCount(1)
        ->and($page->hasMore)->toBeTrue()
        ->and($page->nextPage)->toBe(2);
});
