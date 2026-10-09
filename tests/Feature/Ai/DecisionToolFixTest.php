<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Agents\RuntimeAgent;
use App\Ai\Tools\AskUserTool;
use App\Models\AgentDefinition;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Regression tests for the "decisions tool" production failures.
 *
 * Contract pinned here:
 * 1. A resume that fails before the continuation produced anything restores
 *    the pending decisions, so the cards return and the turn is retryable.
 * 2. An executed write on a failed resume is kept (no double execution); the
 *    decision is NOT restored for it.
 * 3. Partial decisions (one of several pending) are coalesced server-side
 *    instead of 422-ing the whole turn: unanswered pendings become rejected
 *    tool results with a placeholder and the turn continues — except on mixed
 *    pauses (a question plus a write approval in the same block), where
 *    settling one side alone would destroy the other. A partial mixed payload
 *    is rejected (422) so the UI stages every card and resumes with the full
 *    set; a combined mixed payload executes the write and delivers the answer.
 * 4. Custom-agent threads can ask questions (AskUserTool is always available).
 */
function decisionFixPauseSse(array $calls): string
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

function decisionFixAnswerSse(string $text): string
{
    return "data: {\"choices\":[{\"delta\":{\"content\":\"{$text}\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n";
}

function decisionFixThread(User $user): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);
}

function decisionFixPausedRow(ChatThread $thread): ChatMessage
{
    return $thread->messages()->where('role', 'assistant')->whereNotNull('approval_state')->orderByDesc('id')->first();
}

test('a failed resume restores side-effect-free pending decisions so the turn can be retried', function () {
    Http::fakeSequence()
        ->push(decisionFixPauseSse([['id' => 'call_1', 'tool' => 'AskUserTool', 'arguments' => '{"question":"¿Presupuesto?"}']]), 200, ['Content-Type' => 'text/event-stream'])
        ->push("data: {\"error\":{\"message\":\"boom\"}}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream'])
        ->push(decisionFixAnswerSse('Gracias por responder'), 200, ['Content-Type' => 'text/event-stream']);

    $user = User::factory()->withAiProvider()->create();
    $thread = decisionFixThread($user);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'hola', 'thread_id' => $thread->id])->streamedContent();

    $before = decisionFixPausedRow($thread)->refresh();

    // First attempt: the continuation provider dies mid-resume (the exact
    // prod failure mode). The decision must be restored, not eaten.
    $content = $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'reject', 'result' => '1000']]])
        ->streamedContent();

    expect($content)->toContain('"type":"error"');

    $after = decisionFixPausedRow($thread)->refresh();

    expect($after->approval_state)->toBe($before->approval_state)
        ->and($after->tool_results)->toBe($before->tool_results)
        ->and($after->approval_state['pending'])->toHaveKey('call_1');

    // Second attempt with a healthy provider: the answer travels and the
    // turn completes.
    $retry = $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'reject', 'result' => '1000']]])
        ->streamedContent();

    expect($retry)->toContain('Gracias por responder');

    $requests = Http::recorded();
    expect($requests)->toHaveCount(3);
    expect(json_encode($requests[2][0]->data()))->toContain('1000');
});

test('an executed write on a failed resume is kept and never double-executed', function () {
    Http::fakeSequence()
        ->push(decisionFixPauseSse([['id' => 'call_1', 'tool' => 'GymActionTool', 'arguments' => '{"action":"create_workout"}']]), 200, ['Content-Type' => 'text/event-stream'])
        ->push("data: {\"error\":{\"message\":\"boom\"}}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream']);

    $user = User::factory()->withAiProvider()->create();
    $thread = decisionFixThread($user);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'loguea mi workout', 'thread_id' => $thread->id])->streamedContent();

    $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'approve']]])
        ->streamedContent();

    // The write ran exactly once; the consumed decision stays consumed so a
    // retry cannot duplicate it; history keeps the tool result for the next
    // message.
    expect(Workout::where('user_id', $user->id)->count())->toBe(1);

    $paused = decisionFixPausedRow($thread)->refresh();

    expect($paused->approval_state['pending'])->toBe([])
        ->and($paused->tool_results)->toHaveCount(1)
        ->and($paused->tool_results[0]['id'])->toBe('call_1');
});

test('answering one of several pending questions coalesces the others and continues the turn', function () {
    Http::fakeSequence()
        ->push(decisionFixPauseSse([
            ['id' => 'call_1', 'tool' => 'AskUserTool', 'arguments' => '{"question":"¿A?"}'],
            ['id' => 'call_2', 'tool' => 'AskUserTool', 'arguments' => '{"question":"¿B?"}'],
        ]), 200, ['Content-Type' => 'text/event-stream'])
        ->push(decisionFixAnswerSse('Sigo con A'), 200, ['Content-Type' => 'text/event-stream']);

    $user = User::factory()->withAiProvider()->create();
    $thread = decisionFixThread($user);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'hola', 'thread_id' => $thread->id])->streamedContent();

    expect(decisionFixPausedRow($thread)->approval_state['pending'])->toHaveCount(2);

    // Deciding a single card must not 422: the unanswered pendings are
    // coalesced into rejected results with a placeholder and the turn goes on.
    $content = $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'reject', 'result' => 'A']]])
        ->streamedContent();

    expect($content)->toContain('Sigo con A');

    $requests = Http::recorded();
    expect($requests)->toHaveCount(2);

    $body = json_encode($requests[1][0]->data(), JSON_UNESCAPED_UNICODE);

    expect($body)->toContain('no respondió');

    $paused = decisionFixPausedRow($thread)->refresh();

    expect($paused->approval_state['pending'])->toBe([])
        ->and($paused->tool_results)->toHaveCount(2);
});

test('a partial decision on a mixed pause is rejected so both cards are decided together', function () {
    Http::fake(['*' => Http::response(decisionFixPauseSse([
        ['id' => 'call_q', 'tool' => 'AskUserTool', 'arguments' => '{"question":"¿Qué presupuesto?"}'],
        ['id' => 'call_w', 'tool' => 'GymActionTool', 'arguments' => '{"action":"create_workout"}'],
    ]), 200, ['Content-Type' => 'text/event-stream'])]);

    $user = User::factory()->withAiProvider()->create();
    $thread = decisionFixThread($user);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'hola', 'thread_id' => $thread->id])->streamedContent();

    expect(decisionFixPausedRow($thread)->approval_state['pending'])->toHaveCount(2);

    // Answering only the question must not auto-deny the write the user
    // never denied.
    $this->actingAs($user)
        ->postJson(route('ai.chat.approve', $thread), ['decisions' => ['call_q' => ['action' => 'reject', 'result' => '1000']]])
        ->assertStatus(422);

    // Approving only the write must not kill the question and lose the answer.
    $this->actingAs($user)
        ->postJson(route('ai.chat.approve', $thread), ['decisions' => ['call_w' => ['action' => 'approve']]])
        ->assertStatus(422);

    // Nothing was consumed or executed: both calls are still pending and no
    // continuation was ever issued.
    expect(decisionFixPausedRow($thread)->refresh()->approval_state['pending'])
        ->toHaveKeys(['call_q', 'call_w'])
        ->and(Workout::where('user_id', $user->id)->count())->toBe(0)
        ->and(Http::recorded())->toHaveCount(1);
});

test('a combined decision on a mixed pause executes the write and delivers the answer', function () {
    Http::fakeSequence()
        ->push(decisionFixPauseSse([
            ['id' => 'call_q', 'tool' => 'AskUserTool', 'arguments' => '{"question":"¿Qué presupuesto?"}'],
            ['id' => 'call_w', 'tool' => 'GymActionTool', 'arguments' => '{"action":"create_workout"}'],
        ]), 200, ['Content-Type' => 'text/event-stream'])
        ->push(decisionFixAnswerSse('Anotado con 1000'), 200, ['Content-Type' => 'text/event-stream']);

    $user = User::factory()->withAiProvider()->create();
    $thread = decisionFixThread($user);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'hola', 'thread_id' => $thread->id])->streamedContent();

    $content = $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => [
            'call_q' => ['action' => 'reject', 'result' => '1000'],
            'call_w' => ['action' => 'approve'],
        ]])
        ->streamedContent();

    expect($content)->toContain('Anotado con 1000');

    $requests = Http::recorded();
    expect($requests)->toHaveCount(2);

    $body = json_encode($requests[1][0]->data(), JSON_UNESCAPED_UNICODE);

    expect($body)->toContain('1000')
        ->and(Workout::where('user_id', $user->id)->count())->toBe(1)
        ->and(decisionFixPausedRow($thread)->refresh()->approval_state['pending'])->toBe([]);
});

test('unknown decision ids still reject the resume', function () {
    Http::fake(['*' => Http::response(decisionFixPauseSse([
        ['id' => 'call_1', 'tool' => 'AskUserTool', 'arguments' => '{"question":"¿A?"}'],
    ]), 200, ['Content-Type' => 'text/event-stream'])]);

    $user = User::factory()->withAiProvider()->create();
    $thread = decisionFixThread($user);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'hola', 'thread_id' => $thread->id])->streamedContent();

    $this->actingAs($user)
        ->postJson(route('ai.chat.approve', $thread), ['decisions' => ['call_unknown' => ['action' => 'approve']]])
        ->assertStatus(422);
});

test('custom-agent threads can ask questions', function () {
    $user = User::factory()->create();
    $definition = AgentDefinition::factory()->for($user)->create([
        'key' => 'reporte',
        'tools_policy' => ['internal' => ['tasks_query'], 'integrations' => []],
    ]);

    $classes = collect(iterator_to_array((new RuntimeAgent($definition))->tools()))
        ->map(fn ($tool): string => $tool::class)
        ->all();

    expect($classes)->toContain(AskUserTool::class);
});

test('the main agent instructs one question per turn', function () {
    $user = User::factory()->create();

    expect((new MegalomaniacAgent($user))->instructions())->toContain('una sola pregunta');
});

/*
 * Frontend guards. The repo has no JS test runner, so — like the SelectGroup
 * guard in AiSettingsPageTest — these pin the decisions contract structurally:
 * the cards must survive a failed resume and must never render twice.
 */

test('the stream hook keeps the decision cards across a failed resume', function () {
    $source = file_get_contents(resource_path('js/hooks/use-chat-stream.ts'));

    // Clearing the cards is gated: a resume (`retainApprovals`) keeps them.
    expect($source)->toContain('applyApprovals(retainApprovals ? snapshot : [])')
        ->toContain('restoreApprovalsRef.current = retainApprovals ? snapshot : null;');

    // Every failure path puts the snapshot back instead of leaving the UI
    // with an empty approvals list (the "no aparece la card" bug).
    expect($source)->toMatch('/if \(erroredRef\.current\) \{[^}]*restoreApprovals\(\);/s')
        ->toMatch("/caught\.name === 'AbortError'[\s\S]{0,200}restoreApprovals\(\);/")
        ->toMatch('/caught instanceof Error[\s\S]*?restoreApprovals\(\);/');
});

test('the thread page resumes decisions without wiping their cards', function () {
    $source = file_get_contents(resource_path('js/pages/ai/thread.tsx'));

    // decide()/approveAll() resume with `retainApprovals` (at most once per path).
    expect(substr_count($source, 'retainApprovals: true'))->toBe(4);

    // The decided card is hidden while the resume runs and comes back if the
    // request errors (the hook restores the snapshot on error).
    expect($source)->toContain('hiddenApprovalIds={submittedDecisions}')
        ->toMatch('/onError: \(message, recoverable\) => \{[\s\S]{0,400}clearDecisionState\(\);/');

    // A pause reloads the persisted cards, and MessageList dedupes them.
    expect($source)->toMatch('/onPaused: \(\) => \{[^}]*only: \[\'messages\'\]/s');
});

test('a mixed pause stages every card and resumes with the full set', function () {
    $source = file_get_contents(resource_path('js/pages/ai/thread.tsx'));

    // A partial mixed resume would 422 (and settle nothing), so decisions are
    // staged until every pending id has one, then sent together.
    expect($source)->toContain('mixedPause')
        ->toContain('stagedDecisions')
        ->toContain('allPendingIds.every((pendingId) => pendingId in next)')
        ->toContain('Decisión guardada: decidí las tarjetas restantes');

    $controller = file_get_contents(base_path('app/Http/Controllers/Ai/ChatController.php'));

    expect($controller)->toContain('respondé la pregunta y decidí la acción juntas');
});

test('the message list renders each pending decision exactly once', function () {
    $source = file_get_contents(resource_path('js/components/ai/chat/MessageList.tsx'));

    // Persisted cards already shown by the live stream are dropped by id, so
    // the reload-after-pause race cannot paint the same card twice.
    expect($source)->toContain('new Set(liveApprovals.map(')
        ->toContain('!liveIds.has(approval.id)')
        ->toContain('!hiddenIds.has(approval.id)')
        ->toContain('pendingApprovals={visibleLiveApprovals}');
});
