<?php

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Ai\Tools\ForgetMemoryTool;
use App\Ai\Tools\PromoteMemoryTool;
use App\Ai\Tools\RememberMemoryTool;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;
use Laravel\Ai\Tools\Request;

function memoryToolThread(User $user): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
}

test('remember tool saves global memories and reports thread-scope misuse', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);
    $tool = new RememberMemoryTool($user, $catalog);

    $result = json_decode((string) $tool->handle(new Request([
        'content' => 'Prefiere reportes cortos',
        'scope' => 'global',
    ])), true);

    expect($result['success'])->toBeTrue()
        ->and(Memory::query()->forUser($user)->sole()->content)->toBe('Prefiere reportes cortos');

    $error = (string) $tool->handle(new Request([
        'content' => 'Detalle del hilo',
        'scope' => 'thread',
    ]));

    expect($error)->toContain('hilo');
});

test('remember tool returns the cap error as tool text', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    Memory::factory()->count(MemoryCatalog::MAX_GLOBAL)->global()->create(['user_id' => $user->id]);

    $tool = new RememberMemoryTool($user, $catalog);

    $result = (string) $tool->handle(new Request([
        'content' => 'Una más',
        'scope' => 'global',
    ]));

    expect($result)->toContain('Límite');
});

test('forget tool deletes by id and resolves unique queries', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);
    $thread = memoryToolThread($user);

    $byId = $catalog->remember($user, 'Memoria uno', MemoryScope::Global);
    $byQuery = $catalog->remember($user, 'Memoria dos', MemoryScope::Global);

    $tool = new ForgetMemoryTool($user, $catalog, $thread);

    (string) $tool->handle(new Request(['id' => $byId->id]));

    expect(Memory::query()->whereKey($byId->id)->exists())->toBeFalse();

    $result = (string) $tool->handle(new Request(['query' => 'memoria dos']));

    expect($result)->toContain('borrada')
        ->and(Memory::query()->whereKey($byQuery->id)->exists())->toBeFalse();
});

test('forget tool lists candidates when the query is ambiguous', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    $catalog->remember($user, 'Proyecto alpha', MemoryScope::Global);
    $catalog->remember($user, 'Proyecto beta', MemoryScope::Global);

    $tool = new ForgetMemoryTool($user, $catalog);

    $result = (string) $tool->handle(new Request(['query' => 'proyecto']));

    expect($result)->toContain('Proyecto alpha')
        ->and($result)->toContain('Proyecto beta')
        ->and(Memory::query()->forUser($user)->count())->toBe(2);
});

test('promote tool moves thread memories to global and rejects globals', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);
    $thread = memoryToolThread($user);

    $threadMemory = $catalog->remember($user, 'Del hilo', MemoryScope::Thread, $thread);
    $globalMemory = $catalog->remember($user, 'Global', MemoryScope::Global);

    $tool = new PromoteMemoryTool($user, $catalog);

    (string) $tool->handle(new Request(['id' => $threadMemory->id]));

    expect($threadMemory->refresh()->scope)->toBe(MemoryScope::Global);

    $result = (string) $tool->handle(new Request(['id' => $globalMemory->id]));

    expect($result)->toContain('hilo');
});
