<?php

use App\Models\ChatThread;
use App\Models\HealthCondition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates a health chat with pinned tools and context', function () {
    $condition = HealthCondition::factory()->create(['user_id' => $this->user->id]);

    $this->post('/health/chats', [
        'context_type' => 'health_condition',
        'context_id' => $condition->id,
    ])->assertRedirect();

    $thread = ChatThread::where('category', 'salud')->firstOrFail();

    expect($thread->tools_policy)->toBe(['mode' => 'manual', 'groups' => ['health']])
        ->and($thread->context_type)->toBe(HealthCondition::class)
        ->and($thread->context_id)->toBe($condition->id)
        ->and($thread->participant_id)->toBe($this->user->id);
});

it('lists only the user health chats', function () {
    $createThread = function (string $title, string $category): ChatThread {
        $thread = ChatThread::create([
            'id' => (string) Str::uuid7(),
            'participant_type' => $this->user->getMorphClass(),
            'participant_id' => $this->user->id,
            'title' => $title,
        ]);
        $thread->forceFill(['category' => $category])->save();

        return $thread;
    };

    $createThread('Hilo salud', 'salud');
    $createThread('Hilo general', 'general');

    $this->get('/health/chats')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/chats/Index')
            ->where('threads', fn ($rows) => collect($rows)->pluck('title')->all() === ['Hilo salud']));
});

it('rejects a context owned by another user', function () {
    $condition = HealthCondition::factory()->create();

    $this->post('/health/chats', [
        'context_type' => 'health_condition',
        'context_id' => $condition->id,
    ])->assertNotFound();
});
