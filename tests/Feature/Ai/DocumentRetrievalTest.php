<?php

use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use App\Models\ChatThread;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function indexedDoc(ChatThread $thread, User $user, string $content, string $name = 'plan.txt'): ChatAttachment
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

function sseResponse(): PromiseInterface
{
    return Http::response(
        "data: {\"choices\":[{\"delta\":{\"content\":\"ok\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n",
        200,
        ['Content-Type' => 'text/event-stream'],
    );
}

test('thread documents are injected into the prompt without polluting the stored message', function () {
    Storage::fake('local');
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);
    indexedDoc($thread, $user, 'El plan de hipertrofia usa press banca 4x8 y sentadilla 5x5.');

    Http::fake(['*' => sseResponse()]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Qué dice el plan de hipertrofia?',
        'thread_id' => $thread->id,
    ])->streamedContent();

    Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'Documentos del hilo'));
    Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'press banca 4x8'));

    $stored = $thread->messages()->where('role', 'user')->orderByDesc('id')->first();
    expect($stored->content)->toBe('¿Qué dice el plan de hipertrofia?');
});

test('documents from other threads are not injected', function () {
    Storage::fake('local');
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);
    $other = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);
    indexedDoc($other, $user, 'Secreto de otro hilo: OTRO-HILO-SECRETO sobre criptomonedas.', 'secreto.txt');

    Http::fake(['*' => sseResponse()]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Qué dice sobre criptomonedas?',
        'thread_id' => $thread->id,
    ])->streamedContent();

    Http::assertSent(fn ($request) => ! str_contains(json_encode($request->data()), 'OTRO-HILO-SECRETO'));
    Http::assertSent(fn ($request) => ! str_contains(json_encode($request->data()), 'Documentos del hilo'));
});

test('a message without usable search terms does not inject context', function () {
    Storage::fake('local');
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);
    indexedDoc($thread, $user, 'El plan de hipertrofia usa press banca 4x8.');

    Http::fake(['*' => sseResponse()]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Y tú?',
        'thread_id' => $thread->id,
    ])->streamedContent();

    Http::assertSent(fn ($request) => ! str_contains(json_encode($request->data()), 'Documentos del hilo'));

    $stored = $thread->messages()->where('role', 'user')->orderByDesc('id')->first();
    expect($stored->content)->toBe('¿Y tú?');
});
