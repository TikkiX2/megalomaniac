<?php

use App\Models\Connection;
use App\Models\User;
use Tests\Support\McpFake;

it('lists prompts from connected mcp servers', function () {
    McpFake::server();

    $user = User::factory()->create();
    Connection::factory()->for($user)->create(['kind' => 'mcp', 'name' => 'Notion MCP', 'base_url' => 'https://mcp.test/mcp', 'options' => []]);

    $this->actingAs($user)
        ->getJson(route('ai.mcp-prompts.index'))
        ->assertOk()
        ->assertJsonPath('prompts.0.name', 'resumen')
        ->assertJsonPath('prompts.0.connection_name', 'Notion MCP');
});

it('renders a prompt with arguments', function () {
    McpFake::server();

    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create(['kind' => 'mcp', 'base_url' => 'https://mcp.test/mcp', 'options' => []]);

    $this->actingAs($user)
        ->postJson(route('ai.mcp-prompts.render'), [
            'connection_id' => $connection->id,
            'name' => 'resumen',
            'arguments' => ['tema' => 'finanzas'],
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('text', 'Prompt renderizado');
});

it('404s foreign or non-mcp connections', function () {
    $foreign = Connection::factory()->create(['kind' => 'mcp', 'base_url' => 'https://mcp.test/mcp']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('ai.mcp-prompts.render'), ['connection_id' => $foreign->id, 'name' => 'x'])
        ->assertNotFound();
});
