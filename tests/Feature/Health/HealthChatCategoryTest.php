<?php

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->thread = ChatThread::create([
        'id' => (string) Str::uuid7(),
        'participant_type' => $this->user->getMorphClass(),
        'participant_id' => $this->user->id,
        'title' => 'Hilo de prueba',
        'category' => 'general',
        'tools_policy' => ['mode' => 'auto', 'groups' => ['memory']],
    ]);
});

it('moves a general thread to salud pinning the health tools', function () {
    $this->patch(route('ai.chat.update', $this->thread), ['category' => 'salud'])
        ->assertRedirect();

    $thread = $this->thread->fresh();

    expect($thread->category)->toBe('salud')
        ->and($thread->tools_policy)->toBe(['mode' => 'manual', 'groups' => ['health']])
        ->and($thread->tools_policy_backup)->toBe(['mode' => 'auto', 'groups' => ['memory']]);
});

it('restores the previous tools policy when moving back to general', function () {
    $this->patch(route('ai.chat.update', $this->thread), ['category' => 'salud']);
    $this->patch(route('ai.chat.update', $this->thread), ['category' => 'general'])
        ->assertRedirect();

    $thread = $this->thread->fresh();

    expect($thread->category)->toBe('general')
        ->and($thread->tools_policy)->toBe(['mode' => 'auto', 'groups' => ['memory']])
        ->and($thread->tools_policy_backup)->toBeNull();
});

it('restores to agent default when the salud thread had no backup', function () {
    $this->thread->forceFill([
        'category' => 'salud',
        'tools_policy' => ['mode' => 'manual', 'groups' => ['health']],
        'tools_policy_backup' => null,
    ])->save();

    $this->patch(route('ai.chat.update', $this->thread), ['category' => 'general'])
        ->assertRedirect();

    $thread = $this->thread->fresh();

    expect($thread->category)->toBe('general')
        ->and($thread->tools_policy)->toBeNull()
        ->and($thread->tools_policy_backup)->toBeNull();
});

it('rejects an invalid category value', function () {
    $this->patch(route('ai.chat.update', $this->thread), ['category' => 'sports'])
        ->assertSessionHasErrors('category');
});

it('forbids changing another user thread category', function () {
    $other = User::factory()->create();
    $foreign = ChatThread::create([
        'id' => (string) Str::uuid7(),
        'participant_type' => $other->getMorphClass(),
        'participant_id' => $other->id,
        'title' => 'Hilo ajeno',
        'category' => 'general',
    ]);

    $this->patch(route('ai.chat.update', $foreign), ['category' => 'salud'])
        ->assertForbidden();

    expect($foreign->fresh()->category)->toBe('general');
});
