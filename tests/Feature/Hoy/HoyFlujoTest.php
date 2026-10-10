<?php

use App\Models\Dia;
use App\Models\DiaItem;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
});

test('ritual de las 21 crea el dia de manana no modifica hoy', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $task = ProjectTask::factory()->create(['user_id' => $user->id, 'en_semana' => true]);

    $manana = now()->addDay()->toDateString();
    $response = $this->post('/hoy/manana', [
        'items' => [
            ['tarea_id' => $task->id, 'titulo' => $task->title, 'ancla' => 'sin ancla', 'posicion' => 1],
        ],
    ]);
    $response->assertRedirect();

    expect(Dia::where('user_id', $user->id)->whereDate('fecha', $manana)->exists())->toBeTrue();
    expect(Dia::where('user_id', $user->id)->whereDate('fecha', now()->toDateString())->exists())->toBeFalse();
});

test('no se puede crear un cuarto item por API', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $manana = now()->addDay()->toDateString();
    $response = $this->post('/hoy/manana', [
        'items' => [
            ['titulo' => 'A', 'ancla' => 'sin ancla', 'posicion' => 1],
            ['titulo' => 'B', 'ancla' => 'sin ancla', 'posicion' => 2],
            ['titulo' => 'C', 'ancla' => 'sin ancla', 'posicion' => 3],
            ['titulo' => 'D', 'ancla' => 'sin ancla', 'posicion' => 3],
        ],
    ]);
    $response->assertInvalid(['items']);
});

test('marcar hecho es un solo request sin modal obligatorio', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $dia = Dia::create(['user_id' => $user->id, 'fecha' => now()->toDateString()]);
    $item = DiaItem::create(['dia_id' => $dia->id, 'titulo' => 'Lavar ropa', 'ancla' => 'después de comer', 'posicion' => 1, 'estado' => 'pendiente']);

    $response = $this->patch("/hoy/items/{$item->id}", ['estado' => 'hecho']);
    $response->assertRedirect();
    expect($item->fresh()->estado)->toBe('hecho');
    expect($item->fresh()->hecho_at)->not->toBeNull();
});

test('hoy no consulta cantidad total de pendientes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    ProjectTask::factory()->count(5)->create(['user_id' => $user->id]);

    DB::enableQueryLog();
    $this->get(route('dashboard'))->assertOk();
    $queries = collect(DB::getQueryLog())->map(fn ($q) => strtolower($q['query']))->join(' ');
    // Hoy nunca cuenta tareas pendientes (el count de approval_requests del sidebar es ajeno).
    // Se permite eager-load de las 3 vinculadas (select ... where id in), pero jamás count agregado.
    $hasCountTasks = str_contains($queries, 'count(*)') && str_contains($queries, 'project_tasks');
    expect($hasCountTasks)->toBeFalse();
});
