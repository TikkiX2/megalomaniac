<?php

use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake(['api.example.com/v1/models' => Http::response(['data' => [['id' => 'test-model']]])]);
});

function ownThread(User $user): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
}

test('thread page renders own messages with citations', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ownThread($user);
    $userMessage = ChatMessage::factory()->create(['conversation_id' => $thread->id, 'content' => 'Hola']);
    ChatMessage::factory()->assistant()->withCitations([
        ['url' => 'https://laravel.com', 'title' => 'Laravel', 'start_index' => null, 'end_index' => null],
    ])->create(['conversation_id' => $thread->id, 'content' => 'Qué tal']);

    $this->actingAs($user)
        ->get(route('ai.chat.show', $thread))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/thread')
            ->where('thread.id', $thread->id)
            ->has('messages', 2)
            ->where('messages.0.content', 'Hola')
            ->where('messages.1.citations.0.url', 'https://laravel.com')
        );
});

test('thread page exposes the thread sources', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ownThread($user);
    $document = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'document',
        'status' => 'indexed',
        'original_name' => 'notas.txt',
        'mime' => 'text/plain',
    ]);
    $thread->sources()->attach($document->id);

    $this->actingAs($user)
        ->get(route('ai.chat.show', $thread))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/thread')
            ->has('sources', 1)
            ->where('sources.0.id', $document->id)
            ->where('sources.0.name', 'notas.txt')
            ->missing('documents')
        );
});

test('thread page returns 404 for another users thread', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ownThread(User::factory()->create());

    $this->actingAs($user)
        ->get(route('ai.chat.show', $thread))
        ->assertNotFound();
});

test('thread can be renamed and pinned', function () {
    $user = User::factory()->create();
    $thread = ownThread($user);

    $this->actingAs($user)
        ->patch(route('ai.chat.update', $thread), ['title' => 'Nuevo título', 'pinned' => true])
        ->assertRedirect();

    $thread->refresh();

    expect($thread->title)->toBe('Nuevo título');
    expect($thread->isPinned())->toBeTrue();
});

test('thread update validates title length', function () {
    $user = User::factory()->create();
    $thread = ownThread($user);

    $this->actingAs($user)
        ->patch(route('ai.chat.update', $thread), ['title' => str_repeat('a', 121)])
        ->assertSessionHasErrors('title');
});

test('another user cannot update or delete a thread', function () {
    $user = User::factory()->create();
    $thread = ownThread(User::factory()->create());

    $this->actingAs($user)
        ->patch(route('ai.chat.update', $thread), ['title' => 'Hack'])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('ai.chat.destroy', $thread))
        ->assertForbidden();
});

test('thread can be deleted with its messages', function () {
    $user = User::factory()->create();
    $thread = ownThread($user);
    ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    $this->actingAs($user)
        ->delete(route('ai.chat.destroy', $thread))
        ->assertRedirect(route('ai.chat.index'));

    expect(ChatThread::query()->whereKey($thread->id)->exists())->toBeFalse();
    expect(ChatMessage::query()->where('conversation_id', $thread->id)->exists())->toBeFalse();
});
