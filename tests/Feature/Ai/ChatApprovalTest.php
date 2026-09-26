<?php

use App\Http\Resources\ChatMessageResource;
use App\Models\ChatThread;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function pausedToolSse(string $tool): string
{
    return implode("\n\n", [
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['tool_calls' => [[
            'index' => 0, 'id' => 'call_1', 'type' => 'function',
            'function' => ['name' => $tool, 'arguments' => '{"action":"create_workout","started_at":"2026-09-25T10:00:00Z"}'],
        ]]], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => [], 'finish_reason' => 'tool_calls']]]),
        'data: [DONE]',
    ])."\n\n";
}

test('write tools pause the turn with a tool approval request', function () {
    Http::fake(['*' => Http::response(pausedToolSse('ActionTool'), 200, ['Content-Type' => 'text/event-stream'])]);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $content = $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => 'loguea mi workout', 'thread_id' => $thread->id])
        ->streamedContent();

    expect($content)->toContain('tool_approval_request')->toContain('ActionTool')->toContain('[DONE]');

    $assistant = $thread->messages()->where('role', 'assistant')->orderByDesc('id')->first();
    expect($assistant->approval_state['pending'])->toHaveKey('call_1');

    $resource = (new ChatMessageResource($assistant->refresh()))->resolve(request());
    expect($resource['pending_approvals'])->toHaveCount(1);
    expect($resource['pending_approvals'][0]['kind'])->toBe('approval');
    expect($resource['pending_approvals'][0]['tool'])->toBe('ActionTool');
});

test('approving resumes the run and executes the tool', function () {
    Http::fakeSequence()
        ->push(pausedToolSse('ActionTool'), 200, ['Content-Type' => 'text/event-stream'])
        ->push("data: {\"choices\":[{\"delta\":{\"content\":\"Listo\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'loguea mi workout', 'thread_id' => $thread->id])->streamedContent();

    $content = $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'approve']]])
        ->streamedContent();

    expect($content)->toContain('Listo');

    $assistant = $thread->messages()->where('role', 'assistant')->orderByDesc('id')->first();
    expect($assistant->approval_state)->toBeNull();

    // The SDK executes the approved call locally and durably records the result
    // on the paused turn; the resumed assistant row dedupes that same result.
    $paused = $thread->messages()
        ->where('role', 'assistant')
        ->whereNotNull('approval_state')
        ->orderByDesc('id')
        ->first();

    expect($paused->tool_results)->not->toBeEmpty();
    expect($paused->tool_results[0]['id'])->toBe('call_1');
    expect($paused->approval_state['pending'])->toBe([]);

    expect(Workout::where('user_id', $user->id)->count())->toBe(1);
});

test('rejecting records the denial without a follow-up completion', function () {
    Http::fakeSequence()
        ->push(pausedToolSse('ActionTool'), 200, ['Content-Type' => 'text/event-stream'])
        ->push("data: {\"choices\":[{\"delta\":{\"content\":\"Vale, no lo hago\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'loguea mi workout', 'thread_id' => $thread->id])->streamedContent();

    $content = $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'reject']]])
        ->streamedContent();

    // A bare rejection is final for the SDK: the denial streams as a denied
    // tool result and no second completion is requested.
    expect($content)
        ->toContain('"type":"tool_result"')
        ->toContain('"denied":true')
        ->toContain('The user rejected this tool call.')
        ->not->toContain('no lo hago');

    Http::assertSentCount(1);

    $assistant = $thread->messages()->where('role', 'assistant')->orderByDesc('id')->first();
    expect($assistant->tool_results)->not->toBeEmpty();
    expect($assistant->tool_results[0]['denied'])->toBeTrue();
    expect($assistant->approval_state['pending'])->toBe([]);
});

test('ask user answers travel back as a rejected tool result', function () {
    Http::fake(['*' => Http::response(pausedToolSse('AskUserTool'), 200, ['Content-Type' => 'text/event-stream'])]);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $content = $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => 'hola', 'thread_id' => $thread->id])
        ->streamedContent();

    expect($content)->toContain('tool_approval_request')->toContain('AskUserTool');
});

test('a decision that does not match the pending call returns 422', function () {
    Http::fake(['*' => Http::response(pausedToolSse('ActionTool'), 200, ['Content-Type' => 'text/event-stream'])]);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'loguea mi workout', 'thread_id' => $thread->id])->streamedContent();

    $this->actingAs($user)
        ->postJson(route('ai.chat.approve', $thread), ['decisions' => ['call_unknown' => ['action' => 'approve']]])
        ->assertStatus(422)
        ->assertJson(['message' => 'Approval decisions do not match the pending tool calls.']);
});

test('another user cannot approve a thread', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create();

    $this->actingAs($user)
        ->postJson(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'approve']]])
        ->assertForbidden();
});
