<?php

use App\Integrations\Connectors\Arr\SonarrConnector;
use App\Integrations\Enums\ActionAccess;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function sonarr(): Connection
{
    return Connection::factory()->make([
        'kind' => 'sonarr',
        'base_url' => 'http://sonarr.local',
        'credentials' => ['api_key' => 'arr_key'],
    ]);
}

it('declares the sonarr catalog', function () {
    $connector = new SonarrConnector;
    $keys = collect($connector->actions())->pluck('key')->all();

    expect($keys)->toContain('series.list', 'series.add', 'queue.list', 'calendar.list', 'commands.search')
        ->and($connector->group())->toBe('Media')
        ->and($connector->authFields()[0]->name)->toBe('api_key')
        ->and(collect($connector->actions())->firstWhere('key', 'series.delete')->access)->toBe(ActionAccess::Destructive);
});

it('lists series with the api key header', function () {
    Http::fake(['sonarr.local/api/v3/series*' => Http::response([['id' => 1, 'title' => 'Severance']], 200)]);

    $result = (new SonarrConnector)->execute(sonarr(), 'series.list', []);

    expect($result->ok)->toBeTrue()->and($result->data[0]['title'])->toBe('Severance');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'arr_key')
        && str_contains($request->url(), '/api/v3/series'));
});

it('adds a series', function () {
    Http::fake(['sonarr.local/api/v3/series' => Http::response(['id' => 9], 201)]);

    $result = (new SonarrConnector)->execute(sonarr(), 'series.add', [
        'tvdb_id' => 121361,
        'title' => 'Game of Thrones',
        'quality_profile_id' => 1,
        'root_folder' => '/tv',
    ]);

    expect($result->ok)->toBeTrue()->and($result->data['id'])->toBe(9);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->data()['tvdbId'] === 121361
        && $request->data()['rootFolderPath'] === '/tv');
});

it('maps arr errors', function () {
    Http::fake(['sonarr.local/*' => Http::response(['message' => 'Unauthorized'], 401)]);

    $result = (new SonarrConnector)->execute(sonarr(), 'system.status', []);

    expect($result->ok)->toBeFalse()->and($result->error)->toContain('401');
});

it('tests the connection via system status', function () {
    Http::fake(['sonarr.local/api/v3/system/status' => Http::response(['version' => '4.0.0'], 200)]);

    $result = (new SonarrConnector)->test(sonarr());

    expect($result->ok)->toBeTrue()->and($result->meta['version'])->toBe('4.0.0');
});
