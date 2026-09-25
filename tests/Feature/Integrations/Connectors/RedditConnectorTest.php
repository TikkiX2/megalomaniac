<?php

use App\Integrations\Connectors\Reddit\RedditConnector;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function reddit(): Connection
{
    return Connection::factory()->make([
        'kind' => 'reddit',
        'auth_type' => 'oauth2',
        'base_url' => 'https://oauth.reddit.com',
        'credentials' => [
            'access_token' => 'reddit_at',
            'refresh_token' => 'reddit_rt',
            'expires_at' => now()->addHour()->toIso8601String(),
        ],
    ]);
}

it('declares the reddit catalog', function () {
    $keys = collect((new RedditConnector)->actions())->pluck('key')->all();

    expect($keys)->toContain('user.me', 'subreddit.hot', 'search.query', 'post.get', 'vote', 'post.submit')
        ->and((new RedditConnector)->group())->toBe('Contenido')
        ->and((new RedditConnector)->defaultBaseUrl())->toBe('https://oauth.reddit.com');
});

it('reads the authenticated user with user-agent and bearer', function () {
    Http::fake(['oauth.reddit.com/api/v1/me*' => Http::response(['name' => 'megauser'], 200)]);

    $result = (new RedditConnector)->execute(reddit(), 'user.me', []);

    expect($result->ok)->toBeTrue()->and($result->data['name'])->toBe('megauser');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer reddit_at')
        && $request->hasHeader('User-Agent', 'megalomaniac/1.0')
        && str_contains($request->url(), 'raw_json=1'));
});

it('lists subreddit hot posts', function () {
    Http::fake(['oauth.reddit.com/r/laravel/hot*' => Http::response(['data' => ['children' => [['data' => ['title' => 'Post']]]]], 200)]);

    $result = (new RedditConnector)->execute(reddit(), 'subreddit.hot', ['sub' => 'laravel', 'limit' => 10]);

    expect($result->ok)->toBeTrue()->and($result->data['data']['children'][0]['data']['title'])->toBe('Post');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/r/laravel/hot') && str_contains($request->url(), 'limit=10'));
});

it('votes as form data', function () {
    Http::fake(['oauth.reddit.com/api/vote*' => Http::response([], 200)]);

    $result = (new RedditConnector)->execute(reddit(), 'vote', ['id' => 't3_abc', 'direction' => 1]);

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'api/vote')
        && ($request->data()['id'] ?? null) === 't3_abc');
});

it('tests the connection via user.me', function () {
    Http::fake(['oauth.reddit.com/api/v1/me*' => Http::response(['name' => 'megauser'], 200)]);

    $result = (new RedditConnector)->test(reddit());

    expect($result->ok)->toBeTrue()->and($result->meta['name'])->toBe('megauser');
});
