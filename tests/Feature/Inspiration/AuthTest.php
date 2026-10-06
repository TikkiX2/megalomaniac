<?php

declare(strict_types=1);

use App\Inspiration\Auth\InspirationAuthStore;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Exceptions\SourceException;
use App\Inspiration\SourceManager;
use App\Models\InspirationAuth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeInspirationSource;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

function authUser(): User
{
    return User::factory()->create();
}

it('stores a pasted session cookie encrypted at rest and never leaks it', function (): void {
    $user = authUser();

    $this->actingAs($user)
        ->postJson('/inspiration/sources/behance/auth', [
            'method' => 'cookie',
            'cookie' => 'behance_session=secret-cookie-value; Path=/',
        ])
        ->assertStatus(201)
        ->assertJson(['ok' => true]);

    $row = InspirationAuth::where('user_id', $user->id)->where('source', 'behance')->first();

    expect($row)->not->toBeNull()
        ->and($row->type)->toBe('cookie')
        ->and($row->data)->not->toContain('secret-cookie-value');

    // Decryption round-trips the exact cookie header.
    $encrypted = json_decode(Crypt::decryptString($row->data), true);

    expect($encrypted['cookies'])->toBe('behance_session=secret-cookie-value; Path=/');

    // The settings page exposes only presence flags.
    $response = $this->actingAs($user)->get('/inspiration/settings');

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sources', function ($sources): bool {
            $behance = collect($sources)->firstWhere('key', 'behance');

            return $behance['has_auth'] === true
                && $behance['auth_type'] === 'cookie'
                && $behance['auth_invalid'] === false;
        }));

    expect($response->getContent())->not->toContain('secret-cookie-value');
});

it('rejects auth for a source outside the supported list', function (): void {
    $user = authUser();

    $this->actingAs($user)
        ->postJson('/inspiration/sources/unsplash/auth', [
            'method' => 'cookie',
            'cookie' => 'abc=1; Path=/',
        ])
        ->assertStatus(422)
        ->assertJson(['ok' => false]);
});

it('rejects a login without the risk acknowledgement', function (): void {
    $user = authUser();

    $this->actingAs($user)
        ->postJson('/inspiration/sources/behance/auth', [
            'method' => 'login',
            'email' => 'me@example.com',
            'password' => 'secret',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['acknowledged_login']);
});

it('runs the simulated login flow and stores the session cookies', function (): void {
    Http::fake([
        '*behance.net/api/v3/authentication/session*' => Http::response('', 200, [
            'Set-Cookie' => 'behance_session=logged-in-cookie; Path=/',
        ]),
    ]);

    $user = authUser();

    $this->actingAs($user)
        ->postJson('/inspiration/sources/behance/auth', [
            'method' => 'login',
            'email' => 'me@example.com',
            'password' => 'secret-pass',
            'acknowledged_login' => true,
        ])
        ->assertStatus(201)
        ->assertJson(['ok' => true]);

    $row = InspirationAuth::where('user_id', $user->id)->where('source', 'behance')->first();

    expect($row->type)->toBe('login');

    $encrypted = json_decode(Crypt::decryptString($row->data), true);

    expect($encrypted['cookies'])->toContain('behance_session=logged-in-cookie')
        ->and($row->data)->not->toContain('secret-pass');
});

it('reports an unimplemented login flow so the user falls back to the cookie', function (): void {
    $user = authUser();

    $this->actingAs($user)
        ->postJson('/inspiration/sources/pinterest/auth', [
            'method' => 'login',
            'email' => 'me@example.com',
            'password' => 'secret',
            'acknowledged_login' => true,
        ])
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'pegá la cookie'));
});

it('disconnects a stored credential', function (): void {
    $user = authUser();

    app(InspirationAuthStore::class)->saveCookie($user, 'behance', 'abc=1; Path=/');

    $this->actingAs($user)
        ->deleteJson('/inspiration/sources/behance/auth')
        ->assertStatus(204);

    expect(app(InspirationAuthStore::class)->hasCredential($user, 'behance'))->toBeFalse();
});

it('marks the credential invalid when the source answers 401/403', function (): void {
    $user = authUser();

    app(InspirationAuthStore::class)->saveCookie($user, 'behance', 'abc=1; Path=/');

    FakeInspirationSource::register([
        new FakeInspirationSource('behance', searchCallback: function (): Page {
            throw new SourceException('behance: HTTP 403', httpStatus: 403);
        }),
    ]);

    $manager = app(SourceManager::class);

    try {
        $manager->search($user, 'behance', 'portrait');
    } catch (SourceException) {
        // Expected: no cache to fall back on -> propagates degraded.
    }

    expect(app(InspirationAuthStore::class)->status($user, 'behance'))
        ->toMatchArray(['type' => 'cookie', 'invalid' => true]);
});

it('does not send a stale invalid cookie and offers reconnection', function (): void {
    $user = authUser();

    $store = app(InspirationAuthStore::class);

    $store->saveCookie($user, 'behance', 'abc=1; Path=/');
    $store->markInvalid($user, 'behance');

    expect($store->cookiesFor($user, 'behance'))->toBeNull()
        ->and($store->status($user, 'behance'))->toMatchArray(['invalid' => true]);
});

it('re-logins an invalid login credential once per rate window', function (): void {
    Http::fake([
        '*behance.net/api/v3/authentication/session*' => Http::response('', 200, [
            'Set-Cookie' => 'behance_session=fresh-cookie; Path=/',
        ]),
    ]);

    $user = authUser();

    $store = app(InspirationAuthStore::class);

    $store->saveLogin($user, 'behance', 'me@example.com', 'pw');
    $store->markInvalid($user, 'behance');
    RateLimiter::clear('inspiration:relogin:behance:'.$user->id);

    expect($store->cookiesFor($user, 'behance'))->toContain('behance_session=fresh-cookie')
        ->and($store->status($user, 'behance'))->toMatchArray(['invalid' => false]);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'authentication/session'));
});
