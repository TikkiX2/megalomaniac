<?php

use App\Jobs\RunAgentJob;
use App\Models\AgentDefinition;
use App\Models\AgentRun;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

it('renders the agents index with own agents and catalog data', function () {
    $user = User::factory()->create();
    AgentDefinition::factory()->for($user)->create(['name' => 'Mi agente']);
    AgentDefinition::factory()->create();

    $this->actingAs($user)->get(route('agents.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('agents/index')
            ->has('agents', 1)
            ->where('agents.0.name', 'Mi agente')
            ->has('scheduleIntervals')
            ->has('internalTools')
            ->has('integrations'));
});

it('stores an agent', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('agents.store'), [
        'name' => 'Monitor de precios',
        'instructions' => 'Avisame si sube algo.',
        'schedule_type' => 'interval',
        'schedule_value' => '6h',
        'tools_policy' => ['internal' => ['grocery_query'], 'integrations' => []],
    ])->assertRedirect(route('agents.index'));

    expect(AgentDefinition::first()->key)->toBe('monitor-de-precios')
        ->and(AgentDefinition::first()->user_id)->toBe($user->id);
});

it('validates the store payload', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('agents.store'), ['name' => '', 'instructions' => '', 'schedule_type' => 'nope', 'schedule_value' => ''])
        ->assertSessionHasErrors(['name', 'instructions', 'schedule_type', 'schedule_value']);
});

it('toggles, runs and deletes own agents', function () {
    Queue::fake();

    $user = User::factory()->create();
    $definition = AgentDefinition::factory()->for($user)->create(['enabled' => true]);

    $this->actingAs($user)->post(route('agents.toggle', $definition))->assertRedirect();
    expect($definition->fresh()->enabled)->toBeFalse();

    $this->actingAs($user)->post(route('agents.run', $definition))->assertRedirect();
    Queue::assertPushed(fn (RunAgentJob $job) => $job->definitionId === $definition->id && $job->triggeredBy === 'manual');

    $this->actingAs($user)->delete(route('agents.destroy', $definition))->assertRedirect(route('agents.index'));
    expect(AgentDefinition::find($definition->id))->toBeNull();
});

it('renders the agent detail with runs', function () {
    $user = User::factory()->create();
    $definition = AgentDefinition::factory()->for($user)->create();
    AgentRun::factory()->create(['agent_definition_id' => $definition->id, 'user_id' => $user->id, 'report' => 'Informe X']);

    $this->actingAs($user)->get(route('agents.show', $definition))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('agents/show')
            ->where('agent.name', $definition->name)
            ->has('runs.data', 1)
            ->where('runs.data.0.report', 'Informe X'));
});

it('404s foreign agents', function () {
    $definition = AgentDefinition::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('agents.show', $definition))->assertNotFound();
    $this->actingAs(User::factory()->create())->delete(route('agents.destroy', $definition))->assertNotFound();
    $this->actingAs(User::factory()->create())->post(route('agents.run', $definition))->assertNotFound();
});
