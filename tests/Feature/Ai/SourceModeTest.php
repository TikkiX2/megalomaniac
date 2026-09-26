<?php

use App\Ai\Services\ChatService;
use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use App\Models\ChatThread;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

function sourceModeThread(User $user, ?string $mode = null): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'mode' => $mode,
    ]);
}

function sourceModeDocument(ChatThread $thread, User $user, string $content, string $name = 'plan.txt'): ChatAttachment
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

    $thread->sources()->attach($attachment->id);

    return $attachment;
}

function sourceModeSse(): PromiseInterface
{
    return Http::response(
        "data: {\"choices\":[{\"delta\":{\"content\":\"ok\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n",
        200,
        ['Content-Type' => 'text/event-stream'],
    );
}

/**
 * @return array<int, string>
 */
function sourceModeToolNames(Request $request): array
{
    return collect($request->data()['tools'] ?? [])
        ->pluck('function.name')
        ->filter()
        ->values()
        ->all();
}

test('source mode defaults to both and normalizes invalid values', function () {
    $service = app(ChatService::class);

    expect($service->sourceMode(null))->toBe('both');

    $user = User::factory()->create();
    $thread = sourceModeThread($user);

    expect($service->sourceMode($thread))->toBe('both');

    $thread->mode = 'not-a-mode';

    expect($service->sourceMode($thread))->toBe('both');

    foreach (['web', 'local', 'both', 'off'] as $mode) {
        $thread->mode = $mode;

        expect($service->sourceMode($thread))->toBe($mode);
    }
});

test('local mode removes the web tools and keeps the document context', function () {
    Storage::fake('local');
    $user = User::factory()->withAiProvider()->create();
    $thread = sourceModeThread($user, 'local');
    sourceModeDocument($thread, $user, 'El plan de hipertrofia usa press banca 4x8 y sentadilla 5x5.');

    Http::fake(['*' => sourceModeSse()]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'Busca en internet noticias de hipertrofia y press banca',
        'thread_id' => $thread->id,
    ])->streamedContent();

    Http::assertSent(function (Request $request): bool {
        $tools = sourceModeToolNames($request);

        return ! in_array('WebSearchTool', $tools, true)
            && ! in_array('WebFetchTool', $tools, true)
            && str_contains(json_encode($request->data()), 'Documentos del hilo')
            && str_contains(json_encode($request->data()), 'press banca 4x8');
    });
});

test('web mode keeps the web tools and skips the document context', function () {
    Storage::fake('local');
    $user = User::factory()->withAiProvider()->create();
    $thread = sourceModeThread($user, 'web');
    sourceModeDocument($thread, $user, 'El plan de hipertrofia usa press banca 4x8.');

    Http::fake(['*' => sourceModeSse()]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'Busca en internet noticias de hipertrofia y press banca',
        'thread_id' => $thread->id,
    ])->streamedContent();

    Http::assertSent(function (Request $request): bool {
        $tools = sourceModeToolNames($request);

        return in_array('WebSearchTool', $tools, true)
            && in_array('WebFetchTool', $tools, true)
            && ! str_contains(json_encode($request->data()), 'Documentos del hilo');
    });
});

test('off mode removes the web tools and the document context', function () {
    Storage::fake('local');
    $user = User::factory()->withAiProvider()->create();
    $thread = sourceModeThread($user, 'off');
    sourceModeDocument($thread, $user, 'El plan de hipertrofia usa press banca 4x8.');

    Http::fake(['*' => sourceModeSse()]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'Busca en internet noticias de hipertrofia y press banca',
        'thread_id' => $thread->id,
    ])->streamedContent();

    Http::assertSent(function (Request $request): bool {
        $tools = sourceModeToolNames($request);

        return ! in_array('WebSearchTool', $tools, true)
            && ! in_array('WebFetchTool', $tools, true)
            && ! str_contains(json_encode($request->data()), 'Documentos del hilo');
    });
});

test('both mode lets the router decide when to include the web tools', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = sourceModeThread($user, 'both');

    Http::fake(['*' => sourceModeSse()]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Qué tareas tengo pendientes?',
        'thread_id' => $thread->id,
    ])->streamedContent();

    Http::assertSent(function (Request $request): bool {
        $tools = sourceModeToolNames($request);

        return in_array('TaskQueryTool', $tools, true)
            && ! in_array('WebSearchTool', $tools, true);
    });
});

test('manual policies also honor the thread source mode on resume', function () {
    Storage::fake('local');
    $user = User::factory()->withAiProvider()->create();
    $thread = sourceModeThread($user, 'off');
    $thread->update(['tools_policy' => ['mode' => 'manual', 'groups' => ['web', 'tasks']]]);

    $service = app(ChatService::class);
    $policy = $service->prepareToolPolicy($thread, 'Busca en internet');

    expect($policy['mode'])->toBe('manual')
        ->and($policy['groups'])->toBe(['tasks'])
        ->and($thread->fresh()->tools_policy)->toBe(['mode' => 'manual', 'groups' => ['web', 'tasks']]);

    $thread->update(['mode' => 'both']);

    expect($service->prepareToolPolicy($thread, 'Busca en internet')['groups'])->toBe(['web', 'tasks']);
});

test('a thread patch persists the source mode', function () {
    $user = User::factory()->create();
    $thread = sourceModeThread($user);

    $this->actingAs($user)
        ->patch(route('ai.chat.update', $thread), ['mode' => 'local'])
        ->assertRedirect();

    expect($thread->fresh()->mode)->toBe('local');
});

test('a thread patch rejects an invalid source mode', function () {
    $user = User::factory()->create();
    $thread = sourceModeThread($user);

    $this->actingAs($user)
        ->patch(route('ai.chat.update', $thread), ['mode' => 'nope'])
        ->assertSessionHasErrors('mode');

    expect($thread->fresh()->mode)->toBeNull();
});

test('another user cannot change the source mode', function () {
    $user = User::factory()->create();
    $thread = sourceModeThread(User::factory()->create());

    $this->actingAs($user)
        ->patch(route('ai.chat.update', $thread), ['mode' => 'local'])
        ->assertForbidden();

    expect($thread->fresh()->mode)->toBeNull();
});

test('the thread resource exposes the normalized source mode', function () {
    $user = User::factory()->create();
    $thread = sourceModeThread($user);

    $this->actingAs($user)
        ->get(route('ai.chat.show', $thread))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/thread')
            ->where('thread.mode', 'both')
        );

    $thread->update(['mode' => 'off']);

    $this->actingAs($user)
        ->get(route('ai.chat.show', $thread))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/thread')
            ->where('thread.mode', 'off')
        );
});
