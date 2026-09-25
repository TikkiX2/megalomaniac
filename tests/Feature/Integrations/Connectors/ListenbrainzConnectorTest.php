<?php

use App\Integrations\Connectors\Listenbrainz\ListenbrainzConnector;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function listenbrainz(bool $withToken = true): Connection
{
    return Connection::factory()->make([
        'kind' => 'listenbrainz',
        'base_url' => 'https://api.listenbrainz.org',
        'credentials' => $withToken ? ['token' => 'lb_token'] : [],
        'options' => ['username' => 'megauser'],
    ]);
}

it('declares the listenbrainz catalog', function () {
    $keys = collect((new ListenbrainzConnector)->actions())->pluck('key')->all();

    expect($keys)->toContain('user.get', 'listens.recent', 'stats.top_artists', 'stats.top_recordings')
        ->and((new ListenbrainzConnector)->group())->toBe('Música')
        ->and((new ListenbrainzConnector)->defaultBaseUrl())->toBe('https://api.listenbrainz.org');
});

it('reads recent listens with the default username', function () {
    Http::fake(['api.listenbrainz.org/1/user/*' => Http::response(['payload' => ['listens' => [['track_metadata' => ['track_name' => 'T']]]]], 200)]);

    $result = (new ListenbrainzConnector)->execute(listenbrainz(), 'listens.recent', []);

    expect($result->ok)->toBeTrue()->and($result->data['payload']['listens'][0]['track_metadata']['track_name'])->toBe('T');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/1/user/megauser/listens')
        && $request->hasHeader('Authorization', 'Bearer lb_token'));
});

it('reads top artists for a range', function () {
    Http::fake(['api.listenbrainz.org/1/stats/user/*' => Http::response(['payload' => ['artists' => [['artist_name' => 'A']]]], 200)]);

    $result = (new ListenbrainzConnector)->execute(listenbrainz(), 'stats.top_artists', ['range' => 'month']);

    expect($result->ok)->toBeTrue()->and($result->data['payload']['artists'][0]['artist_name'])->toBe('A');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/artists?range=month')
        || str_contains($request->url(), 'range=month'));
});

it('works without a token for public reads', function () {
    Http::fake(['api.listenbrainz.org/*' => Http::response(['payload' => []], 200)]);

    $result = (new ListenbrainzConnector)->execute(listenbrainz(false), 'user.get', []);

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization'));
});

it('tests the connection via user endpoint', function () {
    Http::fake(['api.listenbrainz.org/*' => Http::response(['username' => 'megauser'], 200)]);

    $result = (new ListenbrainzConnector)->test(listenbrainz());

    expect($result->ok)->toBeTrue()->and($result->meta['username'])->toBe('megauser');
});
