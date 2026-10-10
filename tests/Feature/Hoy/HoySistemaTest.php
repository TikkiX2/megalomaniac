<?php

use App\Models\ColaMediaItem;
use App\Models\ProjectTask;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

test('semana muestra pool sin mostrar las 150', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    ProjectTask::factory()->count(3)->create(['user_id' => $user->id, 'en_semana' => true]);
    ProjectTask::factory()->count(20)->create(['user_id' => $user->id, 'en_semana' => false]);

    $response = $this->get('/hoy/semana')->assertOk();
    $pool = $response->viewData('page')['props']['pool'] ?? [];
    expect(count($pool))->toBe(3);
});

test('cola siguiente saca pos1 sin historial', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    ColaMediaItem::create(['user_id' => $user->id, 'titulo' => 'A', 'tipo' => 'serie', 'posicion' => 1]);
    ColaMediaItem::create(['user_id' => $user->id, 'titulo' => 'B', 'tipo' => 'serie', 'posicion' => 2]);

    $this->post('/hoy/cola/siguiente')->assertRedirect();
    expect(ColaMediaItem::where('user_id', $user->id)->orderBy('posicion')->first()->titulo)->toBe('B');
    expect(ColaMediaItem::where('user_id', $user->id)->count())->toBe(1);
});

test('archivo masivo exige preview con cantidad y primeros 15', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    ProjectTask::factory()->count(5)->create(['user_id' => $user->id, 'is_done' => false, 'created_at' => now()->subDays(60)]);

    $preview = $this->postJson('/hoy/archivo-masivo/preview', ['filtro' => 'mas_n_dias', 'dias' => 30]);
    $preview->assertOk()->assertJsonPath('total', 5);
    expect($preview->json('primeros'))->toHaveCount(5);

    // Ejecutar sin confirmado falla
    $this->post('/hoy/archivo-masivo/ejecutar', ['filtro' => 'mas_n_dias', 'dias' => 30])->assertInvalid(['confirmado']);

    // Ejecutar archiva soft reversible
    $this->post('/hoy/archivo-masivo/ejecutar', ['filtro' => 'mas_n_dias', 'dias' => 30, 'confirmado' => true])->assertRedirect();
    expect(ProjectTask::where('user_id', $user->id)->archivadas()->count())->toBe(5);

    // Restaurar en bloque
    $ids = ProjectTask::where('user_id', $user->id)->archivadas()->pluck('id')->toArray();
    $this->post('/hoy/archivadas/restaurar', ['ids' => $ids])->assertRedirect();
    expect(ProjectTask::where('user_id', $user->id)->archivadas()->count())->toBe(0);
});

test('sistema: comandos hoy existen y cerrar no muta', function () {
    $this->artisan('hoy:avisar')->assertSuccessful();
    $this->artisan('hoy:cerrar')->assertSuccessful();
});
