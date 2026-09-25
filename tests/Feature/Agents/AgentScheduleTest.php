<?php

use App\Ai\Agents\AgentDefinitionService;
use App\Ai\Agents\AgentScheduler;
use App\Jobs\RunAgentJob;
use App\Models\AgentDefinition;
use App\Models\AgentRun;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

it('validates schedules', function () {
    $service = app(AgentDefinitionService::class);

    $service->validateSchedule('interval', '1h');
    $service->validateSchedule('cron', '0 8 * * *');

    expect(fn () => $service->validateSchedule('interval', '2h'))->toThrow(ValidationException::class)
        ->and(fn () => $service->validateSchedule('cron', 'nope'))->toThrow(ValidationException::class)
        ->and(fn () => $service->validateSchedule('weird', '1h'))->toThrow(ValidationException::class);
});

it('creates a definition with a slug key and next run', function () {
    $user = User::factory()->create();

    $definition = app(AgentDefinitionService::class)->create($user, [
        'name' => 'Reporte Diario',
        'instructions' => 'Resumí mis finanzas.',
        'schedule_type' => 'interval',
        'schedule_value' => '6h',
        'tools_policy' => ['internal' => ['finance_query'], 'integrations' => []],
    ]);

    expect($definition->key)->toBe('reporte-diario')
        ->and($definition->user_id)->toBe($user->id)
        ->and($definition->next_run_at)->not->toBeNull()
        ->and(now()->diffInMinutes($definition->next_run_at))->toBeGreaterThan(350);
});

it('rejects duplicate keys and the eleventh agent', function () {
    $user = User::factory()->create();
    $service = app(AgentDefinitionService::class);

    $service->create($user, ['name' => 'Uno', 'instructions' => 'x']);
    expect(fn () => $service->create($user, ['name' => 'Uno', 'instructions' => 'x']))
        ->toThrow(ValidationException::class);

    for ($i = 0; $i < 9; $i++) {
        $service->create($user, ['name' => "Agente {$i}", 'instructions' => 'x']);
    }

    expect(fn () => $service->create($user, ['name' => 'Once', 'instructions' => 'x']))
        ->toThrow(ValidationException::class);
});

it('computes next run for intervals and cron', function () {
    $scheduler = app(AgentScheduler::class);

    $this->travelTo(now()->setTime(7, 0));

    $interval = AgentDefinition::factory()->make(['schedule_type' => 'interval', 'schedule_value' => '30m']);
    expect(now()->diffInMinutes($scheduler->nextRunAt($interval)))->toEqual(30);

    $cron = AgentDefinition::factory()->make(['schedule_type' => 'cron', 'schedule_value' => '0 8 * * *', 'timezone' => 'UTC']);
    expect($scheduler->nextRunAt($cron)->format('H:i'))->toBe('08:00');
});

it('dispatches due agents and skips disabled, running and over-budget ones', function () {
    Queue::fake();

    $user = User::factory()->create();

    $due = AgentDefinition::factory()->for($user)->due()->create();
    AgentDefinition::factory()->for($user)->due()->create(['enabled' => false]);

    $running = AgentDefinition::factory()->for($user)->due()->create();
    AgentRun::factory()->create(['agent_definition_id' => $running->id, 'user_id' => $user->id, 'status' => 'running']);

    $overBudget = AgentDefinition::factory()->for($user)->due()->create(['max_runs_per_day' => 1]);
    AgentRun::factory()->create(['agent_definition_id' => $overBudget->id, 'user_id' => $user->id, 'status' => 'success']);

    $count = app(AgentScheduler::class)->dispatchDue();

    expect($count)->toBe(1)
        ->and($due->fresh()->next_run_at->isFuture())->toBeTrue();

    Queue::assertPushed(RunAgentJob::class, 1);
    Queue::assertPushed(fn (RunAgentJob $job) => $job->definitionId === $due->id);
});

it('toggles and updates schedules', function () {
    $user = User::factory()->create();
    $service = app(AgentDefinitionService::class);
    $definition = $service->create($user, ['name' => 'Toggle', 'instructions' => 'x']);

    $service->toggle($definition, false);
    expect($definition->fresh()->enabled)->toBeFalse()
        ->and($definition->fresh()->next_run_at)->toBeNull();

    $service->toggle($definition, true);
    expect($definition->fresh()->enabled)->toBeTrue()
        ->and($definition->fresh()->next_run_at)->not->toBeNull();

    $service->update($definition, ['schedule_type' => 'interval', 'schedule_value' => '15m']);
    expect(now()->diffInMinutes($definition->fresh()->next_run_at))->toBeLessThanOrEqual(15);
});
