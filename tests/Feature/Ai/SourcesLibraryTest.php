<?php

use App\Ai\Services\ChatService;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('thread deletion detaches library sources but deletes thread images', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $doc = ChatAttachment::factory()->create(['user_id' => $user->id, 'kind' => 'document', 'status' => 'indexed', 'path' => 'ai-attachments/'.$user->id.'/doc.txt']);
    Storage::disk('local')->put($doc->path, 'contenido');
    $thread->sources()->attach($doc->id);

    $image = ChatAttachment::factory()->create(['user_id' => $user->id, 'kind' => 'image', 'path' => 'ai-attachments/'.$user->id.'/img.png']);
    Storage::disk('local')->put($image->path, 'bytes');
    ChatMessage::factory()->create(['conversation_id' => $thread->id, 'id' => '01a0ffff-0000-7000-8000-000000000001']);
    $image->update(['message_id' => '01a0ffff-0000-7000-8000-000000000001']);

    (new ChatService)->deleteThread($thread);

    expect(ChatAttachment::query()->whereKey($doc->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($doc->path);
    expect(DB::table('chat_thread_sources')->where('thread_id', $thread->id)->count())->toBe(0);
    expect(ChatAttachment::query()->whereKey($image->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($image->path);
});

test('migration backfills existing thread documents into the pivot', function () {
    $thread = ChatThread::factory()->create();
    $legacyDocument = ChatAttachment::factory()->create(['kind' => 'document', 'thread_id' => $thread->id]);
    $threadlessDocument = ChatAttachment::factory()->create(['kind' => 'document', 'thread_id' => null]);
    $legacyImage = ChatAttachment::factory()->create(['kind' => 'image', 'thread_id' => $thread->id]);

    $migration = require glob(database_path('migrations/*_create_chat_thread_sources_table.php'))[0];
    $migration->backfill();

    expect($thread->sources()->pluck('chat_attachments.id')->all())->toBe([$legacyDocument->id])
        ->and($legacyDocument->threads()->pluck('agent_conversations.id')->all())->toBe([$thread->id])
        ->and(DB::table('chat_thread_sources')->where('attachment_id', $threadlessDocument->id)->exists())->toBeFalse()
        ->and(DB::table('chat_thread_sources')->where('attachment_id', $legacyImage->id)->exists())->toBeFalse();
});
