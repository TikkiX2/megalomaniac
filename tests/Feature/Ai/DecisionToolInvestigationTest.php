<?php

use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Investigation evidence for the "decisions tool" production failures.
 * These tests pin the backend behaviour the UI depends on; the frontend
 * counterpart (cards wiped on any failed resume, live+persisted double
 * render) lives in use-chat-stream.ts / thread.tsx / MessageList.tsx.
 */
function decisionInvestigationPauseSse(array $calls): string
{
    $lines = [];

    foreach ($calls as $index => $call) {
        $lines[] = 'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['tool_calls' => [[
            'index' => $index, 'id' => $call['id'], 'type' => 'function',
            'function' => ['name' => $call['tool'], 'arguments' => $call['arguments']],
        ]]], 'finish_reason' => null]]]);
    }

    $lines[] = 'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => [], 'finish_reason' => 'tool_calls']]]);
    $lines[] = 'data: [DONE]';

    return implode("\n\n", $lines)."\n\n";
}

function decisionInvestigationThread(User $user): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);
}

test('an ask-user pause with one call exposes exactly one pending approval', function () {
    Http::fake(['*' => Http::response(
        decisionInvestigationPauseSse([['id' => 'call_1', 'tool' => 'AskUserTool', 'arguments' => '{"question":"¿A o B?","options":["A","B"]}']]),
        200,
        ['Content-Type' => 'text/event-stream'],
    )]);

    $user = User::factory()->withAiProvider()->create();
    $thread = decisionInvestigationThread($user);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'hola', 'thread_id' => $thread->id])->streamedContent();

    $assistant = $thread->messages()->where('role', 'assistant')->orderByDesc('id')->first();

    expect(collect($assistant->approval_state['pending'] ?? []))->toHaveCount(1)
        ->and($assistant->tool_results)->toBe([]);
});

test('answering only one of two pending questions coalesces the other and continues (was: 422, both pending)', function () {
    Http::fake(['*' => Http::response(
        decisionInvestigationPauseSse([
            ['id' => 'call_1', 'tool' => 'AskUserTool', 'arguments' => '{"question":"¿A?"}'],
            ['id' => 'call_2', 'tool' => 'AskUserTool', 'arguments' => '{"question":"¿B?"}'],
        ]),
        200,
        ['Content-Type' => 'text/event-stream'],
    )]);

    $user = User::factory()->withAiProvider()->create();
    $thread = decisionInvestigationThread($user);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'hola', 'thread_id' => $thread->id])->streamedContent();

    $pending = $thread->messages()->where('role', 'assistant')->orderByDesc('id')->first()->approval_state['pending'];

    expect($pending)->toHaveCount(2);

    // The UI decides exactly one card: the controller coalesces the remaining
    // pending call into a rejected result instead of 422-ing the whole turn,
    // so the continuation request is actually issued.
    $content = $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'reject', 'result' => 'A']]])
        ->streamedContent();

    expect($content)->toContain('"tool_id":"call_2"')
        ->toContain('no respondi');

    expect(Http::recorded())->toHaveCount(2);

    $lastPaused = $thread->messages()->where('role', 'assistant')->whereNotNull('approval_state')->orderByDesc('id')->first();

    expect($lastPaused->approval_state['pending'])->toBe([]);
});

test('a failed resume still consumes the decision and records the tool result (no compensation)', function () {
    Http::fakeSequence()
        ->push(
            decisionInvestigationPauseSse([['id' => 'call_1', 'tool' => 'GymActionTool', 'arguments' => '{"action":"create_workout"}']]),
            200,
            ['Content-Type' => 'text/event-stream'],
        )
        ->push("data: {\"error\":{\"message\":\"boom\"}}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream']);

    $user = User::factory()->withAiProvider()->create();
    $thread = decisionInvestigationThread($user);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'loguea mi workout', 'thread_id' => $thread->id])->streamedContent();

    $content = $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'approve']]])
        ->streamedContent();

    expect($content)->toContain('"type":"error"');

    // Root-cause evidence: the SDK records the decision (and executes the
    // write) BEFORE the continuation stream starts. The paused row shows
    // pending=[] and the executed tool result, so there is no pending card to
    // retry and the turn produced no assistant answer: the decision was eaten
    // by the failed resume. The UI clears its live cards at the start of the
    // request, so the card vanishes and nothing restores it until reload.
    $paused = $thread->messages()->where('role', 'assistant')->whereNotNull('approval_state')->orderByDesc('id')->first();

    expect($paused->approval_state['pending'])->toBe([])
        ->and($paused->tool_results)->not->toBeEmpty()
        ->and($paused->tool_results[0]['id'])->toBe('call_1')
        ->and($paused->content)->toBe('');
});

test('a decision replay after resolution returns the already-resolved mismatch', function () {
    Http::fakeSequence()
        ->push(
            decisionInvestigationPauseSse([['id' => 'call_1', 'tool' => 'GymActionTool', 'arguments' => '{"action":"create_workout"}']]),
            200,
            ['Content-Type' => 'text/event-stream'],
        )
        ->push("data: {\"choices\":[{\"delta\":{\"content\":\"Listo\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream'])
        ->push(
            decisionInvestigationPauseSse([['id' => 'call_1', 'tool' => 'GymActionTool', 'arguments' => '{"action":"create_workout"}']]),
            200,
            ['Content-Type' => 'text/event-stream'],
        );

    $user = User::factory()->withAiProvider()->create();
    $thread = decisionInvestigationThread($user);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'loguea mi workout', 'thread_id' => $thread->id])->streamedContent();

    $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'approve']]])
        ->streamedContent();

    // Same decision again (e.g. a stale card double-clicked after the resume):
    // the SDK reports the call as already-resolved.
    $this->actingAs($user)
        ->postJson(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'approve']]])
        ->assertStatus(422);
});
