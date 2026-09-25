<?php

use App\Models\AgentDefinition;
use App\Models\AgentRun;
use App\Models\User;
use Illuminate\Database\QueryException;

it('creates definitions with casts and relations', function () {
    $user = User::factory()->create();
    $definition = AgentDefinition::factory()->for($user)->create([
        'tools_policy' => ['internal' => ['workout_query'], 'integrations' => ['github']],
    ]);

    expect($definition->tools_policy)->toBe(['internal' => ['workout_query'], 'integrations' => ['github']])
        ->and($definition->enabled)->toBeTrue()
        ->and($definition->user)->toBeInstanceOf(User::class)
        ->and($definition->max_runs_per_day)->toBe(24);
});

it('enforces unique key per user', function () {
    $user = User::factory()->create();
    AgentDefinition::factory()->for($user)->create(['key' => 'daily-report']);

    AgentDefinition::factory()->for($user)->create(['key' => 'daily-report']);
})->throws(QueryException::class);

it('scopes definitions by user, enabled and due', function () {
    $user = User::factory()->create();
    AgentDefinition::factory()->for($user)->create(['enabled' => true, 'next_run_at' => now()->subMinute()]);
    AgentDefinition::factory()->for($user)->create(['enabled' => false, 'next_run_at' => now()->subMinute()]);
    AgentDefinition::factory()->for($user)->create(['enabled' => true, 'next_run_at' => now()->addHour()]);
    AgentDefinition::factory()->create();

    expect(AgentDefinition::query()->forUser($user)->count())->toBe(3)
        ->and(AgentDefinition::query()->forUser($user)->enabled()->count())->toBe(2)
        ->and(AgentDefinition::query()->forUser($user)->due()->count())->toBe(1);
});

it('creates runs with usage casts and running scope', function () {
    $run = AgentRun::factory()->create([
        'status' => 'running',
        'usage' => ['promptTokens' => 10, 'completionTokens' => 20],
    ]);
    AgentRun::factory()->create(['status' => 'success']);

    expect($run->usage)->toBe(['promptTokens' => 10, 'completionTokens' => 20])
        ->and($run->definition)->toBeInstanceOf(AgentDefinition::class)
        ->and(AgentRun::query()->running()->count())->toBe(1);
});

it('links runs to their definition and user', function () {
    $user = User::factory()->create();
    $definition = AgentDefinition::factory()->for($user)->create();
    $run = AgentRun::factory()->create(['agent_definition_id' => $definition->id, 'user_id' => $user->id]);

    expect($definition->agentRuns()->count())->toBe(1)
        ->and($run->user->id)->toBe($user->id);
});
