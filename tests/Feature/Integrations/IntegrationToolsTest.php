<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Tools\IntegrationCallTool;
use App\Ai\Tools\IntegrationCatalogTool;
use App\Integrations\ConnectorRegistry;
use App\Integrations\IntegrationExecutor;
use App\Models\ApprovalRequest;
use App\Models\Connection;
use App\Models\User;
use Laravel\Ai\Tools\Request;
use Tests\Support\FakeConnector;

beforeEach(function () {
    FakeConnector::reset();

    app()->instance(ConnectorRegistry::class, new ConnectorRegistry([FakeConnector::class]));
});

it('lists enabled connections and actions in the catalog tool', function () {
    $user = User::factory()->create();
    Connection::factory()->for($user)->create(['kind' => 'fake', 'name' => 'Mi fake']);
    Connection::factory()->for($user)->create(['kind' => 'fake', 'name' => 'Deshabilitada', 'enabled' => false]);
    Connection::factory()->create(['kind' => 'fake', 'name' => 'Ajena']);

    $payload = json_decode((string) (new IntegrationCatalogTool($user))->handle(new Request([])), true);

    expect($payload['connections'])->toHaveCount(1)
        ->and($payload['connections'][0]['name'])->toBe('Mi fake')
        ->and($payload['connections'][0]['actions'][0]['key'])->toBe('ping')
        ->and($payload['connections'][0]['actions'][0]['access'])->toBe('read');
});

it('executes reads inline through the call tool', function () {
    $user = User::factory()->create();
    Connection::factory()->for($user)->create(['kind' => 'fake', 'name' => 'Mi fake']);

    $tool = new IntegrationCallTool($user, app(IntegrationExecutor::class));
    $payload = json_decode((string) $tool->handle(new Request([
        'connection' => 'Mi fake',
        'action' => 'ping',
        'params' => [],
    ])), true);

    expect($payload['status'])->toBe('success')
        ->and($payload['data'])->toBe(['key' => 'ping']);
});

it('returns pending approval for writes', function () {
    $user = User::factory()->create();
    Connection::factory()->for($user)->create(['kind' => 'fake', 'name' => 'Mi fake']);

    $tool = new IntegrationCallTool($user, app(IntegrationExecutor::class));
    $payload = json_decode((string) $tool->handle(new Request([
        'connection' => 'Mi fake',
        'action' => 'write',
        'params' => [],
    ])), true);

    expect($payload['status'])->toBe('pending_approval')
        ->and($payload['approval_id'])->toBeInt()
        ->and(ApprovalRequest::pending()->count())->toBe(1);
});

it('reports unknown connections with available names', function () {
    $user = User::factory()->create();
    Connection::factory()->for($user)->create(['kind' => 'fake', 'name' => 'Mi fake']);

    $tool = new IntegrationCallTool($user, app(IntegrationExecutor::class));
    $payload = json_decode((string) $tool->handle(new Request([
        'connection' => 'No existe',
        'action' => 'ping',
        'params' => [],
    ])), true);

    expect($payload['status'])->toBe('error')
        ->and($payload['message'])->toContain('Mi fake');
});

it('filters the catalog and rejects calls outside the allowlist', function () {
    $user = User::factory()->create();
    Connection::factory()->for($user)->create(['kind' => 'fake', 'name' => 'Mi fake']);

    $catalog = json_decode((string) (new IntegrationCatalogTool($user, ['github']))->handle(new Request([])), true);
    expect($catalog['connections'])->toHaveCount(0);

    $tool = new IntegrationCallTool($user, app(IntegrationExecutor::class), ['github']);
    $payload = json_decode((string) $tool->handle(new Request([
        'connection' => 'Mi fake',
        'action' => 'ping',
        'params' => [],
    ])), true);

    expect($payload['status'])->toBe('error')
        ->and($payload['message'])->toContain('no está permitida');

    $allowed = new IntegrationCallTool($user, app(IntegrationExecutor::class), ['fake']);
    $ok = json_decode((string) $allowed->handle(new Request([
        'connection' => 'Mi fake',
        'action' => 'ping',
        'params' => [],
    ])), true);

    expect($ok['status'])->toBe('success');
});

it('registers both tools on the agent', function () {
    $user = User::factory()->create();

    $classes = collect((new MegalomaniacAgent($user))->tools())
        ->map(fn ($tool): string => $tool::class)
        ->all();

    expect($classes)->toContain(IntegrationCatalogTool::class, IntegrationCallTool::class);
});
