<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('sending a message creates a thread and streams the reply', function () {
    MegalomaniacAgent::fake(['Hola mundo']);

    $user = User::factory()->withAiProvider()->create();

    $response = $this->actingAs($user)->post(route('ai.chat.send'), ['message' => '¿Qué tal?']);

    $response->assertOk();
    $content = $response->streamedContent();

    $deltaText = collect(explode("\n", $content))
        ->filter(fn (string $line): bool => str_starts_with($line, 'data: {"'))
        ->map(fn (string $line): array => json_decode(substr($line, 6), true))
        ->filter(fn (array $event): bool => ($event['type'] ?? null) === 'text_delta')
        ->pluck('delta')
        ->implode('');

    expect($content)->toContain('"type":"thread"');
    expect($content)->toContain('"type":"text_delta"');
    expect($deltaText)->toBe('Hola mundo');
    expect($content)->toContain('[DONE]');

    $thread = ChatThread::query()->forUser($user)->first();

    expect($thread)->not->toBeNull();
    expect($thread->title)->toBe('¿Qué tal?');
    expect($thread->agent)->toBe('megalomaniac');
    expect($thread->messages()->count())->toBe(2);
});

test('sending to an existing thread continues it and stores the model override', function () {
    MegalomaniacAgent::fake(['Seguimos']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $response = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'Continúa',
        'thread_id' => $thread->id,
        'model' => 'model-elegido',
    ]);

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain('"threadId":"'.$thread->id.'"');
    expect($thread->refresh()->model)->toBe('model-elegido');
    expect($thread->messages()->count())->toBe(2);
    expect($thread->fresh()->title)->toBe($thread->title);
});

test('sending to another users thread returns 404', function () {
    MegalomaniacAgent::fake(['No debería']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create();

    $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => 'Hola', 'thread_id' => $thread->id])
        ->assertNotFound();

    MegalomaniacAgent::assertNeverPrompted();
});

test('sending without configured provider returns 422 json', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), ['message' => 'Hola'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Configura tu proveedor de IA en Settings → IA.']);

    expect(ChatThread::query()->count())->toBe(0);
});

test('insufficient credits failures surface an actionable message', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Insufficient Balance']], 402)]);

    $user = User::factory()->withAiProvider()->create();

    $response = $this->actingAs($user)->post(route('ai.chat.send'), ['message' => '¿Qué tal?']);

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)
        ->toContain('"type":"thread"')
        ->toContain('"type":"error"')
        ->toContain('"recoverable":false')
        ->toContain('saldo o cuota')
        ->toContain('[DONE]');
});

test('rate limited failures surface a wait message', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Too many requests']], 429)]);

    $user = User::factory()->withAiProvider()->create();

    $content = $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => '¿Qué tal?'])
        ->streamedContent();

    expect($content)->toContain('limitando las peticiones')->toContain('[DONE]');
});

test('rejected api keys surface a settings hint', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Unauthorized']], 401)]);

    $user = User::factory()->withAiProvider()->create();

    $content = $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => '¿Qué tal?'])
        ->streamedContent();

    expect($content)->toContain('API key')->toContain('[DONE]');
});

test('bad request failures point to the model setting', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'weird failure']], 400)]);

    $user = User::factory()->withAiProvider()->create();

    $content = $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => '¿Qué tal?'])
        ->streamedContent();

    expect($content)->toContain('rechaz')->toContain('[DONE]');
});

test('unknown provider failures keep the generic message', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'weird failure']], 418)]);

    $user = User::factory()->withAiProvider()->create();

    $content = $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => '¿Qué tal?'])
        ->streamedContent();

    expect($content)
        ->toContain('"type":"error"')
        ->toContain('La generaci')
        ->toContain('[DONE]');
});

test('reasoning deltas are persisted on the assistant message meta', function () {
    $sse = implode("\n\n", [
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['reasoning_content' => 'Analizo'], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['content' => 'Listo'], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => [], 'finish_reason' => 'stop']]]),
        'data: [DONE]',
    ])."\n\n";

    Http::fake(['*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

    $user = User::factory()->withAiProvider()->create();

    $response = $this->actingAs($user)->post(route('ai.chat.send'), ['message' => '¿Qué tal?']);
    $content = $response->streamedContent();

    expect($content)->toContain('reasoning_delta');

    $thread = ChatThread::query()->forUser($user)->first();
    $assistant = $thread->messages()->orderByDesc('id')->first();

    expect($assistant->meta['reasoning']['text'])->toBe('Analizo');
    expect($assistant->meta['reasoning'])->toHaveKey('duration_ms');
});

test('a failed turn does not overwrite the previous assistant reasoning', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
    $previous = ChatMessage::factory()->assistant()->create([
        'conversation_id' => $thread->id,
        'meta' => ['reasoning' => ['text' => 'razonamiento previo', 'duration_ms' => 123]],
    ]);

    $sse = implode("\n\n", [
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['reasoning_content' => 'parcial'], 'finish_reason' => null]]]),
        'data: '.json_encode(['error' => ['code' => 'server_error', 'message' => 'boom']]),
        'data: [DONE]',
    ])."\n\n";
    Http::fake(['*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'algo',
        'thread_id' => $thread->id,
    ])->streamedContent();

    expect($content)->toContain('reasoning_delta')->toContain('"type":"error"');

    expect($previous->refresh()->meta['reasoning']['text'])->toBe('razonamiento previo');
    expect($thread->messages()->where('role', 'assistant')->count())->toBe(1);
});

test('message is required and limited', function () {
    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), ['message' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), ['message' => str_repeat('a', 4001)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');
});

test('an image attachment is sent to the provider and linked to the user message', function () {
    Storage::fake('local');
    $user = User::factory()->withAiProvider()->create();
    $path = 'ai-attachments/'.$user->id.'/foto.png';
    Storage::disk('local')->put($path, 'fake-image-bytes');
    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'status' => 'ready',
        'path' => $path,
        'mime' => 'image/png',
        'original_name' => 'foto.png',
    ]);

    Http::fake(['*' => Http::response("data: {\"choices\":[{\"delta\":{\"content\":\"ok\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream'])]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Qué ves?',
        'attachment_ids' => [$attachment->id],
    ])->streamedContent();

    Http::assertSent(function ($request): bool {
        return str_contains(json_encode($request->data()), 'image_url');
    });

    expect($attachment->refresh()->message_id)->not->toBeNull();
});

test('only own images can be attached', function () {
    Http::fake();
    $user = User::factory()->withAiProvider()->create();
    $foreign = ChatAttachment::factory()->create(['kind' => 'image']);

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), ['message' => 'x', 'attachment_ids' => [$foreign->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attachment_ids.0');
});

test('a non ready image cannot be attached', function () {
    Http::fake();
    $user = User::factory()->withAiProvider()->create();
    $pending = ChatAttachment::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), ['message' => 'x', 'attachment_ids' => [$pending->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attachment_ids.0');
});

test('a failed turn leaves the attachment unlinked', function () {
    Storage::fake('local');
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
    ChatMessage::factory()->create([
        'conversation_id' => $thread->id,
        'role' => 'user',
        'content' => 'mensaje previo',
    ]);

    $path = 'ai-attachments/'.$user->id.'/fallo.png';
    Storage::disk('local')->put($path, 'fake-image-bytes');
    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'status' => 'ready',
        'path' => $path,
        'mime' => 'image/png',
        'original_name' => 'fallo.png',
    ]);

    $sse = implode("\n\n", [
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['content' => 'parcial'], 'finish_reason' => null]]]),
        'data: '.json_encode(['error' => ['code' => 'server_error', 'message' => 'boom']]),
        'data: [DONE]',
    ])."\n\n";
    Http::fake(['*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'algo',
        'thread_id' => $thread->id,
        'attachment_ids' => [$attachment->id],
    ])->streamedContent();

    expect($content)->toContain('"type":"error"');
    expect($attachment->refresh()->message_id)->toBeNull();
    expect($thread->messages()->where('role', 'user')->count())->toBe(1);
});

test('the thread payload exposes the attachments linked to messages', function () {
    Storage::fake('local');
    $user = User::factory()->withAiProvider()->create();
    $path = 'ai-attachments/'.$user->id.'/foto.png';
    Storage::disk('local')->put($path, 'fake-image-bytes');
    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'status' => 'ready',
        'path' => $path,
        'mime' => 'image/png',
        'original_name' => 'foto.png',
    ]);

    Http::fake(['*' => Http::response("data: {\"choices\":[{\"delta\":{\"content\":\"ok\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream'])]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Qué ves?',
        'attachment_ids' => [$attachment->id],
    ])->streamedContent();

    $thread = ChatThread::query()->forUser($user)->sole();

    $this->actingAs($user)
        ->get(route('ai.chat.show', $thread))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/thread')
            ->where('messages.0.attachments.0.id', $attachment->id)
            ->where('messages.0.attachments.0.name', 'foto.png')
        );
});
