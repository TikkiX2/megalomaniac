<?php

use App\Integrations\Connectors\Jellyfin\JellyfinConnector;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function jellyfin(): Connection
{
    return Connection::factory()->make([
        'kind' => 'jellyfin',
        'base_url' => 'http://jellyfin.local',
        'credentials' => ['api_key' => 'jf_key'],
    ]);
}

it('declares the jellyfin catalog', function () {
    $keys = collect((new JellyfinConnector)->actions())->pluck('key')->all();

    expect($keys)->toContain('system.info', 'items.list', 'items.search', 'sessions.list', 'library.refresh')
        ->and((new JellyfinConnector)->defaultBaseUrl())->toBe('http://localhost:8096');
});

it('lists media items with the emby token header', function () {
    Http::fake(['jellyfin.local/Items*' => Http::response(['Items' => [['Id' => 'i1', 'Name' => 'Dune']]], 200)]);

    $result = (new JellyfinConnector)->execute(jellyfin(), 'items.list', ['types' => 'Movie']);

    expect($result->ok)->toBeTrue()->and($result->data['Items'][0]['Name'])->toBe('Dune');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'MediaBrowser Token="jf_key"')
        && str_contains($request->url(), 'IncludeItemTypes=Movie'));
});

it('lists sessions', function () {
    Http::fake(['jellyfin.local/Sessions' => Http::response([['Id' => 's1', 'UserName' => 'me']], 200)]);

    $result = (new JellyfinConnector)->execute(jellyfin(), 'sessions.list', []);

    expect($result->ok)->toBeTrue()->and($result->data[0]['UserName'])->toBe('me');
});

it('tests the connection via system info', function () {
    Http::fake(['jellyfin.local/System/Info' => Http::response(['Version' => '10.10.0'], 200)]);

    $result = (new JellyfinConnector)->test(jellyfin());

    expect($result->ok)->toBeTrue()->and($result->meta['version'])->toBe('10.10.0');
});
