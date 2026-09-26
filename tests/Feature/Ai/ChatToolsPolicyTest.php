<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Services\ChatService;
use App\Ai\Tools\AskUserTool;
use App\Ai\Tools\TaskQueryTool;
use App\Models\ChatThread;
use App\Models\User;

it('persists a manual tool policy and emits the tools event', function () {
    MegalomaniacAgent::fake(['ok']);

    $user = User::factory()->withAiProvider()->create();

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'Hola',
        'tools_policy' => ['mode' => 'manual', 'groups' => ['tasks']],
    ])->streamedContent();

    expect($content)->toContain('"type":"tools"')
        ->and($content)->toContain('"mode":"manual"')
        ->and($content)->toContain('"groups":["tasks"]');

    $thread = ChatThread::query()->forUser($user)->first();

    expect($thread->tools_policy)->toBe(['mode' => 'manual', 'groups' => ['tasks']]);
});

it('routes automatically when no policy is given', function () {
    MegalomaniacAgent::fake(['ok']);

    $user = User::factory()->withAiProvider()->create();

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Qué tareas tengo pendientes?',
    ])->streamedContent();

    expect($content)->toContain('"mode":"auto"')
        ->and($content)->toContain('"groups":["tasks"]');

    expect(ChatThread::query()->forUser($user)->first()->tools_policy)->toBeNull();
});

it('ignores invalid manual groups and falls back to the router', function () {
    MegalomaniacAgent::fake(['ok']);

    $user = User::factory()->withAiProvider()->create();

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Cuánto gasté este mes?',
        'tools_policy' => ['mode' => 'manual', 'groups' => ['nope']],
    ])->streamedContent();

    expect($content)->toContain('"mode":"auto"')
        ->and($content)->toContain('finance');
});

it('persists the policy through a thread patch', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $this->actingAs($user)->patch(route('ai.chat.update', $thread), [
        'tools_policy' => ['mode' => 'manual', 'groups' => ['finance', 'tasks']],
    ])->assertRedirect();

    expect($thread->fresh()->tools_policy)->toBe(['mode' => 'manual', 'groups' => ['finance', 'tasks']]);
});

it('builds the turn agent with only the resolved groups', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $service = app(ChatService::class);
    $policy = $service->prepareToolPolicy($thread, '', ['mode' => 'manual', 'groups' => ['tasks']]);

    expect($policy['groups'])->toBe(['tasks']);

    $agent = $service->agentFor($user, $thread, $policy['groups']);
    $classes = collect(iterator_to_array($agent->tools()))->map(fn ($tool): string => $tool::class)->all();

    expect($classes)->toBe([TaskQueryTool::class, AskUserTool::class]);
});
