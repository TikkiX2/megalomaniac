<?php

use App\Models\Connection;
use App\Models\User;
use Tests\Support\McpFake;

function uiMcpConnection(User $user): Connection
{
    return Connection::factory()->for($user)->create([
        'kind' => 'mcp',
        'name' => 'Notion MCP',
        'base_url' => 'https://mcp.test/mcp',
        'credentials' => [],
        'options' => [],
    ]);
}

it('discovers tools, resources and prompts with enabled flags', function () {
    McpFake::server([
        ['name' => 'search', 'description' => 'Busca', 'inputSchema' => [], 'annotations' => ['readOnlyHint' => true]],
        ['name' => 'create_page', 'description' => 'Crea', 'inputSchema' => []],
    ]);

    $user = User::factory()->create();
    $connection = uiMcpConnection($user);

    $this->actingAs($user)
        ->getJson(route('connections.discover', $connection))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('tools.0.name', 'search')
        ->assertJsonPath('tools.0.access', 'read')
        ->assertJsonPath('tools.0.enabled', true)
        ->assertJsonPath('resources.0.uri', 'file://doc')
        ->assertJsonPath('prompts.0.name', 'resumen');
});

it('persists the tool allowlist and invalidates the cache', function () {
    McpFake::server([['name' => 'search', 'inputSchema' => []], ['name' => 'create_page', 'inputSchema' => []]]);

    $user = User::factory()->create();
    $connection = uiMcpConnection($user);

    $this->actingAs($user)->patchJson(route('connections.tools', $connection), [
        'enabled_tools' => ['search'],
    ])->assertOk();

    expect($connection->fresh()->options['tools'])->toBe(['search']);

    $this->actingAs($user)
        ->getJson(route('connections.discover', $connection))
        ->assertOk()
        ->assertJsonPath('tools.0.enabled', true)
        ->assertJsonPath('tools.1.enabled', false);
});

it('reports authorization required with a connect url', function () {
    McpFake::unauthorized();

    $user = User::factory()->create();
    $connection = uiMcpConnection($user);

    $this->actingAs($user)
        ->getJson(route('connections.discover', $connection))
        ->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('authorization_required', true)
        ->assertJsonPath('connect_url', fn (string $url): bool => str_contains($url, 'integrations/mcp/'.$connection->id.'/connect'));
});

it('404s discover and tools for non-mcp or foreign connections', function () {
    $github = Connection::factory()->create(['kind' => 'github']);
    $foreign = uiMcpConnection(User::factory()->create());

    $this->actingAs(User::factory()->create())->getJson(route('connections.discover', $github))->assertNotFound();
    $this->actingAs(User::factory()->create())->patchJson(route('connections.tools', $foreign), ['enabled_tools' => []])->assertNotFound();
});
