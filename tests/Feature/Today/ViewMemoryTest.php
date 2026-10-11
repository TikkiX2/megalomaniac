<?php

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskSavedView;
use App\Models\User;

test('week recuerda filtros aplicados en vista reservada', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/today/week?priority=High')->assertOk();

    $fila = TaskSavedView::where('user_id', $user->id)->where('view_type', 'today_week')->first();

    expect($fila)->not->toBeNull();
    expect($fila->filters)->toBe(['priority' => 'High']);
});

test('week aplica filtros recordados en visita sin params', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    TaskSavedView::create([
        'user_id' => $user->id,
        'name' => 'Filtros de la semana',
        'view_type' => 'today_week',
        'filters' => ['priority' => 'High'],
    ]);

    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Alta', 'priority' => 'High', 'in_week' => false]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Baja', 'priority' => 'Low', 'in_week' => false]);

    $props = $this->get('/today/week')->assertOk()->viewData('page')['props'];
    $titles = collect($props['candidates'])->pluck('title')->all();

    expect($titles)->toContain('Alta');
    expect($titles)->not->toContain('Baja');
    expect($props['filters'])->toBe(['priority' => 'High']);
});

test('week cleared borra la memoria', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    TaskSavedView::create([
        'user_id' => $user->id,
        'name' => 'Filtros de la semana',
        'view_type' => 'today_week',
        'filters' => ['priority' => 'High'],
    ]);

    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Alta', 'priority' => 'High', 'in_week' => false]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Baja', 'priority' => 'Low', 'in_week' => false]);

    $props = $this->get('/today/week?cleared=1')->assertOk()->viewData('page')['props'];
    $titles = collect($props['candidates'])->pluck('title')->all();

    expect(TaskSavedView::where('user_id', $user->id)->where('view_type', 'today_week')->exists())->toBeFalse();
    expect($props['filters'])->toBe([]);
    expect($titles)->toContain('Alta', 'Baja');
});

test('week descarta project_id ajeno', function () {
    $otro = User::factory()->create();
    $user = User::factory()->create();
    $proyectoAjeno = Project::factory()->create(['user_id' => $otro->id]);
    $this->actingAs($user);

    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Mia']);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Tambien mia']);

    $props = $this->get("/today/week?project_id={$proyectoAjeno->id}")->assertOk()->viewData('page')['props'];
    $titles = collect($props['candidates'])->pluck('title')->all();

    expect(TaskSavedView::where('user_id', $user->id)->where('view_type', 'today_week')->exists())->toBeFalse();
    expect($props['filters'])->toBe([]);
    expect($titles)->toContain('Mia', 'Tambien mia');
});

test('week limit no persiste en la memoria', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    TaskSavedView::create([
        'user_id' => $user->id,
        'name' => 'Filtros de la semana',
        'view_type' => 'today_week',
        'filters' => ['priority' => 'High'],
    ]);

    ProjectTask::factory()->count(45)->create([
        'user_id' => $user->id,
        'project_id' => null,
        'priority' => 'High',
        'in_week' => false,
        'is_done' => false,
    ]);

    $props = $this->get('/today/week?limit=40')->assertOk()->viewData('page')['props'];

    expect($props['limit'])->toBe(40);
    expect($props['candidates'])->toHaveCount(40);
    expect($props['hasMoreCandidates'])->toBeTrue();

    $fila = TaskSavedView::where('user_id', $user->id)->where('view_type', 'today_week')->firstOrFail();
    expect($fila->filters)->toBe(['priority' => 'High']);
});

test('week sanitiza memoria con proyecto borrado', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $proyecto = Project::factory()->create(['user_id' => $user->id, 'name' => 'Borrable']);

    TaskSavedView::create([
        'user_id' => $user->id,
        'name' => 'Filtros de la semana',
        'view_type' => 'today_week',
        'filters' => ['project_id' => $proyecto->id],
    ]);

    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => $proyecto->id, 'title' => 'DelProyecto', 'in_week' => false]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Suelta', 'in_week' => false]);

    $proyecto->delete();

    $props = $this->get('/today/week')->assertOk()->viewData('page')['props'];
    $titles = collect($props['candidates'])->pluck('title')->all();

    expect($titles)->toContain('DelProyecto', 'Suelta');
    expect($props['filters'])->toBe([]);
    expect(TaskSavedView::where('user_id', $user->id)->where('view_type', 'today_week')->exists())->toBeFalse();
});

test('week descarta tag que supera el maximo de 64 caracteres', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tagLargo = str_repeat('a', 65);

    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'ConTagLargo', 'tags' => [$tagLargo], 'in_week' => false]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Otra', 'tags' => ['corta'], 'in_week' => false]);

    $props = $this->get('/today/week?tag='.urlencode($tagLargo))->assertOk()->viewData('page')['props'];
    $titles = collect($props['candidates'])->pluck('title')->all();

    expect($props['filters'])->toBe([]);
    expect(TaskSavedView::where('user_id', $user->id)->where('view_type', 'today_week')->exists())->toBeFalse();
    expect($titles)->toContain('ConTagLargo', 'Otra');
});

test('pagina de tareas personales excluye vistas reservadas', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    TaskSavedView::create(['user_id' => $user->id, 'name' => 'Reservada Semana', 'view_type' => 'today_week', 'filters' => ['priority' => 'High']]);
    TaskSavedView::create(['user_id' => $user->id, 'name' => 'Mi Vista Personal', 'view_type' => 'table', 'filters' => ['status' => 'Pending']]);

    $props = $this->get('/personal/tasks')->assertOk()->viewData('page')['props'];
    $nombres = collect($props['savedViews'])->pluck('name')->all();

    expect($nombres)->toContain('Mi Vista Personal');
    expect($nombres)->not->toContain('Reservada Semana');
});

test('saved views del modulo personal excluye vistas reservadas', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    TaskSavedView::create(['user_id' => $user->id, 'name' => 'Reservada Semana', 'view_type' => 'today_week', 'filters' => ['priority' => 'High']]);
    TaskSavedView::create(['user_id' => $user->id, 'name' => 'Reservada Manana', 'view_type' => 'today_tomorrow', 'filters' => []]);
    TaskSavedView::create(['user_id' => $user->id, 'name' => 'Mi Vista', 'view_type' => 'table', 'filters' => ['status' => 'Pending']]);

    $vistas = collect($this->get('/personal/saved-views')->assertOk()->json());

    expect($vistas->pluck('name')->all())->toContain('Mi Vista');
    expect($vistas->pluck('name')->all())->not->toContain('Reservada Semana', 'Reservada Manana');
});
