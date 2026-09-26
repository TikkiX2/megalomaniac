<?php

use App\Ai\Services\ChatService;
use App\Jobs\IndexChatDocument;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

test('an image already sent with a message cannot be deleted', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'path' => 'ai-attachments/qa/enviada.png',
        'message_id' => (string) Str::uuid7(),
    ]);
    Storage::disk('local')->put($attachment->path, 'img');

    $this->actingAs($user)
        ->deleteJson(route('ai.chat.attachments.destroy', $attachment))
        ->assertStatus(422)
        ->assertJson(['message' => 'No se puede eliminar una imagen ya enviada.']);

    expect(ChatAttachment::query()->whereKey($attachment->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($attachment->path);
});

test('unattached images and thread documents can still be deleted', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $image = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'path' => 'ai-attachments/qa/libre.png',
    ]);
    Storage::disk('local')->put($image->path, 'img');

    $document = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'document',
        'path' => 'ai-attachments/qa/plan.txt',
        'original_name' => 'plan.txt',
        'mime' => 'text/plain',
        'message_id' => (string) Str::uuid7(),
    ]);
    Storage::disk('local')->put($document->path, 'doc');

    $this->actingAs($user)->delete(route('ai.chat.attachments.destroy', $image))->assertRedirect();
    $this->actingAs($user)->delete(route('ai.chat.attachments.destroy', $document))->assertRedirect();

    expect(ChatAttachment::query()->whereIn('id', [$image->id, $document->id])->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($image->path);
    Storage::disk('local')->assertMissing($document->path);
});

test('deleting a thread detaches library documents and deletes its message images', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);

    $documentPath = 'ai-attachments/'.$user->id.'/plan.txt';
    Storage::disk('local')->put($documentPath, 'contenido');
    $document = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'document',
        'status' => 'indexed',
        'path' => $documentPath,
        'original_name' => 'plan.txt',
        'mime' => 'text/plain',
    ]);
    $thread->sources()->attach($document->id);

    $message = ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    $imagePath = 'ai-attachments/'.$user->id.'/foto.png';
    Storage::disk('local')->put($imagePath, 'img');
    $image = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'path' => $imagePath,
        'message_id' => $message->id,
    ]);

    $this->actingAs($user)
        ->delete(route('ai.chat.destroy', $thread))
        ->assertRedirect(route('ai.chat.index'));

    expect(ChatThread::query()->whereKey($thread->id)->exists())->toBeFalse()
        ->and(ChatAttachment::query()->whereKey($document->id)->exists())->toBeTrue()
        ->and(ChatAttachment::query()->whereKey($image->id)->exists())->toBeFalse()
        ->and(DB::table('chat_thread_sources')->where('thread_id', $thread->id)->count())->toBe(0);

    Storage::disk('local')->assertExists($documentPath);
    Storage::disk('local')->assertMissing($imagePath);
});

test('truncating a thread detaches attachments from the deleted messages', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);

    $userMessage = ChatMessage::factory()->create([
        'conversation_id' => $thread->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);

    $path = 'ai-attachments/'.$user->id.'/foto.png';
    Storage::disk('local')->put($path, 'img');
    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'thread_id' => $thread->id,
        'kind' => 'image',
        'path' => $path,
        'message_id' => $userMessage->id,
    ]);

    ChatMessage::factory()->assistant()->create([
        'conversation_id' => $thread->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);

    app(ChatService::class)->truncateFrom($thread, $userMessage);

    expect($attachment->refresh()->message_id)->toBeNull()
        ->and(ChatAttachment::query()->whereKey($attachment->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($path);
});

test('dropping the last exchange detaches attachments from it', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);

    $userMessage = ChatMessage::factory()->create([
        'conversation_id' => $thread->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);

    $path = 'ai-attachments/'.$user->id.'/foto.png';
    Storage::disk('local')->put($path, 'img');
    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'thread_id' => $thread->id,
        'kind' => 'image',
        'path' => $path,
        'message_id' => $userMessage->id,
    ]);

    ChatMessage::factory()->assistant()->create([
        'conversation_id' => $thread->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);

    $content = app(ChatService::class)->dropLastExchange($thread);

    expect($content)->toBe($userMessage->content)
        ->and($attachment->refresh()->message_id)->toBeNull()
        ->and(ChatAttachment::query()->whereKey($attachment->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($path);
});
