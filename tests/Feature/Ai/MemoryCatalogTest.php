<?php

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function memoryThread(User $user): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
}

test('remember normalizes content and deduplicates by hash', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    $first = $catalog->remember($user, '  Prefiere   entrenar a la mañana ', MemoryScope::Global);
    $second = $catalog->remember($user, 'prefiere entrenar a la mañana', MemoryScope::Global);

    expect($second->id)->toBe($first->id)
        ->and($second->content)->toBe('prefiere entrenar a la mañana')
        ->and(Memory::query()->forUser($user)->count())->toBe(1)
        ->and($first->content_hash)->toBe(MemoryCatalog::hashContent('prefiere entrenar a la mañana'));
});

test('remember enforces the global and thread caps', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    Memory::factory()->count(MemoryCatalog::MAX_GLOBAL)->global()->create(['user_id' => $user->id]);

    expect(fn () => $catalog->remember($user, 'Una más', MemoryScope::Global))
        ->toThrow(ValidationException::class);

    $thread = memoryThread($user);
    Memory::factory()->count(MemoryCatalog::MAX_THREAD)->forThread($thread)->create(['user_id' => $user->id]);

    expect(fn () => $catalog->remember($user, 'Otra', MemoryScope::Thread, $thread))
        ->toThrow(ValidationException::class);
});

test('remember requires a thread for thread scope and rejects empty content', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    expect(fn () => $catalog->remember($user, 'x', MemoryScope::Thread))
        ->toThrow(ValidationException::class);

    expect(fn () => $catalog->remember($user, '   ', MemoryScope::Global))
        ->toThrow(ValidationException::class);
});

test('update rejects content that duplicates another memory in the same scope', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    $first = $catalog->remember($user, 'Primera', MemoryScope::Global);
    $second = $catalog->remember($user, 'Segunda', MemoryScope::Global);

    expect(fn () => $catalog->update($first, 'segunda'))
        ->toThrow(ValidationException::class);

    $catalog->update($first, 'Primera editada');

    expect($first->refresh()->content)->toBe('Primera editada')
        ->and($first->content_hash)->toBe(MemoryCatalog::hashContent('Primera editada'));
});

test('promote moves a thread memory to global with metadata', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);
    $thread = memoryThread($user);

    $memory = $catalog->remember($user, 'El proyecto es X', MemoryScope::Thread, $thread);
    $catalog->promote($memory);

    $memory->refresh();

    expect($memory->scope)->toBe(MemoryScope::Global)
        ->and($memory->thread_id)->toBeNull()
        ->and($memory->metadata['promoted_at'] ?? null)->not->toBeNull();
});

test('candidatesFor searches visible memories by normalized content', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);
    $thread = memoryThread($user);

    $global = $catalog->remember($user, 'Prefiere reportes cortos', MemoryScope::Global);
    $catalog->remember($user, 'Detalle del hilo: PR de press banca', MemoryScope::Thread, $thread);
    $catalog->remember($user, 'Otra memoria', MemoryScope::Thread, memoryThread($user));
    $percent = $catalog->remember($user, 'Press banca al 80% de 1RM', MemoryScope::Thread, $thread);

    $matches = $catalog->candidatesFor($user, $thread, 'PR de press');

    expect($matches)->toHaveCount(1)
        ->and($matches->first()->id)->not->toBe($global->id);

    expect($catalog->candidatesFor($user, $thread, 'prefiere'))->toHaveCount(1)
        ->and($catalog->candidatesFor($user, $thread, 'inexistente'))->toHaveCount(0);

    $percentMatches = $catalog->candidatesFor($user, $thread, '80%');

    expect($percentMatches)->toHaveCount(1)
        ->and($percentMatches->first()->id)->toBe($percent->id);
});

test('blockFor orders by recency and respects the injection budget', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    $oldest = $catalog->remember($user, str_repeat('a', 400), MemoryScope::Global);

    for ($i = 0; $i < 30; $i++) {
        $catalog->remember($user, str_repeat('b', 400).$i, MemoryScope::Global);
    }

    $newest = Memory::query()->forUser($user)->orderByDesc('updated_at')->orderByDesc('id')->first();

    $block = $catalog->blockFor($user, null);

    expect($block)->toContain('Memoria general del usuario')
        ->and($block)->toContain($newest->id)
        ->and($block)->toContain('memorias no mostradas')
        ->and($block)->not->toContain($oldest->id);
});

test('blockFor renders global and thread sections with ids', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);
    $thread = memoryThread($user);

    $global = $catalog->remember($user, 'Dato general', MemoryScope::Global);
    $threadMemory = $catalog->remember($user, 'Dato del hilo', MemoryScope::Thread, $thread);
    $catalog->remember($user, "Linea uno\nLinea dos", MemoryScope::Global);

    $block = $catalog->blockFor($user, $thread);

    expect($block)->toContain('Memoria general del usuario')
        ->and($block)->toContain($global->id)
        ->and($block)->toContain('Memoria de este hilo')
        ->and($block)->toContain($threadMemory->id)
        ->and($block)->toContain('Linea uno Linea dos')
        ->and($block)->not->toContain("\nLinea dos")
        ->and($catalog->blockFor(User::factory()->create(), null))->toBeNull();
});
