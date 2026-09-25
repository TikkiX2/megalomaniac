<?php

use App\Integrations\Connectors\HomeAssistant\HomeAssistantConnector;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function homeAssistant(): Connection
{
    return Connection::factory()->make([
        'kind' => 'home_assistant',
        'base_url' => 'http://ha.local:8123',
        'credentials' => ['token' => 'ha_token'],
    ]);
}

it('declares the home assistant catalog', function () {
    $keys = collect((new HomeAssistantConnector)->actions())->pluck('key')->all();

    expect($keys)->toContain('api.status', 'states.list', 'states.get', 'services.call', 'scenes.list', 'template.render')
        ->and((new HomeAssistantConnector)->defaultBaseUrl())->toBe('http://localhost:8123');
});

it('lists and filters states by domain', function () {
    Http::fake(['ha.local:8123/api/states' => Http::response([
        ['entity_id' => 'light.kitchen', 'state' => 'on'],
        ['entity_id' => 'sensor.temp', 'state' => '22'],
    ], 200)]);

    $result = (new HomeAssistantConnector)->execute(homeAssistant(), 'states.list', ['domain' => 'light']);

    expect($result->ok)->toBeTrue()
        ->and($result->data)->toHaveCount(1)
        ->and($result->data[0]['entity_id'])->toBe('light.kitchen');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer ha_token'));
});

it('calls a service', function () {
    Http::fake(['ha.local:8123/api/services/light/turn_on' => Http::response([], 200)]);

    $result = (new HomeAssistantConnector)->execute(homeAssistant(), 'services.call', [
        'domain' => 'light',
        'service' => 'turn_on',
        'entity_id' => 'light.kitchen',
    ]);

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->data()['entity_id'] === 'light.kitchen');
});

it('lists scenes', function () {
    Http::fake(['ha.local:8123/api/states' => Http::response([
        ['entity_id' => 'scene.movie', 'state' => 'scening'],
        ['entity_id' => 'light.kitchen', 'state' => 'on'],
    ], 200)]);

    $result = (new HomeAssistantConnector)->execute(homeAssistant(), 'scenes.list', []);

    expect($result->ok)->toBeTrue()->and($result->data)->toHaveCount(1)
        ->and($result->data[0]['entity_id'])->toBe('scene.movie');
});

it('tests the connection via api status', function () {
    Http::fake(['ha.local:8123/api/' => Http::response(['message' => 'API running.'], 200)]);

    $result = (new HomeAssistantConnector)->test(homeAssistant());

    expect($result->ok)->toBeTrue()->and($result->meta['message'])->toBe('API running.');
});
