<?php

use App\Ai\Services\ChatService;
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
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function sourcesLibraryThread(User $user, string $title = 'Hilo'): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
        'title' => $title,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function sourcesLibraryDocument(User $user, array $attributes = []): ChatAttachment
{
    return ChatAttachment::factory()->create(array_merge([
        'user_id' => $user->id,
        'kind' => 'document',
        'status' => 'indexed',
        'original_name' => 'plan.txt',
        'mime' => 'text/plain',
    ], $attributes));
}

test('thread deletion detaches library sources and preserves its message images', function () {
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

    expect(ChatAttachment::query()->whereKey($doc->id)->exists())->toBeTrue()
        ->and(ChatAttachment::query()->whereKey($image->id)->exists())->toBeTrue()
        ->and($image->refresh()->message_id)->toBeNull()
        ->and(DB::table('chat_thread_sources')->where('thread_id', $thread->id)->count())->toBe(0);

    Storage::disk('local')->assertExists($doc->path);
    Storage::disk('local')->assertExists($image->path);
});

test('thread deletion preserves unsent images and clears the legacy thread column', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $imagePath = 'ai-attachments/'.$user->id.'/sin-enviar.png';
    Storage::disk('local')->put($imagePath, 'img');
    $image = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'path' => $imagePath,
        'thread_id' => $thread->id,
        'message_id' => null,
    ]);

    $documentPath = 'ai-attachments/'.$user->id.'/contexto.txt';
    Storage::disk('local')->put($documentPath, 'doc');
    $document = sourcesLibraryDocument($user, ['path' => $documentPath]);
    $thread->sources()->attach($document->id);

    (new ChatService)->deleteThread($thread);

    expect(ChatAttachment::query()->whereKey($image->id)->exists())->toBeTrue()
        ->and($image->refresh()->thread_id)->toBeNull()
        ->and(ChatAttachment::query()->whereKey($document->id)->exists())->toBeTrue()
        ->and(DB::table('chat_thread_sources')->where('thread_id', $thread->id)->count())->toBe(0);

    Storage::disk('local')->assertExists($imagePath);
    Storage::disk('local')->assertExists($documentPath);
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

test('sources index lists own documents and images with thread counts and titles', function () {
    $this->withoutVite();

    $user = User::factory()->withAiProvider()->create();
    $threadA = sourcesLibraryThread($user, 'Hilo A');
    $threadB = sourcesLibraryThread($user, 'Hilo B');

    $document = sourcesLibraryDocument($user, ['created_at' => now()->subMinute()]);
    $document->threads()->attach([
        $threadA->id => ['created_at' => now()->subMinutes(3), 'updated_at' => now()->subMinutes(3)],
        $threadB->id => ['created_at' => now()->subMinutes(2), 'updated_at' => now()->subMinutes(2)],
    ]);

    $newest = sourcesLibraryDocument($user, ['original_name' => 'nuevo.txt']);

    sourcesLibraryDocument(User::factory()->create(), ['original_name' => 'ajeno.txt']);

    $image = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'created_at' => now()->subSeconds(30),
    ]);

    $this->actingAs($user)
        ->get(route('ai.sources.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/sources', false)
            ->has('items', 3)
            ->where('items.0.id', $newest->id)
            ->where('items.0.is_image', false)
            ->where('items.1.id', $image->id)
            ->where('items.1.is_image', true)
            ->where('items.2.id', $document->id)
            ->where('items.2.threads_count', 2)
            ->where('items.2.threads', ['Hilo B', 'Hilo A'])
            ->where('ai.enabled', true)
            ->where('ai.configured', true)
            ->where('ai.defaultModel', $user->ai_model)
        );
});

test('the shared ai state falls back to the server tavily key', function () {
    $this->withoutVite();

    config(['services.tavily.key' => null]);

    $user = User::factory()->withAiProvider()->create(['tavily_api_key' => null]);

    $this->actingAs($user)
        ->get(route('ai.sources.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('ai.has_tavily_key', false));

    config(['services.tavily.key' => 'tvly-env']);

    $this->actingAs($user)
        ->get(route('ai.sources.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('ai.has_tavily_key', true));
});

test('the thread page exposes pivot attached sources ordered by attach time', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = sourcesLibraryThread($user);

    $older = sourcesLibraryDocument($user, ['original_name' => 'antiguo.txt']);
    $newer = sourcesLibraryDocument($user, ['original_name' => 'reciente.txt']);

    $thread->sources()->attach([
        $older->id => ['created_at' => now()->subMinutes(2), 'updated_at' => now()->subMinutes(2)],
        $newer->id => ['created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()],
    ]);

    $this->actingAs($user)
        ->get(route('ai.chat.show', $thread))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/thread')
            ->has('sources', 2)
            ->where('sources.0.id', $newer->id)
            ->where('sources.1.id', $older->id)
            ->missing('documents')
        );
});

test('the thread page exposes the full library (documents and images) scoped to the user', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = sourcesLibraryThread($user);

    $older = sourcesLibraryDocument($user, ['original_name' => 'antiguo.txt', 'created_at' => now()->subMinute()]);
    $newer = sourcesLibraryDocument($user, ['original_name' => 'nuevo.txt']);
    $image = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'original_name' => 'foto.png',
        'created_at' => now()->subSeconds(30),
    ]);

    sourcesLibraryDocument(User::factory()->create(), ['original_name' => 'ajeno.txt']);
    ChatAttachment::factory()->create(['user_id' => User::factory()->create()->id, 'kind' => 'image']);

    $this->actingAs($user)
        ->get(route('ai.chat.show', $thread))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/thread')
            ->has('library', 3)
            ->where('library.0.id', $newer->id)
            ->where('library.0.threads_count', 0)
            ->where('library.1.id', $image->id)
            ->where('library.1.is_image', true)
            ->where('library.2.id', $older->id)
        );
});

test('a document can be attached to a thread through the library', function () {
    $user = User::factory()->create();
    $thread = sourcesLibraryThread($user);
    $document = sourcesLibraryDocument($user);

    $this->actingAs($user)
        ->post(route('ai.chat.sources.store', $thread), ['attachment_id' => $document->id])
        ->assertRedirect();

    expect(DB::table('chat_thread_sources')
        ->where('thread_id', $thread->id)
        ->where('attachment_id', $document->id)
        ->exists())->toBeTrue();
});

test('attaching a source returns a JSON 201 for JSON requests', function () {
    $user = User::factory()->create();
    $thread = sourcesLibraryThread($user);
    $document = sourcesLibraryDocument($user);

    $this->actingAs($user)
        ->postJson(route('ai.chat.sources.store', $thread), ['attachment_id' => $document->id])
        ->assertCreated()
        ->assertJsonPath('attachment_id', $document->id);
});

test('another user cannot attach sources to a foreign thread', function () {
    $user = User::factory()->create();
    $thread = sourcesLibraryThread(User::factory()->create());
    $document = sourcesLibraryDocument($user);

    $this->actingAs($user)
        ->postJson(route('ai.chat.sources.store', $thread), ['attachment_id' => $document->id])
        ->assertForbidden();

    expect(DB::table('chat_thread_sources')->count())->toBe(0);
});

test('an attachment of another user cannot be attached', function () {
    $user = User::factory()->create();
    $thread = sourcesLibraryThread($user);
    $foreign = sourcesLibraryDocument(User::factory()->create());

    $this->actingAs($user)
        ->postJson(route('ai.chat.sources.store', $thread), ['attachment_id' => $foreign->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attachment_id');

    expect(DB::table('chat_thread_sources')->count())->toBe(0);
});

test('non document and failed attachments cannot be attached', function (string $kind, string $status) {
    $user = User::factory()->create();
    $thread = sourcesLibraryThread($user);
    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => $kind,
        'status' => $status,
    ]);

    $this->actingAs($user)
        ->postJson(route('ai.chat.sources.store', $thread), ['attachment_id' => $attachment->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attachment_id');

    expect(DB::table('chat_thread_sources')->count())->toBe(0);
})->with([
    'image' => ['image', 'ready'],
    'failed document' => ['document', 'failed'],
]);

test('a duplicate attach is rejected with an attachment_id validation error', function () {
    $user = User::factory()->create();
    $thread = sourcesLibraryThread($user);
    $document = sourcesLibraryDocument($user);
    $thread->sources()->attach($document->id);

    $this->actingAs($user)
        ->postJson(route('ai.chat.sources.store', $thread), ['attachment_id' => $document->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attachment_id');

    expect(DB::table('chat_thread_sources')->count())->toBe(1);
});

test('detaching a source removes the pivot but keeps the document and file', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $thread = sourcesLibraryThread($user);
    $path = 'ai-attachments/'.$user->id.'/plan.txt';
    Storage::disk('local')->put($path, 'contenido');
    $document = sourcesLibraryDocument($user, ['path' => $path]);
    $thread->sources()->attach($document->id);

    $this->actingAs($user)
        ->delete(route('ai.chat.sources.destroy', [$thread, $document]))
        ->assertRedirect();

    expect(DB::table('chat_thread_sources')->count())->toBe(0)
        ->and(ChatAttachment::query()->whereKey($document->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($path);
});

test('detaching a source that is not attached returns 404', function () {
    $user = User::factory()->create();
    $thread = sourcesLibraryThread($user);
    $document = sourcesLibraryDocument($user);

    $this->actingAs($user)
        ->deleteJson(route('ai.chat.sources.destroy', [$thread, $document]))
        ->assertNotFound();
});

test('a document uploaded with a thread auto attaches through the pivot', function () {
    Storage::fake('local');
    Queue::fake();
    $user = User::factory()->create();
    $thread = sourcesLibraryThread($user);

    $this->actingAs($user)->post(route('ai.chat.attachments.store'), [
        'file' => UploadedFile::fake()->createWithContent('notas.txt', 'hola mundo'),
        'thread_id' => $thread->id,
    ])->assertCreated();

    $document = ChatAttachment::query()->forUser($user)->documents()->sole();

    expect($document->thread_id)->toBeNull()
        ->and(DB::table('chat_thread_sources')
            ->where('thread_id', $thread->id)
            ->where('attachment_id', $document->id)
            ->exists())->toBeTrue();
});

test('a document uploaded without a thread stays in the library', function () {
    Storage::fake('local');
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('ai.chat.attachments.store'), [
        'file' => UploadedFile::fake()->createWithContent('libre.txt', 'hola mundo'),
    ])->assertCreated();

    $document = ChatAttachment::query()->forUser($user)->documents()->sole();

    expect($document->thread_id)->toBeNull()
        ->and(DB::table('chat_thread_sources')->count())->toBe(0);
});

test('an image uploaded with a thread keeps the legacy thread column', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $thread = sourcesLibraryThread($user);

    $this->actingAs($user)->post(route('ai.chat.attachments.store'), [
        'file' => UploadedFile::fake()->image('foto.png', 200, 200),
        'thread_id' => $thread->id,
    ])->assertCreated();

    $image = ChatAttachment::query()->forUser($user)->images()->sole();

    expect($image->thread_id)->toBe($thread->id)
        ->and(DB::table('chat_thread_sources')->count())->toBe(0);
});

test('deleting a library document removes its row, file and pivot rows', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $thread = sourcesLibraryThread($user);
    $path = 'ai-attachments/'.$user->id.'/libre.txt';
    Storage::disk('local')->put($path, 'doc');
    $document = sourcesLibraryDocument($user, ['path' => $path]);
    $thread->sources()->attach($document->id);

    $this->actingAs($user)
        ->delete(route('ai.chat.attachments.destroy', $document))
        ->assertRedirect();

    expect(ChatAttachment::query()->whereKey($document->id)->exists())->toBeFalse()
        ->and(DB::table('chat_thread_sources')->where('attachment_id', $document->id)->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

test('an image linked to a sent message cannot be deleted from the library', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $path = 'ai-attachments/'.$user->id.'/enviada.png';
    Storage::disk('local')->put($path, 'img');
    $image = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'path' => $path,
        'message_id' => (string) Str::uuid7(),
    ]);

    $this->actingAs($user)
        ->deleteJson(route('ai.chat.attachments.destroy', $image))
        ->assertStatus(422);

    expect(ChatAttachment::query()->whereKey($image->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($path);
});
