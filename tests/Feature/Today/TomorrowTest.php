<?php

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskSavedView;
use App\Models\User;

test('tomorrow pool excluye tareas hechas', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Pendiente', 'in_week' => true, 'is_done' => false]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Hecha', 'in_week' => true, 'is_done' => true]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Fuera del pool', 'in_week' => false, 'is_done' => false]);

    $props = $this->get('/today/tomorrow')->assertOk()->viewData('page')['props'];
    $titles = collect($props['pool'])->pluck('title')->all();

    expect($titles)->toContain('Pendiente');
    expect($titles)->not->toContain('Hecha');
    expect($titles)->not->toContain('Fuera del pool');
});

test('tomorrow pool ordenado por creacion ascendente', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $nueva = ProjectTask::factory()->create([
        'user_id' => $user->id,
        'project_id' => null,
        'title' => 'Nueva',
        'in_week' => true,
        'is_done' => false,
        'sort_order' => 1,
        'created_at' => now()->subDay(),
    ]);
    $vieja = ProjectTask::factory()->create([
        'user_id' => $user->id,
        'project_id' => null,
        'title' => 'Vieja',
        'in_week' => true,
        'is_done' => false,
        'sort_order' => 2,
        'created_at' => now()->subDays(30),
    ]);

    $props = $this->get('/today/tomorrow')->assertOk()->viewData('page')['props'];

    expect($props['pool'])->toHaveCount(2);
    expect($props['pool'][0]['id'])->toBe($vieja->id);
    expect($props['pool'][1]['id'])->toBe($nueva->id);
});

test('tomorrow pool incluye proyecto con color', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $proyecto = Project::factory()->create(['user_id' => $user->id, 'name' => 'Con Color', 'type' => 'personal', 'color' => '#3B82F6']);
    $conProyecto = ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => $proyecto->id, 'title' => 'ConProyecto', 'in_week' => true, 'is_done' => false]);
    $suelta = ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'SinProyecto', 'in_week' => true, 'is_done' => false]);

    $props = $this->get('/today/tomorrow')->assertOk()->viewData('page')['props'];

    $item = collect($props['pool'])->firstWhere('id', $conProyecto->id);
    expect($item['project'])->not->toBeNull();
    expect($item['project']['id'])->toBe($proyecto->id);
    expect($item['project']['name'])->toBe('Con Color');
    expect($item['project']['color'])->toBe('#3B82F6');

    $sueltaItem = collect($props['pool'])->firstWhere('id', $suelta->id);
    expect($sueltaItem['project'])->toBeNull();

    expect($props)->toHaveKeys(['projects', 'availableTags', 'filters']);
});

test('tomorrow recuerda filtros con nombre del spec en la vista reservada', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/today/tomorrow?priority=High')->assertOk();

    $fila = TaskSavedView::where('user_id', $user->id)->where('view_type', 'today_tomorrow')->first();

    expect($fila)->not->toBeNull();
    expect($fila->name)->toBe('Mañana (automática)');
    expect($fila->filters)->toBe(['priority' => 'High']);
});

test('tomorrow pool aplica filtros recordados', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    TaskSavedView::create([
        'user_id' => $user->id,
        'name' => 'Mañana (automática)',
        'view_type' => 'today_tomorrow',
        'filters' => ['priority' => 'High'],
        'is_default' => false,
    ]);

    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Alta', 'priority' => 'High', 'in_week' => true, 'is_done' => false]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Baja', 'priority' => 'Low', 'in_week' => true, 'is_done' => false]);

    $props = $this->get('/today/tomorrow')->assertOk()->viewData('page')['props'];
    $titles = collect($props['pool'])->pluck('title')->all();

    expect($titles)->toContain('Alta');
    expect($titles)->not->toContain('Baja');
    expect($props['filters'])->toBe(['priority' => 'High']);
});
