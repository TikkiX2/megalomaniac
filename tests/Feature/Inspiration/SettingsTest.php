<?php

declare(strict_types=1);

use App\Inspiration\Contracts\Source;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\InspirationSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeInspirationSource;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * Persist a full bag for the user through the settings service.
 *
 * @param  array<string, mixed>  $body
 */
function settingsUserWith(array $body): User
{
    $user = User::factory()->create();

    app(InspirationSettings::class)->update($user, $body);

    return $user;
}

/**
 * Assert a JSON test response carries the `{ok:false, message}` contract.
 */
function assertFailedTestResponse($response): void
{
    $response->assertStatus(422)
        ->assertJson(['ok' => false])
        ->assertJsonStructure(['ok', 'message']);
}

it('exposes the credential_fields map in config', function () {
    $fields = config('inspiration.credential_fields');

    expect($fields)->toBeArray()
        ->and($fields['flickr'])->toBe(['key'])
        ->and($fields['discogs'])->toBe(['token'])
        ->and($fields['pixiv'])->toBe(['refresh_token'])
        ->and($fields['wallhaven'])->toBe(['key'])
        ->and($fields['gelbooru'])->toBe(['key'])
        ->and(array_keys($fields))->toEqualCanonicalizing([
            'flickr', 'tumblr', 'unsplash', 'pexels', 'pixabay', 'discogs',
            'giphy', 'europeana', 'rijksmuseum', 'wikiart', 'pixiv',
            'wallhaven', 'gelbooru',
        ]);
});

it('lists the keyless tier 1 and tier 2 sources as enabled by default', function () {
    $defaults = config('inspiration.default_enabled_sources');

    expect($defaults)->toBeArray()
        ->and($defaults)->toContain(
            'deviantart', 'wallhaven', 'openverse', 'zerochan',
            'gelbooru', 'arena', 'aic', '500px',
            'designspiration', 'trendlist', 'posterspy',
            'brutalist', 'archdaily', 'itsnicethat', 'godly', 'darkmode', 'dribbble',
            'awwwards', 'cosmos',
        )
        // Bot-walled, retired or account-gated sources stay out of the home
        // defaults until they get a working surface (or a session cookie).
        ->and(array_intersect($defaults, ['artstation', 'behance', 'met', 'bandcamp', 'lapaninja', 'newgrounds', 'savee']))->toBe([])
        ->and(array_intersect($defaults, config('inspiration.tier3')))->toBe([]);
});

it('shows the config defaults as enabled for a fresh account without settings', function () {
    config()->set('inspiration.default_enabled_sources', ['fake-default']);
    FakeInspirationSource::register([new FakeInspirationSource('fake-default')]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/inspiration/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('settings.enabled_sources', ['fake-default'])
            ->where('sources', function ($sources): bool {
                $row = collect($sources)->firstWhere('key', 'fake-default');

                return $row['enabled'] === true;
            }));
});

it('renders the settings page with source metadata and no plain keys', function () {
    FakeInspirationSource::register([
        new FakeInspirationSource('flickr', needsKey: true),
        new FakeInspirationSource('open-one'),
    ]);

    $user = settingsUserWith([
        'enabled_sources' => ['open-one'],
        'keys' => ['flickr' => ['key' => 'super-secret']],
        'maturity' => true,
        'acknowledged_tier3' => ['pixiv'],
    ]);

    $response = $this->actingAs($user)->get('/inspiration/settings');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inspiration/settings', false)
            ->has('sources')
            ->where('settings.maturity', true)
            ->where('settings.enabled_sources', ['open-one'])
            ->where('settings.acknowledged_tier3', ['pixiv'])
            ->missing('settings.keys')
            ->where('sources', function ($sources): bool {
                $byKey = collect($sources)->keyBy('key');

                return $byKey['flickr']['key'] === 'flickr'
                    && $byKey['flickr']['label'] === 'Flickr'
                    && $byKey['flickr']['needs_key'] === true
                    && $byKey['flickr']['has_key'] === true
                    && $byKey['flickr']['configured'] === true
                    && $byKey['flickr']['credential_fields'] === ['key']
                    && $byKey['flickr']['enabled'] === false
                    && $byKey['flickr']['has_tier3_notice'] === false
                    && $byKey['open-one']['needs_key'] === false
                    && $byKey['open-one']['credential_fields'] === []
                    && $byKey['open-one']['has_key'] === false
                    && $byKey['open-one']['enabled'] === true;
            }));

    // The Inertia props are serialized in the page payload; a persisted raw key
    // must never reach the client in any form.
    expect($response->getContent())->not->toContain('super-secret');
});

it('rejects an invalid enabled_sources entry', function () {
    $user = settingsUserWith([]);

    $this->actingAs($user)
        ->patchJson('/inspiration/settings', ['enabled_sources' => ['not-a-source']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('enabled_sources.0');
});

it('persists the full settings bag', function () {
    $user = settingsUserWith([]);

    $this->actingAs($user)
        ->patch('/inspiration/settings', [
            'enabled_sources' => ['deviantart', 'wallhaven'],
            'maturity' => true,
        ])
        ->assertRedirect();

    $bag = app(InspirationSettings::class)->for($user);

    expect($bag->maturity)->toBeTrue()
        ->and($bag->enabledSources)->toBe(['deviantart', 'wallhaven'])
        ->and($bag->keys)->toBe([])
        ->and($bag->zerochanUa)->toBeNull();
});

it('persists a valid key for a source that needs one', function () {
    FakeInspirationSource::register([new FakeInspirationSource('flickr', needsKey: true)]);
    $user = settingsUserWith([]);

    $this->actingAs($user)
        ->patch('/inspiration/settings', ['keys' => ['flickr' => ['key' => 'abc']]])
        ->assertRedirect();

    $bag = app(InspirationSettings::class)->for($user);

    expect($bag->keys)->toBe(['flickr' => ['key' => 'abc']])
        ->and($bag->hasKey('flickr'))->toBeTrue();
});

it('ignores keys sent for a source without credential fields', function () {
    FakeInspirationSource::register([new FakeInspirationSource('open-one')]);

    $user = settingsUserWith([]);

    $this->actingAs($user)
        ->patch('/inspiration/settings', ['keys' => ['open-one' => ['key' => 'abc']]])
        ->assertRedirect();

    $bag = app(InspirationSettings::class)->for($user);

    expect($bag->keys)->toBe([])
        ->and($bag->hasKey('open-one'))->toBeFalse();
});

it('keeps omitted fields when a patch only sends maturity', function () {
    FakeInspirationSource::register([new FakeInspirationSource('flickr', needsKey: true)]);

    $user = settingsUserWith([
        'keys' => ['flickr' => ['key' => 'kept']],
        'enabled_sources' => ['flickr'],
        'acknowledged_tier3' => ['pixiv'],
    ]);

    $this->actingAs($user)
        ->patch('/inspiration/settings', ['maturity' => true])
        ->assertRedirect();

    $bag = app(InspirationSettings::class)->for($user);

    expect($bag->keys)->toBe(['flickr' => ['key' => 'kept']])
        ->and($bag->maturity)->toBeTrue()
        ->and($bag->enabledSources)->toBe(['flickr'])
        ->and($bag->acknowledgedTier3)->toBe(['pixiv']);
});

it('exposes per-source credential fields and round-trips a non-default field', function () {
    FakeInspirationSource::register([new FakeInspirationSource('discogs', needsKey: true)]);

    $user = settingsUserWith([]);

    $this->actingAs($user)
        ->get('/inspiration/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('sources', function ($sources): bool {
            $discogs = collect($sources)->firstWhere('key', 'discogs');

            return $discogs['credential_fields'] === ['token']
                && $discogs['has_key'] === false;
        }));

    $this->actingAs($user)
        ->patch('/inspiration/settings', ['keys' => ['discogs' => ['token' => 'tok-secret']]])
        ->assertRedirect();

    $bag = app(InspirationSettings::class)->for($user);

    expect($bag->keys)->toBe(['discogs' => ['token' => 'tok-secret']])
        ->and($bag->hasKey('discogs'))->toBeTrue();

    $response = $this->actingAs($user)->get('/inspiration/settings');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('sources', function ($sources): bool {
            $discogs = collect($sources)->firstWhere('key', 'discogs');

            return $discogs['credential_fields'] === ['token']
                && $discogs['has_key'] === true;
        }));

    expect($response->getContent())->not->toContain('tok-secret');
});

it('normalizes an empty key to null', function () {
    FakeInspirationSource::register([new FakeInspirationSource('flickr', needsKey: true)]);

    $user = settingsUserWith(['keys' => ['flickr' => ['key' => 'old']]]);

    $this->actingAs($user)
        ->patch('/inspiration/settings', ['keys' => ['flickr' => ['key' => '']]])
        ->assertRedirect();

    $bag = app(InspirationSettings::class)->for($user);

    expect($bag->keys)->toBe([])
        ->and($bag->hasKey('flickr'))->toBeFalse();
});

it('exposes an optional credential field for wallhaven while staying keyless', function () {
    FakeInspirationSource::register([new FakeInspirationSource('wallhaven')]);

    $user = settingsUserWith([]);

    $this->actingAs($user)
        ->get('/inspiration/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('sources', function ($sources): bool {
            $wallhaven = collect($sources)->firstWhere('key', 'wallhaven');

            return $wallhaven['needs_key'] === false
                && $wallhaven['credential_fields'] === ['key']
                && $wallhaven['has_key'] === false
                && $wallhaven['configured'] === true;
        }));

    $this->actingAs($user)
        ->patch('/inspiration/settings', ['keys' => ['wallhaven' => ['key' => 'wh-secret']]])
        ->assertRedirect();

    $bag = app(InspirationSettings::class)->for($user);

    expect($bag->keys)->toBe(['wallhaven' => ['key' => 'wh-secret']])
        ->and($bag->hasKey('wallhaven'))->toBeTrue();

    $response = $this->actingAs($user)->get('/inspiration/settings');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('sources', function ($sources): bool {
            $wallhaven = collect($sources)->firstWhere('key', 'wallhaven');

            return $wallhaven['credential_fields'] === ['key']
                && $wallhaven['has_key'] === true
                && $wallhaven['configured'] === true;
        }));

    expect($response->getContent())->not->toContain('wh-secret');
});

it('persists acknowledged tier 3 sources', function () {
    $user = settingsUserWith([]);

    $this->actingAs($user)
        ->patch('/inspiration/settings', ['acknowledged_tier3' => ['pixiv', 'bandcamp']])
        ->assertRedirect();

    $bag = app(InspirationSettings::class)->for($user);

    expect($bag->acknowledgedTier3)->toBe(['pixiv', 'bandcamp']);
});

it('rejects an acknowledged tier 3 source outside the config list', function () {
    config()->set('inspiration.tier3', ['pixiv']);

    $user = settingsUserWith([]);

    $this->actingAs($user)
        ->patchJson('/inspiration/settings', ['acknowledged_tier3' => ['not-tier3']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('acknowledged_tier3.0');
});

it('rejects enabling a tier 3 source without acknowledgement', function () {
    config()->set('inspiration.tier3', ['wikiart']);
    FakeInspirationSource::register([new FakeInspirationSource('wikiart')]);

    $user = settingsUserWith([]);

    $this->actingAs($user)
        ->patchJson('/inspiration/settings', ['enabled_sources' => ['wikiart']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('enabled_sources.0');
});

it('allows enabling a tier 3 source once acknowledged', function () {
    config()->set('inspiration.tier3', ['wikiart']);
    FakeInspirationSource::register([new FakeInspirationSource('wikiart')]);

    $user = settingsUserWith(['acknowledged_tier3' => ['wikiart']]);

    $this->actingAs($user)
        ->patch('/inspiration/settings', ['enabled_sources' => ['wikiart']])
        ->assertRedirect();

    expect(app(InspirationSettings::class)->for($user)->enabledSources)->toBe(['wikiart']);
});

it('allows enabling a tier 3 source when the same patch acknowledges it', function () {
    config()->set('inspiration.tier3', ['wikiart']);
    FakeInspirationSource::register([new FakeInspirationSource('wikiart')]);

    $user = settingsUserWith([]);

    $this->actingAs($user)
        ->patch('/inspiration/settings', [
            'enabled_sources' => ['wikiart'],
            'acknowledged_tier3' => ['wikiart'],
        ])
        ->assertRedirect();

    $bag = app(InspirationSettings::class)->for($user);

    expect($bag->enabledSources)->toBe(['wikiart'])
        ->and($bag->acknowledgedTier3)->toBe(['wikiart']);
});

it('fails the test endpoint when a source that needs a key has none', function () {
    FakeInspirationSource::register([new FakeInspirationSource('flickr', needsKey: true)]);
    $user = settingsUserWith([]);

    $response = $this->actingAs($user)->post('/inspiration/sources/flickr/test');

    assertFailedTestResponse($response);
    expect($response->json('message'))->toBe('falta la key de esta fuente');
});

it('fails the test endpoint when the source reports a failure', function () {
    FakeInspirationSource::register([new FakeInspirationSource('flickr', fails: true, needsKey: true)]);

    $user = settingsUserWith([
        'keys' => ['flickr' => ['key' => 'abc']],
        'enabled_sources' => ['flickr'],
    ]);

    $response = $this->actingAs($user)->post('/inspiration/sources/flickr/test');

    assertFailedTestResponse($response);
    expect($response->json('message'))->not->toBeEmpty();
});

it('returns ok when the source test succeeds', function () {
    Http::fake([
        'wallhaven.cc/*' => Http::response(['data' => [], 'meta' => []], 200),
    ]);

    $user = settingsUserWith([]);

    $this->actingAs($user)
        ->post('/inspiration/sources/wallhaven/test')
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('fails the test endpoint for an unknown source', function () {
    $user = settingsUserWith([]);

    $response = $this->actingAs($user)->post('/inspiration/sources/nope/test');

    assertFailedTestResponse($response);
});

it('leaves the bag unchanged when the patch is empty', function () {
    FakeInspirationSource::register([new FakeInspirationSource('flickr', needsKey: true)]);

    $user = settingsUserWith([
        'enabled_sources' => ['flickr'],
        'keys' => ['flickr' => ['key' => 'kept']],
        'maturity' => true,
        'zerochan_ua' => 'Megalomaniac/2.0',
        'acknowledged_tier3' => ['pixiv'],
    ]);

    $this->actingAs($user)
        ->patch('/inspiration/settings', [])
        ->assertRedirect();

    $bag = app(InspirationSettings::class)->for($user);

    expect($bag->enabledSources)->toBe(['flickr'])
        ->and($bag->keys)->toBe(['flickr' => ['key' => 'kept']])
        ->and($bag->maturity)->toBeTrue()
        ->and($bag->zerochanUa)->toBe('Megalomaniac/2.0')
        ->and($bag->acknowledgedTier3)->toBe(['pixiv']);
});

it('normalizes a blank zerochan user agent to null', function () {
    $user = settingsUserWith(['zerochan_ua' => 'Megalomaniac/2.0']);

    $this->actingAs($user)
        ->patch('/inspiration/settings', ['zerochan_ua' => '   '])
        ->assertRedirect();

    expect(app(InspirationSettings::class)->for($user)->zerochanUa)->toBeNull();
});

it('keeps the zerochan user agent when the patch omits it', function () {
    $user = settingsUserWith(['zerochan_ua' => 'Megalomaniac/2.0']);

    $this->actingAs($user)
        ->patch('/inspiration/settings', ['maturity' => true])
        ->assertRedirect();

    expect(app(InspirationSettings::class)->for($user)->zerochanUa)->toBe('Megalomaniac/2.0');
});

it('does not leak non-source exception messages from the test endpoint', function () {
    $exploding = new class implements Source
    {
        public function key(): string
        {
            return 'boom';
        }

        public function label(): string
        {
            return 'Boom';
        }

        public function capabilities(): SourceCapabilities
        {
            return new SourceCapabilities(true, true, false, false);
        }

        public function isConfigured(): bool
        {
            return true;
        }

        /**
         * @param  array<string, mixed>  $credentials
         */
        public function setCredentials(array $credentials): void {}

        public function search(string $query, int $page, SourceQuery $queryOptions): Page
        {
            return Page::fromItems([], false, null);
        }

        public function explore(int $page, SourceQuery $queryOptions): Page
        {
            return Page::fromItems([], false, null);
        }

        public function test(): bool
        {
            throw new RuntimeException('secret internal detail');
        }
    };

    app()->instance('inspiration.fake.boom', $exploding);
    app()->tag(['inspiration.fake.boom'], 'inspiration.sources');

    $user = settingsUserWith([]);

    $response = $this->actingAs($user)->post('/inspiration/sources/boom/test');

    assertFailedTestResponse($response);
    expect($response->json('message'))
        ->toBe('no se pudo conectar con esta fuente')
        ->not->toContain('secret internal detail');
});
