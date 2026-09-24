<?php

use App\Ai\Services\ChatService;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('is configured requires enabled flag url and key', function () {
    $service = new ChatService;

    expect($service->isConfigured(User::factory()->create()))->toBeFalse();
    expect($service->isConfigured(User::factory()->withAiProvider()->create()))->toBeTrue();
    expect($service->isConfigured(User::factory()->withAiProvider()->create(['ai_enabled' => false])))->toBeFalse();
});

test('create thread stores participant title and agent', function () {
    $user = User::factory()->create();

    $thread = (new ChatService)->createThread($user, '  <b>Plan</b> de entrenamiento para la semana  ');

    expect($thread->belongsToUser($user))->toBeTrue();
    expect($thread->title)->toBe('Plan de entrenamiento para la semana');
    expect($thread->agent)->toBe('megalomaniac');
    expect($thread->model)->toBeNull();
});

test('create thread truncates long first messages', function () {
    $user = User::factory()->create();

    $thread = (new ChatService)->createThread($user, str_repeat('palabra ', 40));

    expect(strlen($thread->title))->toBeLessThanOrEqual(63);
});

test('drop last exchange removes assistant and user messages and returns text', function () {
    $thread = ChatThread::factory()->create();
    ChatMessage::factory()->create(['conversation_id' => $thread->id, 'content' => 'Pregunta']);
    ChatMessage::factory()->assistant()->create(['conversation_id' => $thread->id, 'content' => 'Respuesta']);

    $text = (new ChatService)->dropLastExchange($thread);

    expect($text)->toBe('Pregunta');
    expect($thread->messages()->count())->toBe(0);
});

test('drop last exchange returns null when nothing to drop', function () {
    $thread = ChatThread::factory()->create();

    expect((new ChatService)->dropLastExchange($thread))->toBeNull();
});

test('truncate from deletes the message and everything after it', function () {
    $thread = ChatThread::factory()->create();
    $first = ChatMessage::factory()->create(['conversation_id' => $thread->id]);
    ChatMessage::factory()->assistant()->create(['conversation_id' => $thread->id]);
    $third = ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    (new ChatService)->truncateFrom($thread, $first);

    expect($thread->messages()->pluck('id')->all())->toBe([]);
    expect(ChatMessage::query()->whereKey($third->id)->exists())->toBeFalse();
});

test('edit and resend rejects messages that are not user messages', function () {
    $thread = ChatThread::factory()->create();
    $assistant = ChatMessage::factory()->assistant()->create(['conversation_id' => $thread->id]);

    $result = (new ChatService)->editAndResend(
        User::factory()->withAiProvider()->create(),
        $thread,
        $assistant->id,
        'nuevo texto',
    );

    expect($result)->toBeNull();
});

test('regenerate throws and keeps history when provider is not configured', function () {
    $thread = ChatThread::factory()->create();
    ChatMessage::factory()->create(['conversation_id' => $thread->id, 'content' => 'Pregunta']);
    ChatMessage::factory()->assistant()->create(['conversation_id' => $thread->id, 'content' => 'Respuesta']);

    expect(fn () => (new ChatService)->regenerate(User::factory()->create(), $thread))
        ->toThrow(RuntimeException::class);

    expect($thread->messages()->count())->toBe(2);
});

test('available models returns endpoint list and caches it', function () {
    Http::fake([
        'api.example.com/v1/models' => Http::response([
            'data' => [['id' => 'model-a'], ['id' => 'model-b']],
        ]),
    ]);

    $user = User::factory()->withAiProvider()->create();
    $service = new ChatService;

    expect($service->availableModels($user))->toBe(['model-a', 'model-b']);
    expect($service->availableModels($user))->toBe(['model-a', 'model-b']);
    Http::assertSentCount(1);
});

test('available models falls back to configured model on failure', function () {
    Http::fake(['api.example.com/v1/models' => Http::response(null, 500)]);

    $user = User::factory()->withAiProvider('fallback-model')->create();

    expect((new ChatService)->availableModels($user))->toBe(['fallback-model']);
});

test('delete thread removes messages and thread', function () {
    $thread = ChatThread::factory()->create();
    ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    (new ChatService)->deleteThread($thread);

    expect(ChatThread::query()->whereKey($thread->id)->exists())->toBeFalse();
    expect(ChatMessage::query()->where('conversation_id', $thread->id)->exists())->toBeFalse();
});
