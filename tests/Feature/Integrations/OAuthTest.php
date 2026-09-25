<?php

use App\Integrations\OAuth\OAuthBroker;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

it('builds a google authorize url with state and pkce', function () {
    config(['services.google.oauth.client_id' => 'client-1', 'services.google.oauth.client_secret' => 'shh']);

    $connection = Connection::factory()->create(['kind' => 'google', 'auth_type' => 'oauth2']);

    $url = app(OAuthBroker::class)->redirectUrl($connection);

    expect($url)->toContain('accounts.google.com/o/oauth2/v2/auth')
        ->and($url)->toContain('client_id=client-1')
        ->and($url)->toContain('state=')
        ->and($url)->toContain('code_challenge=')
        ->and($url)->toContain('code_challenge_method=S256')
        ->and($url)->toContain('access_type=offline');
});

it('exchanges a code and stores encrypted tokens', function () {
    config(['services.google.oauth.client_id' => 'client-1', 'services.google.oauth.client_secret' => 'shh']);
    Http::fake(['oauth2.googleapis.com/token' => Http::response([
        'access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600,
    ])]);

    $connection = Connection::factory()->create([
        'kind' => 'google',
        'auth_type' => 'oauth2',
        'credentials' => [],
    ]);

    $broker = app(OAuthBroker::class);
    $url = $broker->redirectUrl($connection);
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    $token = $broker->handleCallback($connection, 'the-code', $query['state']);

    expect($token->accessToken)->toBe('at')
        ->and($token->refreshToken)->toBe('rt')
        ->and($connection->fresh()->credentials['access_token'])->toBe('at')
        ->and($connection->fresh()->getRawOriginal('credentials'))->not->toContain('"at"');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com/token')
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'the-code');
});

it('rejects an invalid oauth state', function () {
    $connection = Connection::factory()->create(['kind' => 'google', 'auth_type' => 'oauth2']);

    app(OAuthBroker::class)->handleCallback($connection, 'code', 'bogus-state');
})->throws(ValidationException::class);

it('refreshes an expired token', function () {
    config(['services.google.oauth.client_id' => 'client-1', 'services.google.oauth.client_secret' => 'shh']);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'new', 'expires_in' => 3600])]);

    $connection = Connection::factory()->create([
        'kind' => 'google',
        'auth_type' => 'oauth2',
        'credentials' => [
            'access_token' => 'old',
            'refresh_token' => 'rt',
            'expires_at' => now()->subMinute()->toIso8601String(),
        ],
    ]);

    app(OAuthBroker::class)->refreshIfNeeded($connection);

    expect($connection->fresh()->credentials['access_token'])->toBe('new');

    Http::assertSent(fn ($request) => $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'rt');
});

it('builds a reddit authorize url with per-connection client credentials', function () {
    $connection = Connection::factory()->create([
        'kind' => 'reddit',
        'auth_type' => 'oauth2',
        'credentials' => ['client_id' => 'reddit-client', 'client_secret' => 'reddit-secret'],
    ]);

    $url = app(OAuthBroker::class)->redirectUrl($connection);

    expect($url)->toContain('www.reddit.com/api/v1/authorize')
        ->and($url)->toContain('client_id=reddit-client')
        ->and($url)->toContain('duration=permanent')
        ->and($url)->toContain('scope=read')
        ->and($url)->toContain('state=');
});

it('exchanges a reddit code and stores tokens', function () {
    Http::fake(['www.reddit.com/api/v1/access_token' => Http::response([
        'access_token' => 'rat', 'refresh_token' => 'rrt', 'expires_in' => 3600,
    ])]);

    $connection = Connection::factory()->create([
        'kind' => 'reddit',
        'auth_type' => 'oauth2',
        'credentials' => ['client_id' => 'reddit-client', 'client_secret' => 'reddit-secret'],
    ]);

    $broker = app(OAuthBroker::class);
    $url = $broker->redirectUrl($connection);
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    $token = $broker->handleCallback($connection, 'the-code', $query['state']);

    expect($token->accessToken)->toBe('rat')
        ->and($connection->fresh()->credentials['refresh_token'])->toBe('rrt');
});

it('does not refresh a valid token', function () {
    Http::fake();

    $connection = Connection::factory()->create([
        'kind' => 'google',
        'auth_type' => 'oauth2',
        'credentials' => [
            'access_token' => 'valid',
            'refresh_token' => 'rt',
            'expires_at' => now()->addHour()->toIso8601String(),
        ],
    ]);

    app(OAuthBroker::class)->refreshIfNeeded($connection);

    expect($connection->fresh()->credentials['access_token'])->toBe('valid');
    Http::assertNothingSent();
});
