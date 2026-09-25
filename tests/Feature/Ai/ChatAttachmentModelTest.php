<?php

use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('attachment relations and scopes', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create();
    $image = ChatAttachment::factory()->create(['user_id' => $user->id, 'thread_id' => $thread->id, 'kind' => 'image']);
    ChatAttachment::factory()->create(['user_id' => $user->id, 'kind' => 'document', 'status' => 'indexed']);

    expect($thread->attachments()->count())->toBe(1);
    expect(ChatAttachment::query()->forUser($user)->images()->count())->toBe(1);
    expect(ChatAttachment::query()->forUser($user)->documents()->count())->toBe(1);
    expect($image->isIndexed())->toBeFalse();
});

test('chunks are searchable through fts5', function () {
    $attachment = ChatAttachment::factory()->create(['kind' => 'document', 'status' => 'indexed']);
    ChatDocumentChunk::create(['attachment_id' => $attachment->id, 'position' => 0, 'content' => 'La rutina de hipertrofia usa press banca y sentadilla']);
    ChatDocumentChunk::create(['attachment_id' => $attachment->id, 'position' => 1, 'content' => 'El presupuesto mensual incluye inversiones']);

    $rows = DB::select("select rowid from chat_document_chunks_fts where chat_document_chunks_fts match 'hipertrofia'");

    expect($rows)->toHaveCount(1);

    ChatDocumentChunk::query()->where('position', 0)->delete();

    $rows = DB::select("select rowid from chat_document_chunks_fts where chat_document_chunks_fts match 'hipertrofia'");

    expect($rows)->toHaveCount(0);
});
