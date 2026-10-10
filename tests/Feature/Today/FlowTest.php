<?php

use App\Models\Block;
use App\Models\Day;
use App\Models\DayItem;
use App\Models\QueueItem;
use App\Models\Routine;
use App\Models\User;

test('dashboard incluye dia, rutina, block y pick sin backlog', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $day = Day::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString()]);
    DayItem::factory()->create(['day_id' => $day->id, 'title' => 'Lavar', 'position' => 1]);
    Routine::factory()->create(['user_id' => $user->id, 'name' => 'Push', 'scheduled_date' => now()->format('l')]);
    Block::factory()->create(['user_id' => $user->id, 'label' => 'Proyecto', 'weekday' => now()->dayOfWeek, 'active' => true]);
    QueueItem::factory()->create(['user_id' => $user->id, 'title' => 'Dune', 'type' => 'pelicula', 'position' => 1]);

    $props = $this->get(route('dashboard'))->assertOk()->viewData('page')['props'];
    expect($props['day']['items'])->toHaveCount(1);
    expect($props['routine']['name'])->toBe('Push');
    expect($props['block']['label'])->toBe('Proyecto');
    expect($props['pick']['title'])->toBe('Dune');
});

test('marcar hecho setea done_at y nota; volver a pendiente la limpia', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $day = Day::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString()]);
    $item = DayItem::factory()->create(['day_id' => $day->id, 'position' => 1]);

    $this->patch("/today/items/{$item->id}", ['state' => 'done', 'closing_note' => 'tranqui'])->assertRedirect();
    expect($item->fresh()->done_at)->not->toBeNull();
    expect($item->fresh()->closing_note)->toBe('tranqui');

    $this->patch("/today/items/{$item->id}", ['state' => 'pending'])->assertRedirect();
    expect($item->fresh()->done_at)->toBeNull();
});
