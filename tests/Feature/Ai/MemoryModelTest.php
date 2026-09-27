<?php

use App\Ai\Enums\MemoryScope;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;

test('a memory gets a uuid, casts scope and belongs to its user and thread', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $global = Memory::factory()->global()->create(['user_id' => $user->id]);
    $threadMemory = Memory::factory()->forThread($thread)->create(['user_id' => $user->id]);

    expect($global->id)->toBeString()->toHaveLength(36)
        ->and($global->scope)->toBe(MemoryScope::Global)
        ->and($global->thread_id)->toBeNull()
        ->and($global->user->is($user))->toBeTrue()
        ->and($threadMemory->scope)->toBe(MemoryScope::Thread)
        ->and($threadMemory->thread->is($thread))->toBeTrue()
        ->and(Memory::query()->forUser($user)->global()->count())->toBe(1)
        ->and(Memory::query()->forUser($user)->forThread($thread)->count())->toBe(1);
});

test('deleting a thread cascades its memories', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $memory = Memory::factory()->forThread($thread)->create(['user_id' => $user->id]);
    $global = Memory::factory()->global()->create(['user_id' => $user->id]);

    $thread->delete();

    expect(Memory::query()->whereKey($memory->id)->exists())->toBeFalse()
        ->and(Memory::query()->whereKey($global->id)->exists())->toBeTrue();
});
