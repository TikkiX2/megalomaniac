<?php

use App\Models\Block;
use App\Models\Day;
use App\Models\DayItem;
use App\Models\Project;
use App\Models\ProjectTask;
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

test('items de hoy incluyen el proyecto de su tarea', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $day = Day::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString()]);

    $project = Project::factory()->for($user)->create(['name' => 'Rediseño web', 'color' => '#EF4444']);
    $task = ProjectTask::factory()->create(['project_id' => $project->id]);
    DayItem::factory()->create(['day_id' => $day->id, 'task_id' => $task->id, 'title' => 'Diseñar home', 'position' => 1]);
    DayItem::factory()->create(['day_id' => $day->id, 'task_id' => null, 'title' => 'Lavar', 'position' => 2]);

    $props = $this->get(route('dashboard'))->assertOk()->viewData('page')['props'];

    expect($props['day']['items'][0]['project'])->toBe([
        'id' => $project->id,
        'name' => 'Rediseño web',
        'color' => '#EF4444',
    ]);
    expect($props['day']['items'][1]['project'])->toBeNull();
});

test('tomorrow muestra el dia de manana ya creado y los pendientes de ayer', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $manana = Day::factory()->create(['user_id' => $user->id, 'date' => now()->addDay()->toDateString()]);
    DayItem::factory()->create(['day_id' => $manana->id, 'title' => 'Lavar', 'position' => 1]);

    $ayer = Day::factory()->create(['user_id' => $user->id, 'date' => now()->subDay()->toDateString()]);
    DayItem::factory()->create(['day_id' => $ayer->id, 'title' => 'Colgar', 'position' => 1, 'state' => 'pending']);

    $props = $this->get('/today/tomorrow?target=tomorrow')->assertOk()->viewData('page')['props'];

    expect($props['dia'])->not->toBeNull();
    expect($props['dia']['id'])->toBe($manana->id);
    expect(collect($props['pendientesAyer'])->pluck('title')->all())->toContain('Colgar');
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

test('smoke de todas las paginas today responden 200', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    foreach (['/dashboard', '/today', '/today/tomorrow', '/today/week', '/today/queue', '/today/archived', '/today/bulk-archive'] as $uri) {
        $this->get($uri)->assertOk();
    }
});
