<?php

use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function aiThreadFor(User $user): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
}

test('a turn over the image budget is rejected before streaming', function () {
    Storage::fake('local');
    Http::fake();

    $user = User::factory()->withAiProvider()->create();
    $thread = aiThreadFor($user);

    $historyPath = 'ai-attachments/'.$user->id.'/heavy.png';
    Storage::disk('local')->put($historyPath, str_repeat('a', 15 * 1024 * 1024));

    ChatMessage::factory()->create([
        'conversation_id' => $thread->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'content' => 'foto pesada',
        'attachments' => [
            ['type' => 'stored-image', 'name' => 'heavy.png', 'path' => $historyPath, 'disk' => 'local'],
        ],
    ]);

    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'status' => 'ready',
        'size' => 6 * 1024 * 1024,
    ]);

    $this->actingAs($user)->postJson(route('ai.chat.send'), [
        'message' => 'seguimos',
        'thread_id' => $thread->id,
        'attachment_ids' => [$attachment->id],
    ])->assertStatus(422)->assertJsonValidationErrors('attachment_ids');

    Http::assertNothingSent();
});

test('a single oversized image is rejected before streaming', function () {
    Storage::fake('local');
    Http::fake();

    $user = User::factory()->withAiProvider()->create();
    $thread = aiThreadFor($user);

    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'status' => 'ready',
        'size' => 9 * 1024 * 1024,
    ]);

    $this->actingAs($user)->postJson(route('ai.chat.send'), [
        'message' => 'seguimos',
        'thread_id' => $thread->id,
        'attachment_ids' => [$attachment->id],
    ])->assertStatus(422)->assertJsonValidationErrors('attachment_ids');

    Http::assertNothingSent();
});

test('a turn within the budget still streams', function () {
    Storage::fake('local');
    Http::fake(['*' => Http::response(
        "data: {\"choices\":[{\"delta\":{\"content\":\"ok\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n",
        200,
        ['Content-Type' => 'text/event-stream'],
    )]);

    $user = User::factory()->withAiProvider()->create();
    $thread = aiThreadFor($user);

    $path = 'ai-attachments/'.$user->id.'/ok.png';
    Storage::disk('local')->put($path, 'png-bytes');

    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'status' => 'ready',
        'disk' => 'local',
        'path' => $path,
        'size' => strlen('png-bytes'),
    ]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'seguimos',
        'thread_id' => $thread->id,
        'attachment_ids' => [$attachment->id],
    ])->assertOk()->streamedContent();
});
