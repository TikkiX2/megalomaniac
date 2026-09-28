<?php

use App\Ai\Tools\IntegrationCallTool;
use App\Ai\Tools\IntegrationCatalogTool;
use App\Integrations\Actions\ExecutionContext;
use App\Integrations\IntegrationExecutor;
use App\Models\ApprovalRequest;
use App\Models\Connection;
use App\Models\User;
use Laravel\Ai\Tools\Request;
use Tests\Support\McpFake;

function mcpGovernanceConnection(User $user): Connection
{
    return Connection::factory()->for($user)->create([
        'kind' => 'mcp',
        'name' => 'Notion MCP',
        'base_url' => 'https://mcp.test/mcp',
        'credentials' => [],
        'options' => [],
    ]);
}

function mcpGovernanceTools(): void
{
    McpFake::server([
        ['name' => 'search', 'description' => 'Busca', 'inputSchema' => ['properties' => ['q' => ['type' => 'string']], 'required' => ['q']], 'annotations' => ['readOnlyHint' => true]],
        ['name' => 'create_page', 'description' => 'Crea', 'inputSchema' => []],
    ]);
}

it('executes mcp reads and queues approvals for writes', function () {
    mcpGovernanceTools();

    $user = User::factory()->create();
    $connection = mcpGovernanceConnection($user);
    $executor = app(IntegrationExecutor::class);

    $read = $executor->execute($connection, 'tools.search', ['q' => 'hola'], ExecutionContext::forAgent($user));
    expect($read->ok)->toBeTrue()->and($read->data)->toBe(['ok' => true]);

    $write = $executor->execute($connection, 'tools.create_page', [], ExecutionContext::forAgent($user));
    expect($write->pending)->toBeTrue()
        ->and(ApprovalRequest::pending()->count())->toBe(1)
        ->and(ApprovalRequest::first()->action_key)->toBe('mcp.tools.create_page');
});

it('lists dynamic mcp tools in the integration catalog', function () {
    mcpGovernanceTools();

    $user = User::factory()->create();
    mcpGovernanceConnection($user);

    $payload = json_decode((string) (new IntegrationCatalogTool($user))->handle(new Request([])), true);

    $actions = collect($payload['connections'][0]['actions'])->pluck('key');

    expect($actions)->toContain('tools.search', 'tools.create_page', 'resources.read')
        ->and($payload['connections'][0]['actions'][0]['access'])->toBeIn(['read', 'write', 'destructive']);
});

it('matches the agent allowlist by kind, name and id', function () {
    mcpGovernanceTools();

    $user = User::factory()->create();
    $connection = mcpGovernanceConnection($user);
    $executor = app(IntegrationExecutor::class);

    $byKind = new IntegrationCallTool($user, $executor, ['mcp']);
    $byName = new IntegrationCallTool($user, $executor, ['Notion MCP']);
    $byId = new IntegrationCallTool($user, $executor, [(string) $connection->id]);
    $denied = new IntegrationCallTool($user, $executor, ['github']);

    $call = fn (IntegrationCallTool $tool) => json_decode((string) $tool->handle(new Request([
        'connection' => 'Notion MCP',
        'action' => 'tools.search',
        'params' => ['q' => 'x'],
    ])), true);

    expect($call($byKind)['status'])->toBe('success')
        ->and($call($byName)['status'])->toBe('success')
        ->and($call($byId)['status'])->toBe('success')
        ->and($call($denied)['status'])->toBe('error');
});

it('exposes dynamic actions in the connection actions endpoint', function () {
    mcpGovernanceTools();

    $user = User::factory()->create();
    $connection = mcpGovernanceConnection($user);

    $this->actingAs($user)
        ->get(route('connections.actions', $connection))
        ->assertOk()
        ->assertJsonFragment(['key' => 'tools.search']);
});
