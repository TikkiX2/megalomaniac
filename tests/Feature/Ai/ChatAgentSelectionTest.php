<?php

use App\Ai\Agents\RuntimeAgent;
use App\Models\AgentDefinition;
use App\Models\ChatThread;
use App\Models\User;

it('uses the thread agent definition when streaming', function () {
    RuntimeAgent::fake(['Hola desde el agente']);

    $user = User::factory()->withAiProvider()->create();
    $definition = AgentDefinition::factory()->for($user)->create([
        'key' => 'reporte',
        'instructions' => 'Sos el agente de reportes.',
        'tools_policy' => ['internal' => [], 'integrations' => []],
    ]);

    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'agent' => 'reporte',
    ]);

    $response = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Cómo va todo?',
        'thread_id' => $thread->id,
    ]);

    $response->assertOk();

    expect($response->streamedContent())->toContain('"type":"text_delta"');

    RuntimeAgent::assertPrompted(fn ($prompt): bool => true);
    expect($thread->messages()->count())->toBe(2)
        ->and($definition->agentRuns()->count())->toBe(0);
});

it('creates a thread with the selected agent', function () {
    RuntimeAgent::fake(['Ok']);

    $user = User::factory()->withAiProvider()->create();
    AgentDefinition::factory()->for($user)->create(['key' => 'reporte']);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'Hola agente',
        'agent' => 'reporte',
    ])->assertOk();

    expect(ChatThread::query()->forUser($user)->first()->agent)->toBe('reporte');
});

it('falls back to the megalomaniac agent when the key is unknown', function () {
    RuntimeAgent::fake(['Ok']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'agent' => 'no-existe',
    ]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'Hola',
        'thread_id' => $thread->id,
    ])->assertOk();

    RuntimeAgent::assertNeverPrompted();
});
