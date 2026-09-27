<?php

use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('only the newest user message keeps its images in the prompt history', function () {
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

    $oldPath = 'ai-attachments/'.$user->id.'/old.png';
    $newPath = 'ai-attachments/'.$user->id.'/new.png';
    $sentPath = 'ai-attachments/'.$user->id.'/sent.png';
    Storage::disk('local')->put($oldPath, 'old-bytes');
    Storage::disk('local')->put($newPath, 'new-bytes');
    Storage::disk('local')->put($sentPath, 'sent-bytes');

    ChatMessage::factory()->create([
        'conversation_id' => $thread->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'content' => 'foto vieja',
        'attachments' => [
            ['type' => 'stored-image', 'name' => 'old.png', 'path' => $oldPath, 'disk' => 'local'],
        ],
    ]);

    ChatMessage::factory()->create([
        'conversation_id' => $thread->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'content' => 'foto nueva',
        'attachments' => [
            ['type' => 'stored-image', 'name' => 'new.png', 'path' => $newPath, 'disk' => 'local'],
        ],
    ]);

    $sent = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'disk' => 'local',
        'path' => $sentPath,
        'status' => 'ready',
    ]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'qué ves?',
        'thread_id' => $thread->id,
        'attachment_ids' => [$sent->id],
    ])->assertOk()->streamedContent();

    Http::assertSent(function ($request): bool {
        $body = (string) $request->body();

        return str_contains($body, 'omitidas del historial')
            && ! str_contains($body, base64_encode('old-bytes'))
            && str_contains($body, base64_encode('new-bytes'))
            && str_contains($body, base64_encode('sent-bytes'));
    });
});
