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
use Illuminate\Support\Facades\Cache;
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

    expect($manager->all()->keys()->all())->toContain('fake-ok')
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
    $manager = app(SourceManager::class);

    $result = $manager->search($user, 'fake-down', 'portrait', 1);
    $statuses = $manager->statuses($user);

    expect($result['from_cache'])->toBeTrue()
        ->and($result['age_minutes'])->toBe(180)
        ->and($result['items'])->toBe([['source' => 'fake-down', 'sourceId' => 'stale']])
        ->and($statuses['fake-down']['down'])->toBeFalse()
        ->and($statuses['fake-down']['cache_age_minutes'])->toBe(180)
        ->and($statuses['fake-down']['error_at'])->not->toBeNull();
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

it('reports a source down from a failure persisted in a previous request', function () {
    $key = 'fake-persisted-'.uniqid();

    registerInspirationFakeSources([new FakeInspirationSource($key)]);
    $user = inspirationUserWithSettings([$key]);

    Cache::put(SourceManager::ERROR_CACHE_PREFIX.$key, now()->subMinutes(5), SourceManager::ERROR_CACHE_TTL);

    app()->forgetInstance(SourceManager::class);

    $statuses = app(SourceManager::class)->statuses($user);

    expect($statuses[$key]['down'])->toBeTrue()
        ->and($statuses[$key]['cache_age_minutes'])->toBeNull()
        ->and($statuses[$key]['error_at'])->not->toBeNull()
        ->and(abs((int) $statuses[$key]['error_at']->diffInMinutes(now())))->toBe(5);
});

it('clears a persisted failure once the source responds again', function () {
    $key = 'fake-recovered';

    registerInspirationFakeSources([new FakeInspirationSource($key)]);
    $user = inspirationUserWithSettings([$key]);

    Cache::put(SourceManager::ERROR_CACHE_PREFIX.$key, now(), SourceManager::ERROR_CACHE_TTL);

    $manager = app(SourceManager::class);
    $manager->search($user, $key, 'portrait', 1);

    $statuses = $manager->statuses($user);

    expect($statuses[$key]['down'])->toBeFalse()
        ->and($statuses[$key]['error_at'])->toBeNull();
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
        ->and(InspirationCacheEntry::count())->toBe(2)
        ->and(InspirationCacheEntry::query()
            ->where('source', 'fake-maturity')
            ->where('query_hash', InspirationCache::queryHash('portrait', new SourceQuery('allowed', ['page' => 1])))
            ->exists())->toBeTrue();
});

it('filters activeConfigured by enabled sources that are configured', function () {
    registerInspirationFakeSources([
        new FakeInspirationSource('fake-enabled'),
        new FakeInspirationSource('fake-disabled'),
        new FakeInspirationSource('fake-keyed', needsKey: true),
    ]);

    $user = inspirationUserWithSettings(['fake-enabled', 'fake-keyed']);

    $manager = app(SourceManager::class);

    $active = $manager->activeConfigured($user);

    expect($active->keys()->all())->toContain('fake-enabled')
        ->and($active->keys()->all())->not->toContain('fake-disabled')
        ->and($active->keys()->all())->not->toContain('fake-keyed');

    app(InspirationSettings::class)->update($user, [
        'enabled_sources' => ['fake-enabled', 'fake-keyed'],
        'keys' => ['fake-keyed' => 'secret'],
    ]);

    $active = $manager->activeConfigured($user);

    expect($active->keys()->all())->toContain('fake-enabled')
        ->and($active->keys()->all())->toContain('fake-keyed');
});

it('gates a tier 3 source out of activeConfigured until the user acknowledges it', function () {
    config(['inspiration.tier3' => ['fake-tier3']]);

    registerInspirationFakeSources([
        new FakeInspirationSource('fake-tier3'),
        new FakeInspirationSource('fake-open'),
    ]);

    $user = inspirationUserWithSettings(['fake-tier3', 'fake-open']);

    $manager = app(SourceManager::class);
    $active = $manager->activeConfigured($user);

    // Enabled and configured, but still behind the Tier 3 acknowledgement.
    expect($active->keys()->all())->toContain('fake-open')
        ->and($active->keys()->all())->not->toContain('fake-tier3');

    // statuses() still lists it so the settings UI can render the notice.
    expect($manager->statuses($user))->toHaveKey('fake-tier3');

    app(InspirationSettings::class)->update($user, [
        'enabled_sources' => ['fake-tier3', 'fake-open'],
        'acknowledged_tier3' => ['fake-tier3'],
    ]);

    $active = app(SourceManager::class)->activeConfigured($user);

    expect($active->keys()->all())->toContain('fake-tier3')
        ->and($active->keys()->all())->toContain('fake-open');
});

it('rate limits a source according to its capabilities', function () {
    $key = 'fake-rate-'.uniqid();

    registerInspirationFakeSources([new FakeInspirationSource($key, ratePerMinute: 1)]);
    $user = inspirationUserWithSettings([$key]);

    $manager = app(SourceManager::class);

    expect($manager->rateLimit($user, $key))->toBeTrue()
        ->and($manager->rateLimit($user, $key))->toBeFalse();
});

it('allows exactly two calls per minute when the source declares rate two', function () {
    $key = 'fake-rate-two-'.uniqid();

    registerInspirationFakeSources([new FakeInspirationSource($key, ratePerMinute: 2)]);
    $user = inspirationUserWithSettings([$key]);

    $manager = app(SourceManager::class);

    expect($manager->rateLimit($user, $key))->toBeTrue()
        ->and($manager->rateLimit($user, $key))->toBeTrue()
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

it('hydrates per-source credentials plus the zerochan user agent', function () {
    $source = new FakeInspirationSource('fake-cred');
    registerInspirationFakeSources([$source]);

    $user = User::factory()->create();

    app(InspirationSettings::class)->update($user, [
        'enabled_sources' => ['fake-cred'],
        'keys' => ['fake-cred' => ['refresh_token' => 'rt']],
        'zerochan_ua' => 'Megalomaniac/2.0',
    ]);

    $manager = app(SourceManager::class);

    $manager->activeConfigured($user);

    expect($source->receivedCredentials())->toBe([
        'refresh_token' => 'rt',
        'user_agent' => 'Megalomaniac/2.0',
    ]);

    $manager->search($user, 'fake-cred', 'portrait');

    expect($source->receivedCredentials())->toBe([
        'refresh_token' => 'rt',
        'user_agent' => 'Megalomaniac/2.0',
    ]);
});

it('drops the user agent from hydration when the user has not set one', function () {
    $source = new FakeInspirationSource('fake-no-ua');
    registerInspirationFakeSources([$source]);

    $user = User::factory()->create();

    app(InspirationSettings::class)->update($user, [
        'enabled_sources' => ['fake-no-ua'],
        'keys' => ['fake-no-ua' => ['refresh_token' => 'rt']],
    ]);

    app(SourceManager::class)->search($user, 'fake-no-ua', 'portrait');

    expect($source->receivedCredentials())->toBe(['refresh_token' => 'rt']);
});
