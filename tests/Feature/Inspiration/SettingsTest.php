<?php

declare(strict_types=1);

use App\Inspiration\InspirationSettings;
use App\Models\InspirationSetting;
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
        ->and(array_keys($fields))->toEqualCanonicalizing([
            'flickr', 'tumblr', 'unsplash', 'pexels', 'pixabay', 'discogs',
            'giphy', 'europeana', 'rijksmuseum', 'wikiart', 'pixiv',
        ]);
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
                    && $byKey['flickr']['enabled'] === false
                    && $byKey['flickr']['has_tier3_notice'] === false
                    && $byKey['open-one']['needs_key'] === false
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

it('ignores keys sent for a source that does not need one', function () {
    $user = settingsUserWith([]);

    $this->actingAs($user)
        ->patch('/inspiration/settings', ['keys' => ['wallhaven' => ['key' => 'abc']]])
        ->assertRedirect();

    $bag = app(InspirationSettings::class)->for($user);

    expect($bag->keys)->toBe([])
        ->and($bag->hasKey('wallhaven'))->toBeFalse();
});

it('keeps existing keys when a patch does not send keys', function () {
    FakeInspirationSource::register([new FakeInspirationSource('flickr', needsKey: true)]);

    $user = settingsUserWith([
        'keys' => ['flickr' => ['key' => 'kept']],
        'enabled_sources' => ['flickr'],
    ]);

    $this->actingAs($user)
        ->patch('/inspiration/settings', ['maturity' => true])
        ->assertRedirect();

    $bag = app(InspirationSettings::class)->for($user);

    expect($bag->keys)->toBe(['flickr' => ['key' => 'kept']])
        ->and($bag->maturity)->toBeTrue()
        ->and($bag->enabledSources)->toBe([]);
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

it('does not persist anything beyond what the request carried', function () {
    $user = settingsUserWith([]);

    $this->actingAs($user)
        ->patch('/inspiration/settings', [])
        ->assertRedirect();

    $body = InspirationSetting::query()->whereKey($user->getKey())->first()?->body;

    expect($body)->toBe([
        'enabled_sources' => [],
        'keys' => [],
        'maturity' => false,
        'zerochan_ua' => null,
        'acknowledged_tier3' => [],
    ]);
});
