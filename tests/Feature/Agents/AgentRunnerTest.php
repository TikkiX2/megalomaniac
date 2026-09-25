<?php

use App\Ai\Agents\AgentRunner;
use App\Ai\Agents\RuntimeAgent;
use App\Ai\Agents\TelegramNotifier;
use App\Ai\Tools\FinanceQueryTool;
use App\Ai\Tools\IntegrationCallTool;
use App\Ai\Tools\IntegrationCatalogTool;
use App\Ai\Tools\WorkoutQueryTool;
use App\Models\AgentDefinition;
use App\Models\AgentRun;
use App\Models\AgentSuggestion;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function aiUser(): User
{
    return User::factory()->create([
        'ai_enabled' => true,
        'ai_provider_url' => 'http://ai.test/v1',
        'ai_provider_key' => 'test-key',
        'ai_model' => 'test-model',
    ]);
}

it('runs successfully and stores report, suggestions and notification', function () {
    RuntimeAgent::fake([[
        'report' => "# Informe\nTodo en orden",
        'suggestions' => json_encode([['title' => 'Ahorrá', 'content' => 'Menos café']]),
        'notify' => 'Informe listo',
    ]]);

    $user = aiUser();
    Connection::factory()->for($user)->create([
        'kind' => 'telegram',
        'base_url' => 'https://api.telegram.org',
        'credentials' => ['token' => 'bot'],
        'options' => ['chat_id' => '123'],
    ]);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $definition = AgentDefinition::factory()->for($user)->create(['key' => 'reporte']);

    $run = app(AgentRunner::class)->run($definition, 'manual');

    expect($run->status)->toBe(AgentRun::STATUS_SUCCESS)
        ->and($run->report)->toContain('Informe')
        ->and($run->suggestions_created)->toBe(1)
        ->and($run->notified_at)->not->toBeNull()
        ->and($run->usage)->toBeArray()
        ->and(AgentSuggestion::where('type', 'agent:reporte')->count())->toBe(1);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
});

it('parses a json text response from the provider', function () {
    RuntimeAgent::fake(['{"report":"# JSON","suggestions":"[]","notify":""}']);

    $definition = AgentDefinition::factory()->for(aiUser())->create();

    $run = app(AgentRunner::class)->run($definition, 'manual');

    expect($run->status)->toBe(AgentRun::STATUS_SUCCESS)
        ->and($run->report)->toBe('# JSON')
        ->and($run->suggestions_created)->toBe(0);
});

it('does not notify when there is no telegram connection', function () {
    RuntimeAgent::fake([[
        'report' => 'Informe',
        'suggestions' => '',
        'notify' => 'Aviso',
    ]]);
    Http::fake();

    $definition = AgentDefinition::factory()->for(aiUser())->create();

    $run = app(AgentRunner::class)->run($definition, 'manual');

    expect($run->status)->toBe(AgentRun::STATUS_SUCCESS)
        ->and($run->notified_at)->toBeNull();

    Http::assertNothingSent();
});

it('skips when ai is not configured', function () {
    $definition = AgentDefinition::factory()->for(User::factory())->create();

    $run = app(AgentRunner::class)->run($definition, 'schedule');

    expect($run->status)->toBe(AgentRun::STATUS_SKIPPED)
        ->and($run->error)->toContain('IA no configurada');
});

it('skips when already running or over budget', function () {
    $user = aiUser();

    $running = AgentDefinition::factory()->for($user)->create();
    AgentRun::factory()->create(['agent_definition_id' => $running->id, 'user_id' => $user->id, 'status' => AgentRun::STATUS_RUNNING]);

    expect(app(AgentRunner::class)->run($running, 'manual')->status)->toBe(AgentRun::STATUS_SKIPPED);

    $budget = AgentDefinition::factory()->for($user)->create(['max_runs_per_day' => 1]);
    AgentRun::factory()->create(['agent_definition_id' => $budget->id, 'user_id' => $user->id, 'status' => AgentRun::STATUS_SUCCESS]);

    $run = app(AgentRunner::class)->run($budget, 'manual');

    expect($run->status)->toBe(AgentRun::STATUS_SKIPPED)
        ->and($run->error)->toContain('Presupuesto');
});

it('fails, counts the failure and backs off', function () {
    RuntimeAgent::fake(fn () => throw new RuntimeException('boom'));

    $definition = AgentDefinition::factory()->for(aiUser())->create(['schedule_type' => 'interval', 'schedule_value' => '1h']);

    $run = app(AgentRunner::class)->run($definition, 'schedule');

    expect($run->status)->toBe(AgentRun::STATUS_FAILED)
        ->and($run->error)->toContain('boom')
        ->and($definition->fresh()->failure_count)->toBe(1)
        ->and($definition->fresh()->next_run_at)->not->toBeNull()
        ->and(now()->diffInMinutes($definition->fresh()->next_run_at))->toBeGreaterThanOrEqual(1);
});

it('filters tools by the definition policy', function () {
    $user = User::factory()->create();
    $definition = AgentDefinition::factory()->for($user)->make([
        'tools_policy' => ['internal' => ['finance_query'], 'integrations' => ['github']],
    ]);

    $classes = collect(iterator_to_array((new RuntimeAgent($definition))->tools()))
        ->map(fn ($tool): string => $tool::class)
        ->all();

    expect($classes)->toContain(FinanceQueryTool::class, IntegrationCatalogTool::class, IntegrationCallTool::class)
        ->not->toContain(WorkoutQueryTool::class);
});

it('sends telegram notifications only when a connection exists', function () {
    $user = User::factory()->create();

    expect(app(TelegramNotifier::class)->send($user, 'hola'))->toBeFalse();

    Connection::factory()->for($user)->create([
        'kind' => 'telegram',
        'base_url' => 'https://api.telegram.org',
        'credentials' => ['token' => 'bot'],
        'options' => ['chat_id' => '1'],
    ]);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    expect(app(TelegramNotifier::class)->send($user, 'hola'))->toBeTrue();
});
