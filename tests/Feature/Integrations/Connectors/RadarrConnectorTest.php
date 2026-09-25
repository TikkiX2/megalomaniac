<?php

use App\Integrations\Connectors\Arr\RadarrConnector;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function radarr(): Connection
{
    return Connection::factory()->make([
        'kind' => 'radarr',
        'base_url' => 'http://radarr.local',
        'credentials' => ['api_key' => 'arr_key'],
    ]);
}

it('declares the radarr catalog', function () {
    $keys = collect((new RadarrConnector)->actions())->pluck('key')->all();

    expect($keys)->toContain('movies.list', 'movies.add', 'movies.delete', 'queue.list', 'commands.search')
        ->and((new RadarrConnector)->defaultBaseUrl())->toBe('http://localhost:7878');
});

it('lists movies', function () {
    Http::fake(['radarr.local/api/v3/movie*' => Http::response([['id' => 1, 'title' => 'Dune']], 200)]);

    $result = (new RadarrConnector)->execute(radarr(), 'movies.list', []);

    expect($result->ok)->toBeTrue()->and($result->data[0]['title'])->toBe('Dune');
    Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'arr_key'));
});

it('adds a movie', function () {
    Http::fake(['radarr.local/api/v3/movie' => Http::response(['id' => 4], 201)]);

    $result = (new RadarrConnector)->execute(radarr(), 'movies.add', [
        'tmdb_id' => 438631,
        'title' => 'Dune',
        'quality_profile_id' => 1,
        'root_folder' => '/movies',
    ]);

    expect($result->ok)->toBeTrue()->and($result->data['id'])->toBe(4);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->data()['tmdbId'] === 438631
        && $request->data()['addOptions']['searchForMovie'] === true);
});

it('tests the connection via system status', function () {
    Http::fake(['radarr.local/api/v3/system/status' => Http::response(['version' => '5.1.0'], 200)]);

    $result = (new RadarrConnector)->test(radarr());

    expect($result->ok)->toBeTrue()->and($result->meta['version'])->toBe('5.1.0');
});
