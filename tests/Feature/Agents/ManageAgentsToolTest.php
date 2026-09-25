<?php

use App\Ai\Agents\AgentDefinitionService;
use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Agents\RuntimeAgent;
use App\Ai\Tools\ManageAgentsTool;
use App\Jobs\RunAgentJob;
use App\Models\AgentDefinition;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;

function manageTool(User $user): ManageAgentsTool
{
    return new ManageAgentsTool($user, app(AgentDefinitionService::class));
}

it('lists, creates, updates, toggles and deletes agents', function () {
    $user = User::factory()->create();
    $tool = manageTool($user);

    $empty = json_decode((string) $tool->handle(new Request(['action' => 'list'])), true);
    expect($empty['agents'])->toHaveCount(0);

    $created = json_decode((string) $tool->handle(new Request([
        'action' => 'create',
        'name' => 'Reporte Semanal',
        'instructions' => 'Resumí mis finanzas.',
        'schedule_type' => 'interval',
        'schedule_value' => 'weekly',
        'tools_policy' => ['internal' => ['finance_query'], 'integrations' => []],
    ])), true);

    expect($created['success'])->toBeTrue()
        ->and($created['agent']['key'])->toBe('reporte-semanal');

    $duplicate = json_decode((string) $tool->handle(new Request([
        'action' => 'create',
        'name' => 'Reporte Semanal',
        'instructions' => 'x',
    ])), true);
    expect($duplicate['success'])->toBeFalse();

    $updated = json_decode((string) $tool->handle(new Request([
        'action' => 'update',
        'key' => 'reporte-semanal',
        'instructions' => 'Nuevas instrucciones',
        'schedule_value' => 'daily',
    ])), true);
    expect($updated['success'])->toBeTrue();
    expect(AgentDefinition::first()->instructions)->toBe('Nuevas instrucciones');

    $disabled = json_decode((string) $tool->handle(new Request([
        'action' => 'disable',
        'key' => 'reporte-semanal',
    ])), true);
    expect($disabled['success'])->toBeTrue();
    expect(AgentDefinition::first()->enabled)->toBeFalse();

    $deleted = json_decode((string) $tool->handle(new Request([
        'action' => 'delete',
        'key' => 'reporte-semanal',
    ])), true);
    expect($deleted['success'])->toBeTrue();
    expect(AgentDefinition::count())->toBe(0);
});

it('reports unknown agents and invalid actions', function () {
    $tool = manageTool(User::factory()->create());

    $missing = json_decode((string) $tool->handle(new Request(['action' => 'delete', 'key' => 'nope'])), true);
    expect($missing['success'])->toBeFalse();

    $invalid = json_decode((string) $tool->handle(new Request(['action' => 'explode'])), true);
    expect($invalid['error'])->toContain('Acción inválida');
});

it('is exposed to the main agent but not to runtime agents', function () {
    $user = User::factory()->create();

    $mainTools = collect(iterator_to_array((new MegalomaniacAgent($user))->tools()))
        ->map(fn ($tool): string => $tool::class)
        ->all();

    $definition = AgentDefinition::factory()->for($user)->make(['tools_policy' => ['internal' => ['*'], 'integrations' => []]]);
    $runtimeTools = collect(iterator_to_array((new RuntimeAgent($definition))->tools()))
        ->map(fn ($tool): string => $tool::class)
        ->all();

    expect($mainTools)->toContain(ManageAgentsTool::class)
        ->and($runtimeTools)->not->toContain(ManageAgentsTool::class);
});

it('dispatches due agents from the command', function () {
    Queue::fake();

    $user = User::factory()->create();
    $definition = AgentDefinition::factory()->for($user)->due()->create();

    $this->artisan('agents:dispatch-due')->assertSuccessful();

    Queue::assertPushed(fn (RunAgentJob $job) => $job->definitionId === $definition->id);
});
