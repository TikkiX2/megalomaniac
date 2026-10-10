<?php

use App\Models\Day;
use App\Models\QueueItem;
use App\Models\User;
use App\Services\Media\DailyPickService;

test('pick es determinístico por usuario fecha y tipo', function () {
    $user = User::factory()->create();
    $items = QueueItem::factory()->count(5)->create(['user_id' => $user->id, 'type' => 'pelicula']);
    $svc = app(DailyPickService::class);

    $a = $svc->pick($user->id, '2026-10-10', 'pelicula', $items);
    $b = $svc->pick($user->id, '2026-10-10', 'pelicula', $items);

    expect($a)->not->toBeNull();
    expect($a->id)->toBe($b->id);

    // Otra fecha produce otra elección (barrido de fechas: con datos fijos la
    // fórmula es determinística, no depende del azar del test).
    $otro = collect(['2026-10-11', '2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15'])
        ->map(fn (string $date) => $svc->pick($user->id, $date, 'pelicula', $items)->id)
        ->first(fn (int $id) => $id !== $a->id);
    expect($otro)->not->toBeNull();

    expect($svc->pick($user->id, '2026-10-10', 'pelicula', collect()))->toBeNull();
});

test('surprise respeta el tipo elegido', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    QueueItem::factory()->create(['user_id' => $user->id, 'type' => 'libro', 'title' => 'Dune']);
    QueueItem::factory()->create(['user_id' => $user->id, 'type' => 'pelicula', 'title' => 'Heat']);

    $res = $this->getJson('/today/surprise?type=libro')->assertOk()->json();

    expect($res['title'])->toBe('Dune');
    expect($res['type'])->toBe('libro');
    expect($res)->toHaveKeys(['title', 'type', 'cover_url', 'source']);
});

test('surprise sin items del tipo responde 204', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    QueueItem::factory()->create(['user_id' => $user->id, 'type' => 'pelicula', 'title' => 'Heat']);

    $this->getJson('/today/surprise?type=juego')->assertNoContent();
});

test('surprise devuelve un item distinto al pick diario cuando hay mas de uno del tipo', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Day::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString(), 'pick_type' => 'libro']);
    QueueItem::factory()->count(3)->create(['user_id' => $user->id, 'type' => 'libro']);

    $props = $this->get(route('dashboard'))->assertOk()->viewData('page')['props'];
    expect($props['pick']['title'])->not->toBeNull();

    $res = $this->getJson('/today/surprise?type=libro')->assertOk()->json();
    expect($res['title'])->not->toBe($props['pick']['title']);
});

test('cambiar pick type persiste en el day de hoy y crea el day si no existe', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/today/pick-type', ['type' => 'libro'])->assertRedirect();

    $day = Day::where('user_id', $user->id)->whereDate('date', now()->toDateString())->first();
    expect($day)->not->toBeNull();
    expect($day->pick_type)->toBe('libro');

    $this->post('/today/pick-type', ['type' => 'juego'])->assertRedirect();
    expect($day->fresh()->pick_type)->toBe('juego');

    $this->post('/today/pick-type', ['type' => 'series'])->assertInvalid(['type']);
});
