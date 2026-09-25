<?php

use App\Integrations\Connectors\Arr\JellyseerrConnector;
use App\Integrations\Enums\ActionAccess;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function jellyseerr(): Connection
{
    return Connection::factory()->make([
        'kind' => 'jellyseerr',
        'base_url' => 'http://jellyseerr.local',
        'credentials' => ['api_key' => 'arr_key'],
    ]);
}

it('declares the jellyseerr catalog', function () {
    $connector = new JellyseerrConnector;
    $keys = collect($connector->actions())->pluck('key')->all();

    expect($keys)->toContain('requests.list', 'requests.approve', 'requests.decline', 'media.search')
        ->and(collect($connector->actions())->firstWhere('key', 'requests.approve')->access)->toBe(ActionAccess::Write)
        ->and($connector->defaultBaseUrl())->toBe('http://localhost:5055');
});

it('lists requests', function () {
    Http::fake(['jellyseerr.local/api/v1/request*' => Http::response(['results' => [['id' => 7]]], 200)]);

    $result = (new JellyseerrConnector)->execute(jellyseerr(), 'requests.list', []);

    expect($result->ok)->toBeTrue()->and($result->data['results'][0]['id'])->toBe(7);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'arr_key'));
});

it('approves a request', function () {
    Http::fake(['jellyseerr.local/api/v1/request/7/approve' => Http::response([], 200)]);

    $result = (new JellyseerrConnector)->execute(jellyseerr(), 'requests.approve', ['id' => 7]);

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'request/7/approve'));
});

it('tests the connection via status', function () {
    Http::fake(['jellyseerr.local/api/v1/status' => Http::response(['version' => '1.33.0'], 200)]);

    $result = (new JellyseerrConnector)->test(jellyseerr());

    expect($result->ok)->toBeTrue()->and($result->meta['version'])->toBe('1.33.0');
});
