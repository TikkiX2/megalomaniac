<?php

declare(strict_types=1);

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\InspirationSettings;
use App\Jobs\Inspiration\RefreshSourceJob;
use App\Models\InspirationCacheEntry;
use App\Models\Moodboard;
use App\Models\Project;
use App\Models\SavedImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\FakeInspirationSource;

uses(RefreshDatabase::class);

/**
 * Read a raw fixture body for HTTP-backed cases.
 */
function exploreFixture(string $path): string
{
    $contents = file_get_contents(base_path('tests/Fixtures/Inspiration/'.$path));

    if ($contents === false) {
        throw new RuntimeException("Missing inspiration fixture [{$path}].");
    }

    return $contents;
}

/**
 * Create a user whose inspiration settings enable the given sources.
 *
 * @param  array<int, string>  $enabledSources
 */
function exploreUser(array $enabledSources): User
{
    $user = User::factory()->create();

    app(InspirationSettings::class)->update($user, [
        'enabled_sources' => $enabledSources,
    ]);

    return $user;
}

beforeEach(function (): void {
    $this->withoutVite();

    // The first render only fans out over `home_sources`; keep the fakes in
    // that subset so the mashup tests exercise the full flow.
    config()->set('inspiration.home_sources', array_merge(
        (array) config('inspiration.home_sources', []),
        ['fake-ok', 'fake-two', 'fake-down', 'fake-boom', 'fake-capped', 'fake-throttle'],
    ));
});

it('renders the explore page as a mashup of the active sources', function () {
    FakeInspirationSource::register([
        new FakeInspirationSource('fake-ok'),
        new FakeInspirationSource('fake-two'),
    ]);

    $user = exploreUser(['fake-ok', 'fake-two']);

    $this->actingAs($user)
        ->get('/inspiration')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inspiration/explore', false)
            ->where('search', '')
            ->where('source', 'all')
            ->has('sources')
            ->has('results', 2)
            ->where('results.0.source', 'fake-ok')
            ->where('results.0.items.0.source_id', 'fake-ok-explore-1')
            ->where('results.0.items.0.page_url', 'https://example.com/fake-ok-explore-1')
            ->where('results.0.items.0.image_url', 'https://cdn.example.com/fake-ok-explore-1.jpg')
            ->where('results.0.items.0.title', 'fake-ok-explore-1')
            ->where('results.0.items.0.thumbnail_url', null)
            ->where('results.0.items.0.dominant_color', null)
            ->where('results.0.items.0.author_url', null)
            ->missing('results.0.items.0.sourceId')
            ->missing('results.0.items.0.imageUrl')
            ->where('results.0.from_cache', false)
            ->where('results.1.source', 'fake-two')
            ->where('results.1.items.0.source_id', 'fake-two-explore-1'));
});

it('exposes personal projects, boards and the saved index for the user', function () {
    FakeInspirationSource::register([new FakeInspirationSource('fake-ok')]);

    $user = exploreUser(['fake-ok']);
    $other = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'type' => 'personal',
        'name' => 'Board Project',
    ]);

    Project::factory()->create([
        'user_id' => $other->id,
        'type' => 'personal',
        'name' => 'Foreign Project',
    ]);

    $board = Moodboard::create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'name' => 'Mood',
    ]);

    Moodboard::create([
        'user_id' => $user->id,
        'name' => 'Inbox',
    ]);

    SavedImage::create([
        'user_id' => $user->id,
        'moodboard_id' => $board->id,
        'source' => 'fake-ok',
        'source_id' => 'abc',
        'page_url' => 'https://example.com/page',
        'image_url' => 'https://example.com/image.jpg',
        'thumb_path' => 'inspiration/1/fake-ok/abc.jpg',
    ]);

    $this->actingAs($user)
        ->get('/inspiration')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.name', 'Board Project')
            ->has('boards', 2)
            ->where('boards.0.name', 'Mood')
            ->where('boards.0.project_id', $project->id)
            ->where('boards.0.project_name', 'Board Project')
            ->where('boards.0.count', 1)
            ->where('boards.1.name', 'Inbox')
            ->where('boards.1.project_id', null)
            ->where('boards.1.project_name', null)
            ->where('boards.1.count', 0)
            ->where('saved', ['fake-ok:abc' => $board->id]));
});

it('serves saved and boards on a partial reload of the search URL', function () {
    FakeInspirationSource::register([new FakeInspirationSource('fake-ok')]);

    $user = exploreUser(['fake-ok']);
    $other = User::factory()->create();

    $board = Moodboard::create([
        'user_id' => $user->id,
        'name' => 'Mood',
    ]);

    Moodboard::create([
        'user_id' => $other->id,
        'name' => 'Foreign',
    ]);

    SavedImage::create([
        'user_id' => $user->id,
        'moodboard_id' => $board->id,
        'source' => 'fake-ok',
        'source_id' => 'abc',
        'page_url' => 'https://example.com/page',
        'image_url' => 'https://example.com/image.jpg',
        'thumb_path' => 'inspiration/1/fake-ok/abc.jpg',
    ]);

    $this->actingAs($user)
        ->get('/inspiration/search?q=portrait&source=all', [
            'X-Inertia-Partial-Component' => 'inspiration/explore',
            'X-Inertia-Partial-Data' => 'saved,boards,projects',
        ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inspiration/explore', false)
            ->where('saved', ['fake-ok:abc' => $board->id])
            ->has('boards', 1)
            ->where('boards.0.name', 'Mood')
            ->where('boards.0.project_id', null)
            ->where('boards.0.project_name', null)
            ->where('boards.0.count', 1)
            ->has('projects', 0)
            ->missing('results')
            ->missing('sources'));
});

it('keeps source statuses available on a partial search reload', function () {
    FakeInspirationSource::register([new FakeInspirationSource('fake-ok')]);

    $user = exploreUser(['fake-ok']);

    $this->actingAs($user)
        ->get('/inspiration/search?q=portrait&source=all', [
            'X-Inertia-Partial-Component' => 'inspiration/explore',
            'X-Inertia-Partial-Data' => 'sources',
        ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sources')
            ->where('sources.fake-ok.enabled', true)
            ->where('sources.fake-ok.down', false)
            ->missing('results'));
});

it('degrades a failing explore source without failing the page', function () {
    FakeInspirationSource::register([
        new FakeInspirationSource('fake-ok'),
        new FakeInspirationSource('fake-down', fails: true),
    ]);

    $user = exploreUser(['fake-ok', 'fake-down']);

    $this->actingAs($user)
        ->get('/inspiration')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('results', 2)
            ->where('sources.fake-ok.down', false)
            ->where('sources.fake-down.down', true)
            ->where('results.0.source', 'fake-ok')
            ->where('results.0.items.0.source_id', 'fake-ok-explore-1')
            ->where('results.1.source', 'fake-down')
            ->where('results.1.items', [])
            ->where('results.1.has_more', false)
            ->where('results.1.from_cache', false)
            ->where('results.1.age_minutes', null));
});

it('degrades a source that throws a non-source error without failing the page', function () {
    FakeInspirationSource::register([
        new FakeInspirationSource('fake-ok'),
        new FakeInspirationSource(
            'fake-boom',
            exploreCallback: fn (int $page, SourceQuery $options): Page => throw new TypeError('adapter bug'),
        ),
    ]);

    $user = exploreUser(['fake-ok', 'fake-boom']);

    $this->actingAs($user)
        ->get('/inspiration')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('results', 2)
            ->where('results.0.source', 'fake-ok')
            ->where('results.0.items.0.source_id', 'fake-ok-explore-1')
            ->where('results.1.source', 'fake-boom')
            ->where('results.1.items', [])
            ->where('results.1.has_more', false));
});

it('degrades a single-source search that throws a non-source error', function () {
    FakeInspirationSource::register([
        new FakeInspirationSource(
            'fake-boom',
            searchCallback: fn (int $page, string $query, SourceQuery $options): Page => throw new TypeError('adapter bug'),
        ),
    ]);

    $user = exploreUser(['fake-boom']);

    $this->actingAs($user)
        ->get('/inspiration/search?q=portrait&source=fake-boom')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('results', 1)
            ->where('results.0.source', 'fake-boom')
            ->where('results.0.items', []));
});

it('caps the explore mashup at twelve items per source', function () {
    $items = [];

    for ($i = 1; $i <= 20; $i++) {
        $items[] = new InspirationItem(
            source: 'fake-capped',
            sourceId: 'capped-'.$i,
            pageUrl: 'https://example.com/capped-'.$i,
            imageUrl: 'https://cdn.example.com/capped-'.$i.'.jpg',
        );
    }

    $source = new FakeInspirationSource('fake-capped', exploreCallback: fn (int $page, SourceQuery $options): Page => Page::fromItems($items, true, 2));

    FakeInspirationSource::register([$source]);

    $user = exploreUser(['fake-capped']);

    $this->actingAs($user)
        ->get('/inspiration')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('results.0.items', 12)
            ->where('results.0.items.0.source_id', 'capped-1')
            ->where('results.0.items.11.source_id', 'capped-12')
            ->where('results.0.has_more', true));
});

it('fans out a search across every active source', function () {
    FakeInspirationSource::register([
        new FakeInspirationSource('fake-ok'),
        new FakeInspirationSource('fake-two'),
    ]);

    $user = exploreUser(['fake-ok', 'fake-two']);

    $this->actingAs($user)
        ->get('/inspiration/search?q=portrait&source=all')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inspiration/explore', false)
            ->where('search', 'portrait')
            ->where('source', 'all')
            ->has('results', 2)
            ->where('results.0.source', 'fake-ok')
            ->where('results.0.items.0.source_id', 'fake-ok-1')
            ->where('results.1.source', 'fake-two')
            ->where('results.1.items.0.source_id', 'fake-two-1'));
});

it('isolates a failing source in the search fan-out', function () {
    FakeInspirationSource::register([
        new FakeInspirationSource('fake-ok'),
        new FakeInspirationSource('fake-down', fails: true),
    ]);

    $user = exploreUser(['fake-ok', 'fake-down']);

    $this->actingAs($user)
        ->get('/inspiration/search?q=portrait')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('results', 2)
            ->where('results.0.source', 'fake-ok')
            ->where('results.0.items.0.source_id', 'fake-ok-1')
            ->where('results.1.source', 'fake-down')
            ->where('results.1.items', [])
            ->where('results.1.has_more', false)
            ->where('results.1.age_minutes', null));
});

it('searches a single source with the requested page', function () {
    $seenPage = null;

    $source = new FakeInspirationSource('fake-page', searchCallback: function (int $page) use (&$seenPage): Page {
        $seenPage = $page;

        return Page::fromItems([], false, null);
    });

    FakeInspirationSource::register([
        $source,
        new FakeInspirationSource('fake-other'),
    ]);

    $user = exploreUser(['fake-page', 'fake-other']);

    $this->actingAs($user)
        ->get('/inspiration/search?q=portrait&source=fake-page&page=3')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('results', 1)
            ->where('results.0.source', 'fake-page'));

    expect($seenPage)->toBe(3)
        ->and($source->searchCalls)->toBe(1);
});

it('serves the stale cache when a source fails mid-request', function () {
    Http::fake([
        '*wallhaven.cc/api/v1/search*' => Http::sequence()
            ->push(exploreFixture('wallhaven/search.json'), 200)
            ->push('', 500),
    ]);

    $user = exploreUser(['wallhaven']);

    $this->actingAs($user)
        ->get('/inspiration/search?q=portrait&source=wallhaven')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('results.0.source', 'wallhaven')
            ->where('results.0.from_cache', false)
            ->has('results.0.items'));

    // Force the second controller call to reach the adapter, which now fails.
    InspirationCacheEntry::query()->update([
        'expires_at' => now()->subMinute(),
        'fetched_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->get('/inspiration/search?q=portrait&source=wallhaven')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('results.0.source', 'wallhaven')
            ->where('results.0.from_cache', true)
            ->where('results.0.age_minutes', fn ($value) => is_int($value) && $value >= 0));
});

it('rejects a query longer than 200 characters', function () {
    FakeInspirationSource::register([new FakeInspirationSource('fake-ok')]);

    $user = exploreUser(['fake-ok']);

    $this->actingAs($user)
        ->getJson('/inspiration/search?q='.str_repeat('a', 201).'&source=all')
        ->assertStatus(422)
        ->assertJsonValidationErrors('q');
});

it('rejects an unknown source key', function () {
    FakeInspirationSource::register([new FakeInspirationSource('fake-ok')]);

    $user = exploreUser(['fake-ok']);

    $this->actingAs($user)
        ->getJson('/inspiration/search?q=portrait&source=bogus')
        ->assertStatus(422)
        ->assertJsonValidationErrors('source');
});

it('requires authentication to explore', function () {
    $this->get('/inspiration')->assertRedirect('/login');
});

it('requires authentication to search', function () {
    $this->get('/inspiration/search')->assertRedirect('/login');
});

it('enforces the per-source rate limit as a degraded entry', function () {
    $key = 'fake-throttle';

    RateLimiter::clear('inspiration:'.$key);

    FakeInspirationSource::register([
        new FakeInspirationSource($key, ratePerMinute: 1),
    ]);

    $user = exploreUser([$key]);

    // First hit consumes the single allowed slot and caches a payload.
    $this->actingAs($user)
        ->get('/inspiration/search?q=portrait&source='.$key)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('results.0.source', $key)
            ->where('results.0.from_cache', false)
            ->has('results.0.items'));

    // Drop the fallback so the throttled call has to degrade, not serve stale.
    InspirationCacheEntry::query()->delete();

    // Second hit is denied by the limiter and degrades like a dead source.
    $this->actingAs($user)
        ->get('/inspiration')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sources.'.$key.'.down', true)
            ->where('results.0.source', $key)
            ->where('results.0.items', [])
            ->where('results.0.has_more', false)
            ->where('results.0.from_cache', false)
            ->where('results.0.age_minutes', null));
});

it('only fans out over the home subset on the first render', function () {
    FakeInspirationSource::register([
        new FakeInspirationSource('fake-ok'),
        new FakeInspirationSource('fake-two'),
        new FakeInspirationSource('fake-lazy'),
    ]);

    config()->set('inspiration.home_sources', ['fake-ok']);

    $user = exploreUser(['fake-ok', 'fake-two', 'fake-lazy']);

    $this->actingAs($user)
        ->get('/inspiration')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('results', function ($results): bool {
            $sources = $results instanceof Collection
                ? $results->pluck('source')->all()
                : array_column((array) $results, 'source');

            return $sources === ['fake-ok'];
        }));
});

it('serves an expired cache entry instantly and dispatches one background refresh', function () {
    FakeInspirationSource::register([new FakeInspirationSource('fake-ok')]);

    config()->set('inspiration.home_sources', ['fake-ok']);

    $user = exploreUser(['fake-ok']);

    InspirationCacheEntry::create([
        'source' => 'fake-ok',
        'kind' => 'explore',
        'query_hash' => md5('|safe|{"page":1}'),
        'payload' => [
            'items' => [['source' => 'fake-ok', 'sourceId' => 'stale-1', 'pageUrl' => 'https://example.com/1', 'imageUrl' => 'https://cdn.example.com/1.jpg']],
            'has_more' => false,
        ],
        'fetched_at' => now()->subHours(2),
        'expires_at' => now()->subHour(),
    ]);

    Queue::fake();

    $this->actingAs($user)
        ->get('/inspiration')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('results', function ($results): bool {
            $group = $results[0] ?? null;

            return $group !== null
                && $group['source'] === 'fake-ok'
                && $group['from_cache'] === true
                && $group['stale'] === true
                && $group['items'][0]['source_id'] === 'stale-1';
        }));

    // The stale entry is served without touching the adapter again, and
    // exactly one background refresh is queued for the key.
    Http::assertNothingSent();
    Queue::assertPushed(
        RefreshSourceJob::class,
        fn (RefreshSourceJob $job): bool => $job->source === 'fake-ok' && $job->kind === 'explore',
    );
});
