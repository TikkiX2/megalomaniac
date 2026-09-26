<?php

use App\Http\Resources\ChatMessageResource;
use App\Models\AgentDefinition;
use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use App\Models\ChatThread;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

test('a rejection with an answer resumes the turn and feeds the answer to the model', function () {
    Http::fakeSequence()
        ->push(pausedToolSse('AskUserTool'), 200, ['Content-Type' => 'text/event-stream'])
        ->push("data: {\"choices\":[{\"delta\":{\"content\":\"Gracias por responder\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'hola', 'thread_id' => $thread->id])->streamedContent();

    $content = $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'reject', 'result' => 'mi respuesta']]])
        ->streamedContent();

    expect($content)->toContain('Gracias por responder');

    // A non-blank rejection result means "the user answered": the SDK resumes
    // the loop and sends the answer back as the tool result.
    $requests = Http::recorded();

    expect($requests)->toHaveCount(2);
    expect(json_encode($requests[1][0]->data()))->toContain('mi respuesta');

    $assistant = $thread->messages()->where('role', 'assistant')->orderByDesc('id')->first();
    expect($assistant->content)->toBe('Gracias por responder');
});

test('resuming uses the thread agent instead of the default', function () {
    $user = User::factory()->withAiProvider()->create();
    $definition = AgentDefinition::factory()->for($user)->create([
        'key' => 'reporte',
        'instructions' => 'Sos el agente de reportes unico.',
        'tools_policy' => ['internal' => ['actions'], 'integrations' => []],
    ]);

    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
        'agent' => 'reporte',
    ]);

    Http::fakeSequence()
        ->push(pausedToolSse('ActionTool'), 200, ['Content-Type' => 'text/event-stream'])
        ->push("data: {\"choices\":[{\"delta\":{\"content\":\"Reporte listo\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream']);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'loguea mi workout', 'thread_id' => $thread->id])->streamedContent();

    $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'approve']]])
        ->streamedContent();

    // The resume must keep the thread's runtime agent instructions, not fall
    // back to Megalomaniac's. Both requests carry the agent's instructions, so
    // only the SECOND provider call proves which agent resumed the turn.
    $requests = Http::recorded();

    expect($requests)->toHaveCount(2);

    $secondRequest = json_encode($requests[1][0]->data());

    expect($secondRequest)
        ->toContain('Sos el agente de reportes unico.')
        ->toContain('ActionTool')
        ->not->toContain('TaskQueryTool');
});

function resumeIndexedDocument(ChatThread $thread, User $user, string $content, string $name = 'plan.txt'): ChatAttachment
{
    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'thread_id' => $thread->id,
        'kind' => 'document',
        'status' => 'indexed',
        'original_name' => $name,
    ]);

    ChatDocumentChunk::create([
        'attachment_id' => $attachment->id,
        'position' => 0,
        'content' => $content,
    ]);

    return $attachment;
}

test('a resumed turn keeps the manual tool policy pinned on the thread', function () {
    Http::fakeSequence()
        ->push(pausedToolSse('ActionTool'), 200, ['Content-Type' => 'text/event-stream'])
        ->push("data: {\"choices\":[{\"delta\":{\"content\":\"Listo\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
        'tools_policy' => ['mode' => 'manual', 'groups' => ['tasks', 'actions']],
    ]);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'loguea mi workout', 'thread_id' => $thread->id])->streamedContent();

    $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'approve']]])
        ->streamedContent();

    expect($thread->refresh()->tools_policy)->toBe(['mode' => 'manual', 'groups' => ['tasks', 'actions']]);

    $requests = Http::recorded();

    expect($requests)->toHaveCount(2);

    // The resume must advertise exactly the pinned manual groups (including
    // groups the message itself would not route to), never the whole catalog.
    expect(json_encode($requests[1][0]->data()))
        ->toContain('ActionTool')
        ->toContain('TaskQueryTool')
        ->not->toContain('FinanceQueryTool')
        ->not->toContain('WorkoutQueryTool');
});

test('a resumed turn keeps the thread document context', function () {
    Storage::fake('local');

    Http::fakeSequence()
        ->push(pausedToolSse('ActionTool'), 200, ['Content-Type' => 'text/event-stream'])
        ->push("data: {\"choices\":[{\"delta\":{\"content\":\"Listo\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);
    resumeIndexedDocument($thread, $user, 'El plan de hipertrofia usa press banca 4x8 y sentadilla 5x5.');

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'loguea mi workout de hipertrofia',
        'thread_id' => $thread->id,
    ])->streamedContent();

    $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'approve']]])
        ->streamedContent();

    $requests = Http::recorded();

    expect($requests)->toHaveCount(2);

    // The resume re-queries thread documents with the paused turn's user
    // message, so grounded answers keep their sources.
    expect(json_encode($requests[1][0]->data()))
        ->toContain('Documentos del hilo')
        ->toContain('press banca 4x8');
});
