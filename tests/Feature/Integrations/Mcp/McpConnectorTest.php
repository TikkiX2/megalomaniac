<?php

use App\Integrations\Connectors\Mcp\McpConnector;
use App\Integrations\Enums\ActionAccess;
use Tests\Support\McpFake;

it('exposes generic actions plus discovered tools', function () {
    McpFake::server([
        ['name' => 'search', 'description' => 'Busca', 'inputSchema' => ['properties' => ['q' => ['type' => 'string']], 'required' => ['q']], 'annotations' => ['readOnlyHint' => true]],
        ['name' => 'create_page', 'description' => 'Crea', 'inputSchema' => []],
    ]);

    $connector = new McpConnector;

    expect(collect($connector->actions())->pluck('key'))->toContain('mcp.info', 'resources.read', 'prompts.get')
        ->not->toContain('tools.search');

    $actions = collect($connector->actionsFor(McpFake::connection()))->keyBy('key');

    expect($actions)->toHaveKeys(['tools.search', 'tools.create_page'])
        ->and($actions['tools.search']->access)->toBe(ActionAccess::Read)
        ->and($actions['tools.create_page']->access)->toBe(ActionAccess::Write);
});

it('respects the per-connection tool allowlist', function () {
    McpFake::server([
        ['name' => 'search', 'inputSchema' => []],
        ['name' => 'create_page', 'inputSchema' => []],
    ]);

    $connection = McpFake::connection(['options' => ['tools' => ['search']]]);

    $keys = collect((new McpConnector)->actionsFor($connection))->pluck('key')->all();

    expect($keys)->toContain('tools.search')->not->toContain('tools.create_page');
});

it('calls a tool and returns structured content', function () {
    McpFake::server([['name' => 'search', 'inputSchema' => []]]);

    $result = (new McpConnector)->execute(McpFake::connection(), 'tools.search', ['q' => 'hola']);

    expect($result->ok)->toBeTrue()->and($result->data)->toBe(['ok' => true]);
});

it('maps tool errors and unknown tools', function () {
    McpFake::server([], [
        'tools/call' => ['content' => [['type' => 'text', 'text' => 'boom']], 'isError' => true],
    ]);

    $result = (new McpConnector)->execute(McpFake::connection(), 'tools.anything', []);

    expect($result->ok)->toBeFalse()->and($result->error)->toContain('boom');

    $disabled = McpFake::connection(['options' => ['tools' => ['other']]]);
    $blocked = (new McpConnector)->execute($disabled, 'tools.anything', []);

    expect($blocked->ok)->toBeFalse()->and($blocked->error)->toContain('no está habilitada');
});

it('caps large tool outputs', function () {
    config(['integrations.mcp.output_cap_bytes' => 100]);

    McpFake::server([], [
        'tools/call' => ['content' => [['type' => 'text', 'text' => str_repeat('x', 5000)]], 'isError' => false],
    ]);

    $result = (new McpConnector)->execute(McpFake::connection(), 'tools.anything', []);

    expect($result->ok)->toBeTrue()->and(strlen($result->data['text']))->toBeLessThanOrEqual(100);
});

it('reads resources, renders prompts and reports server info', function () {
    McpFake::server();

    $connector = new McpConnector;
    $connection = McpFake::connection();

    $resources = $connector->execute($connection, 'resources.list', []);
    expect($resources->data[0]['uri'])->toBe('file://doc');

    $read = $connector->execute($connection, 'resources.read', ['uri' => 'file://doc']);
    expect($read->data['content'])->toBe('contenido');

    $prompts = $connector->execute($connection, 'prompts.list', []);
    expect($prompts->data[0]['name'])->toBe('resumen');

    $rendered = $connector->execute($connection, 'prompts.get', ['name' => 'resumen', 'arguments' => ['tema' => 'x']]);
    expect($rendered->data['text'])->toBe('Prompt renderizado');

    $info = $connector->execute($connection, 'mcp.info', []);
    expect($info->data['name'])->toBe('fake-mcp');
});

it('surfaces authorization required on execute and test', function () {
    McpFake::unauthorized();

    $connector = new McpConnector;
    $connection = McpFake::connection();

    $result = $connector->execute($connection, 'mcp.info', []);

    expect($result->ok)->toBeFalse()
        ->and($result->data['authorization_required'] ?? false)->toBeTrue()
        ->and($result->data['scope'] ?? null)->toBe('default');

    $test = $connector->test($connection);

    expect($test->ok)->toBeFalse()
        ->and($test->meta['authorization_required'])->toBeTrue();
});

it('tests a healthy connection with counts', function () {
    McpFake::server([['name' => 'search', 'inputSchema' => []]]);

    $test = (new McpConnector)->test(McpFake::connection());

    expect($test->ok)->toBeTrue()
        ->and($test->meta['tools'])->toBe(1)
        ->and($test->meta['resources'])->toBe(1)
        ->and($test->meta['prompts'])->toBe(1);
});
