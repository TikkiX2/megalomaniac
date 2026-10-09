<?php

use App\Http\Resources\ChatThreadResource;
use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Crea un attachment indexado con N chunks de contenido distinguible y lo adjunta como source del hilo. */
function deepContextAttachment(ChatThread $thread, User $user, int $chunks, callable $contentFor, string $name = 'manual.txt'): ChatAttachment
{
    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'document',
        'status' => 'indexed',
        'original_name' => $name,
    ]);

    foreach (range(0, $chunks - 1) as $position) {
        ChatDocumentChunk::create([
            'attachment_id' => $attachment->id,
            'position' => $position,
            'content' => $contentFor($position),
        ]);
    }

    $thread->sources()->attach($attachment->id);

    return $attachment;
}

function deepContextThread(User $user, bool $deep = false): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
        'deep_context' => $deep,
    ]);
}

test('default off returns ten chunks without neighbour expansion', function () {
    $user = User::factory()->create();
    $thread = deepContextThread($user);

    expect((bool) $thread->fresh()->deep_context)->toBeFalse();

    deepContextAttachment(
        $thread,
        $user,
        12,
        fn (int $position): string => "Contenido palabraclave{$position} sobre nutrición y entrenamiento marcador-Q{$position}.",
    );

    $context = $thread->documentContext('palabraclave');

    expect($context)->not->toBeNull()
        ->and(substr_count($context, '###'))->toBe(10);
});

test('expand brings hit neighbours ordered by position', function () {
    $user = User::factory()->create();
    $thread = deepContextThread($user, true);

    deepContextAttachment(
        $thread,
        $user,
        10,
        fn (int $position): string => $position === 5
            ? 'Aquí está el zxqkeyword especial sobre hipertrofia marcador-P5.'
            : "Texto de relleno de la posición {$position} sobre cocina y jardinería marcador-P{$position}.",
    );

    $context = $thread->documentContext('zxqkeyword', 10, true);

    expect($context)->not->toBeNull()
        ->and(substr_count($context, '###'))->toBe(7);

    foreach (range(2, 8) as $position) {
        expect($context)->toContain("marcador-P{$position}");
    }

    expect($context)->not->toContain('marcador-P0')
        ->and($context)->not->toContain('marcador-P1')
        ->and($context)->not->toContain('marcador-P9');

    $offsets = array_map(fn (int $position): int|false => strpos($context, "marcador-P{$position}"), range(2, 8));

    expect($offsets)->toBe(array_values(array_filter($offsets, fn (mixed $offset): bool => $offset !== false)))
        ->and($offsets)->toBe(array_values(array_unique($offsets)));

    $sorted = $offsets;
    sort($sorted);

    expect($offsets)->toBe($sorted);
});

test('expand deduplicates overlapping neighbours', function () {
    $user = User::factory()->create();
    $thread = deepContextThread($user, true);

    deepContextAttachment(
        $thread,
        $user,
        10,
        fn (int $position): string => in_array($position, [4, 6], true)
            ? "Chunk con zxqdupe relevante para la consulta marcador-D{$position}."
            : "Texto de relleno de la posición {$position} sobre cocina y jardinería marcador-D{$position}.",
    );

    $context = $thread->documentContext('zxqdupe', 10, true);

    // Hits 4 y 6 expandidos ±3 cubren 1..9: 9 chunks sin duplicados.
    expect($context)->not->toBeNull()
        ->and(substr_count($context, '###'))->toBe(9);

    foreach (range(1, 9) as $position) {
        expect(substr_count($context, "marcador-D{$position}"))->toBe(1);
    }

    expect($context)->not->toContain('marcador-D0');
});

test('expand caps the total at sixty thousand characters', function () {
    $user = User::factory()->create();
    $thread = deepContextThread($user, true);

    deepContextAttachment(
        $thread,
        $user,
        40,
        fn (int $position): string => "zxqtope palabra marcador-T{$position} ".str_repeat('x', 3000),
    );

    $context = $thread->documentContext('zxqtope', 10, true);

    expect($context)->not->toBeNull()
        ->and(mb_strlen($context))->toBeLessThanOrEqual(60000)
        ->and(substr_count($context, '###'))->toBeLessThan(30);
});

test('expand fallback returns up to thirty chunks without a text match', function () {
    $user = User::factory()->create();
    $thread = deepContextThread($user, true);

    deepContextAttachment(
        $thread,
        $user,
        35,
        fn (int $position): string => "Relleno genérico número {$position} sobre rutinas y compras.",
    );

    $plain = $thread->documentContext('zxqnoexiste');
    $expanded = $thread->documentContext('zxqnoexiste', 10, true);

    expect($plain)->not->toBeNull()
        ->and(substr_count($plain, '###'))->toBe(10)
        ->and($expanded)->not->toBeNull()
        ->and(substr_count($expanded, '###'))->toBe(30);
});

test('patching deep_context persists the flag', function () {
    $user = User::factory()->create();
    $thread = deepContextThread($user);

    $this->actingAs($user)->patch(route('ai.chat.update', $thread), [
        'deep_context' => true,
    ])->assertRedirect();

    expect((bool) $thread->fresh()->deep_context)->toBeTrue();

    $this->actingAs($user)->patch(route('ai.chat.update', $thread), [
        'deep_context' => false,
    ])->assertRedirect();

    expect((bool) $thread->fresh()->deep_context)->toBeFalse();
});

test('the thread resource exposes the deep context flag', function () {
    $user = User::factory()->create();
    $thread = deepContextThread($user, true);

    $payload = (new ChatThreadResource($thread->fresh()))->toArray(request());

    expect($payload['deep_context'] ?? null)->toBeTrue();

    $thread->update(['deep_context' => false]);

    $payload = (new ChatThreadResource($thread->fresh()))->toArray(request());

    expect($payload['deep_context'] ?? null)->toBeFalse();
});
