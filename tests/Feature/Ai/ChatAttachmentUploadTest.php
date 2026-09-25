<?php

use App\Jobs\IndexChatDocument;
use App\Models\ChatAttachment;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('an image uploads and returns a ready resource', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ai.chat.attachments.store'), [
        'file' => UploadedFile::fake()->image('foto.png', 200, 200),
    ]);

    $response->assertCreated()->assertJsonPath('kind', 'image')->assertJsonPath('status', 'ready');
    expect(ChatAttachment::query()->forUser($user)->images()->count())->toBe(1);
});

test('a document uploads, queues indexing and reports pending', function () {
    Storage::fake('local');
    Queue::fake();
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('ai.chat.attachments.store'), [
        'file' => UploadedFile::fake()->createWithContent('notas.txt', 'hola mundo'),
        'thread_id' => $thread->id,
    ]);

    $response->assertCreated()->assertJsonPath('kind', 'document')->assertJsonPath('status', 'pending');
    Queue::assertPushed(IndexChatDocument::class);

    $attachment = ChatAttachment::query()->forUser($user)->documents()->sole();

    expect($attachment->path)->toEndWith('.txt')
        ->and($attachment->thread_id)->toBe($thread->id);

    $this->actingAs($user)
        ->get(route('ai.chat.attachments.index', ['ids' => [$attachment->id]]))
        ->assertOk()
        ->assertJsonPath('data.0.id', $attachment->id)
        ->assertJsonPath('data.0.status', 'pending')
        ->assertJsonPath('data.0.is_image', false);
});

test('uploads validate mime and size', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ai.chat.attachments.store'), ['file' => UploadedFile::fake()->create('virus.exe', 10)])
        ->assertSessionHasErrors('file');
});

test('another user cannot view or delete an attachment', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $attachment = ChatAttachment::factory()->create(['user_id' => $owner->id]);
    Storage::disk('local')->put($attachment->path, 'x');

    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get(route('ai.chat.attachments.show', $attachment))->assertNotFound();
    $this->actingAs($intruder)->delete(route('ai.chat.attachments.destroy', $attachment))->assertForbidden();
});

test('owner can stream the file inline and delete it', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $attachment = ChatAttachment::factory()->create(['user_id' => $user->id, 'path' => 'ai-attachments/qa/x.png']);
    Storage::disk('local')->put($attachment->path, 'binary');

    $this->actingAs($user)
        ->get(route('ai.chat.attachments.show', $attachment))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="test.png"');

    $this->actingAs($user)->delete(route('ai.chat.attachments.destroy', $attachment))->assertRedirect();
    expect(ChatAttachment::query()->whereKey($attachment->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($attachment->path);
});
