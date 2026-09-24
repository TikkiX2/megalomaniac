<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('chat index lists only own non empty threads ordered by pinned and recency', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'title' => 'Mío',
    ]);
    ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    $emptyThread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    ChatThread::factory()->create();

    $this->actingAs($user)
        ->get(route('ai.chat.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/chat')
            ->has('threads', 1)
            ->where('threads.0.id', $thread->id)
            ->where('threads.0.title', 'Mío')
            ->where('ai.configured', true)
            ->has('models')
        );
});

test('chat index flags unconfigured provider', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ai.chat.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/chat')
            ->where('ai.configured', false)
        );
});
