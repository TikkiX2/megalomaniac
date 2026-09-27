<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('dangling stored images are pruned from history before the prompt is built', function () {
    Storage::fake('local');
    Http::fake(['*' => Http::response(
        "data: {\"choices\":[{\"delta\":{\"content\":\"ok\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n",
        200,
        ['Content-Type' => 'text/event-stream'],
    )]);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $okPath = 'ai-attachments/'.$user->id.'/ok.png';
    Storage::disk('local')->put($okPath, 'png-bytes');

    $history = ChatMessage::factory()->create([
        'conversation_id' => $thread->id,
        'content' => 'Aca van las fotos',
        'attachments' => [
            ['type' => 'stored-image', 'name' => 'ok.png', 'path' => $okPath, 'disk' => 'local'],
            ['type' => 'stored-image', 'name' => 'gone.png', 'path' => 'ai-attachments/'.$user->id.'/gone.png', 'disk' => 'local'],
        ],
    ]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'seguimos',
        'thread_id' => $thread->id,
    ])->assertOk()->streamedContent();

    expect(collect($history->refresh()->attachments)->pluck('name')->all())->toBe(['ok.png']);

    Http::assertSent(function ($request): bool {
        $body = (string) $request->body();

        return ! str_contains($body, ';base64,"')
            && str_contains($body, ';base64,'.base64_encode('png-bytes'));
    });
});

test('empty stored images are pruned from history before the prompt is built', function () {
    Storage::fake('local');
    Http::fake(['*' => Http::response(
        "data: {\"choices\":[{\"delta\":{\"content\":\"ok\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n",
        200,
        ['Content-Type' => 'text/event-stream'],
    )]);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $okPath = 'ai-attachments/'.$user->id.'/ok.png';
    $emptyPath = 'ai-attachments/'.$user->id.'/empty.png';
    Storage::disk('local')->put($okPath, 'png-bytes');
    Storage::disk('local')->put($emptyPath, '');

    $history = ChatMessage::factory()->create([
        'conversation_id' => $thread->id,
        'content' => 'Aca van las fotos',
        'attachments' => [
            ['type' => 'stored-image', 'name' => 'ok.png', 'path' => $okPath, 'disk' => 'local'],
            ['type' => 'stored-image', 'name' => 'empty.png', 'path' => $emptyPath, 'disk' => 'local'],
        ],
    ]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'seguimos',
        'thread_id' => $thread->id,
    ])->assertOk()->streamedContent();

    expect(collect($history->refresh()->attachments)->pluck('name')->all())->toBe(['ok.png']);
});
