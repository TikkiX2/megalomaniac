<?php

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('the memory page renders with scopes, threads and limits', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
    ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    $catalog = app(MemoryCatalog::class);
    $catalog->remember($user, 'Dato general', MemoryScope::Global);
    $catalog->remember($user, 'Dato del hilo', MemoryScope::Thread, $thread);

    $this->actingAs($user)
        ->get(route('ai.memory.index', ['thread' => $thread->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ai/memory')
            ->has('memories', 2)
            ->where('selected_thread', $thread->id)
            ->where('limits.max_content', MemoryCatalog::MAX_CONTENT)
            ->where('limits.max_global', MemoryCatalog::MAX_GLOBAL)
            ->where('limits.max_thread', MemoryCatalog::MAX_THREAD)
            ->has('threads', 1));
});

test('memories can be created, edited, promoted and deleted from the page', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->from(route('ai.memory.index'))
        ->post(route('ai.memory.store'), [
            'content' => 'Prefiere entrenar a la mañana',
            'scope' => 'global',
        ])
        ->assertRedirect(route('ai.memory.index'));

    $global = Memory::query()->forUser($user)->sole();

    expect($global->source)->toBe('user');

    $this->actingAs($user)
        ->from(route('ai.memory.index', ['thread' => $thread->id]))
        ->post(route('ai.memory.store'), [
            'content' => 'El objetivo es un PR de press banca',
            'scope' => 'thread',
            'thread_id' => $thread->id,
        ])
        ->assertRedirect(route('ai.memory.index', ['thread' => $thread->id]));

    $threadMemory = Memory::query()->forUser($user)->forThread($thread)->sole();

    $this->actingAs($user)
        ->from(route('ai.memory.index'))
        ->patch(route('ai.memory.update', $global), ['content' => 'Prefiere entrenar temprano'])
        ->assertRedirect(route('ai.memory.index'));

    expect($global->refresh()->content)->toBe('Prefiere entrenar temprano');

    $this->actingAs($user)
        ->from(route('ai.memory.index'))
        ->post(route('ai.memory.promote', $threadMemory))
        ->assertRedirect(route('ai.memory.index'));

    $threadMemory->refresh();

    expect($threadMemory->scope)->toBe(MemoryScope::Global)
        ->and($threadMemory->thread_id)->toBeNull();

    $this->actingAs($user)
        ->from(route('ai.memory.index'))
        ->delete(route('ai.memory.destroy', $global))
        ->assertRedirect(route('ai.memory.index'));

    expect(Memory::query()->whereKey($global->id)->exists())->toBeFalse();
});

test('memory page validates content, scope and thread ownership', function () {
    $user = User::factory()->create();
    $intruder = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $intruder->getMorphClass(),
        'participant_id' => $intruder->getKey(),
    ]);

    $this->actingAs($user)
        ->post(route('ai.memory.store'), ['content' => '', 'scope' => 'global'])
        ->assertSessionHasErrors('content');

    $this->actingAs($user)
        ->post(route('ai.memory.store'), [
            'content' => str_repeat('a', MemoryCatalog::MAX_CONTENT + 1),
            'scope' => 'global',
        ])
        ->assertSessionHasErrors('content');

    $this->actingAs($user)
        ->post(route('ai.memory.store'), [
            'content' => 'Dato',
            'scope' => 'thread',
            'thread_id' => $thread->id,
        ])
        ->assertSessionHasErrors('thread_id');
});

test('another user cannot touch memories or read them from the page', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $intruder = User::factory()->create();
    $memory = Memory::factory()->global()->create(['user_id' => $intruder->id]);

    $this->actingAs($user)
        ->get(route('ai.memory.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('memories', 0));

    $this->actingAs($user)
        ->patch(route('ai.memory.update', $memory), ['content' => 'Hack'])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('ai.memory.destroy', $memory))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('ai.memory.promote', $memory))
        ->assertForbidden();
});

test('deleting a thread deletes its memories and the show prop counts them', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    Memory::factory()->count(2)->forThread($thread)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('ai.chat.show', $thread))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('thread.memories_count', 2));

    $this->actingAs($user)
        ->delete(route('ai.chat.destroy', $thread))
        ->assertRedirect(route('ai.chat.index'));

    expect(Memory::query()->where('thread_id', $thread->id)->count())->toBe(0);
});
