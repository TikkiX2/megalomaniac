<?php

use App\Inspiration\Dtos\SettingsBag;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\InspirationCache;
use App\Inspiration\InspirationSettings;
use App\Models\InspirationCacheEntry;
use App\Models\InspirationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('writes through the cache and returns the freshly fetched payload', function () {
    $result = InspirationCache::remember(
        'deviantart',
        'search',
        InspirationCache::queryHash('portrait', new SourceQuery),
        1800,
        fn (): array => ['items' => [['id' => 'a'], ['id' => 'b']]],
    );

    expect($result['from_cache'])->toBeFalse()
        ->and($result['age_minutes'])->toBeNull()
        ->and($result['payload'])->toBe(['items' => [['id' => 'a'], ['id' => 'b']]])
        ->and(InspirationCacheEntry::count())->toBe(1);
});

it('serves the cached payload without executing the closure while fresh', function () {
    $hash = InspirationCache::queryHash('portrait', new SourceQuery);

    InspirationCache::remember('deviantart', 'search', $hash, 1800, fn (): array => ['items' => [['id' => 'a']]]);

    Carbon::setTestNow(now()->addMinutes(5));

    $result = InspirationCache::remember('deviantart', 'search', $hash, 1800, function (): array {
        throw new RuntimeException('Closure must not run for a fresh cache hit.');
    });

    expect($result['from_cache'])->toBeTrue()
        ->and($result['age_minutes'])->toBe(5)
        ->and($result['payload'])->toBe(['items' => [['id' => 'a']]])
        ->and(InspirationCacheEntry::count())->toBe(1);

    Carbon::setTestNow();
});

it('re-executes the closure once the ttl has expired', function () {
    $hash = InspirationCache::queryHash('portrait', new SourceQuery);

    InspirationCache::remember('deviantart', 'search', $hash, 60, fn (): array => ['items' => [['id' => 'old']]]);

    Carbon::setTestNow(now()->addMinutes(61));

    $result = InspirationCache::remember('deviantart', 'search', $hash, 60, fn (): array => ['items' => [['id' => 'new']]]);

    expect($result['from_cache'])->toBeFalse()
        ->and($result['age_minutes'])->toBeNull()
        ->and($result['payload'])->toBe(['items' => [['id' => 'new']]])
        ->and(InspirationCacheEntry::count())->toBe(1);

    Carbon::setTestNow();
});

it('overwrites the single cache row for the same source kind and query hash', function () {
    $hash = InspirationCache::queryHash('portrait', new SourceQuery);

    InspirationCache::remember('deviantart', 'search', $hash, 1800, fn (): array => ['items' => [['id' => 'a']]]);
    InspirationCache::remember('deviantart', 'search', $hash, 1800, fn (): array => ['items' => [['id' => 'b']]]);

    expect(InspirationCacheEntry::where('source', 'deviantart')->where('kind', 'search')->where('query_hash', $hash)->count())->toBe(1);
});

it('reports the age in minutes of a stored entry', function () {
    $hash = InspirationCache::queryHash('portrait', new SourceQuery);

    InspirationCache::remember('deviantart', 'search', $hash, 1800, fn (): array => ['items' => []]);

    expect(InspirationCache::ageMinutes('deviantart', 'search', $hash))->toBe(0)
        ->and(InspirationCache::ageMinutes('deviantart', 'search', 'missing'))->toBeNull();

    Carbon::setTestNow(now()->addMinutes(12));

    expect(InspirationCache::ageMinutes('deviantart', 'search', $hash))->toBe(12);

    Carbon::setTestNow();
});

it('builds a different query hash for a different maturity', function () {
    expect(InspirationCache::queryHash('portrait', new SourceQuery('safe')))
        ->not->toBe(InspirationCache::queryHash('portrait', new SourceQuery('allowed')));
});

it('takes extra query options into account for the hash', function () {
    expect(InspirationCache::queryHash('portrait', new SourceQuery('safe', ['page' => 1])))
        ->not->toBe(InspirationCache::queryHash('portrait', new SourceQuery('safe', ['page' => 2])));
});

it('returns well-formed settings bag defaults for a user without settings', function () {
    $user = User::factory()->create();

    $bag = app(InspirationSettings::class)->for($user);

    expect($bag)->toBeInstanceOf(SettingsBag::class)
        ->and($bag->enabledSources)->toBe([])
        ->and($bag->keys)->toBe([])
        ->and($bag->maturity)->toBeFalse()
        ->and($bag->zerochanUa)->toBeNull()
        ->and($bag->acknowledgedTier3)->toBe([])
        ->and($bag->hasKey('deviantart'))->toBeFalse()
        ->and($bag->isEnabled('deviantart'))->toBeFalse();
});

it('persists the complete settings shape and merges defaults for missing keys', function () {
    $user = User::factory()->create();
    $settings = app(InspirationSettings::class);

    $settings->update($user, [
        'enabled_sources' => ['deviantart', 'artstation'],
        'keys' => ['flickr' => 'secret'],
        'maturity' => true,
        'zerochan_ua' => 'Megalomaniac-ricky',
        'acknowledged_tier3' => ['pixiv'],
    ]);

    $bag = $settings->for($user);

    expect($bag->enabledSources)->toBe(['deviantart', 'artstation'])
        ->and($bag->keys)->toBe(['flickr' => 'secret'])
        ->and($bag->maturity)->toBeTrue()
        ->and($bag->zerochanUa)->toBe('Megalomaniac-ricky')
        ->and($bag->acknowledgedTier3)->toBe(['pixiv'])
        ->and($bag->hasKey('flickr'))->toBeTrue()
        ->and($bag->isEnabled('deviantart'))->toBeTrue();

    $settings->update($user, ['enabled_sources' => ['unsplash']]);

    $updated = $settings->for($user);

    expect($updated->enabledSources)->toBe(['unsplash'])
        ->and($updated->maturity)->toBeFalse()
        ->and($updated->keys)->toBe([])
        ->and($updated->acknowledgedTier3)->toBe([])
        ->and(InspirationSetting::count())->toBe(1);
});
