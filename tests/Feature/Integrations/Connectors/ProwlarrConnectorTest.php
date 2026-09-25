<?php

use App\Integrations\Connectors\Arr\ProwlarrConnector;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function prowlarr(): Connection
{
    return Connection::factory()->make([
        'kind' => 'prowlarr',
        'base_url' => 'http://prowlarr.local',
        'credentials' => ['api_key' => 'arr_key'],
    ]);
}

it('declares the prowlarr catalog', function () {
    $keys = collect((new ProwlarrConnector)->actions())->pluck('key')->all();

    expect($keys)->toContain('indexers.list', 'indexers.test', 'search.query', 'health.list')
        ->and((new ProwlarrConnector)->defaultBaseUrl())->toBe('http://localhost:9696');
});

it('lists indexers', function () {
    Http::fake(['prowlarr.local/api/v1/indexer' => Http::response([['id' => 1, 'name' => '1337x']], 200)]);

    $result = (new ProwlarrConnector)->execute(prowlarr(), 'indexers.list', []);

    expect($result->ok)->toBeTrue()->and($result->data[0]['name'])->toBe('1337x');
});

it('runs a search query', function () {
    Http::fake(['prowlarr.local/api/v1/search*' => Http::response([['title' => 'Ubuntu 24.04']], 200)]);

    $result = (new ProwlarrConnector)->execute(prowlarr(), 'search.query', ['query' => 'ubuntu']);

    expect($result->ok)->toBeTrue()->and($result->data[0]['title'])->toBe('Ubuntu 24.04');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'query=ubuntu')
        && $request->hasHeader('X-Api-Key', 'arr_key'));
});

it('tests the connection via system status', function () {
    Http::fake(['prowlarr.local/api/v1/system/status' => Http::response(['version' => '1.20.0'], 200)]);

    $result = (new ProwlarrConnector)->test(prowlarr());

    expect($result->ok)->toBeTrue()->and($result->meta['version'])->toBe('1.20.0');
});
