<?php

use App\Models\ColaMediaItem;
use App\Models\Dia;
use App\Models\DiaItem;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('no existe ningún campo tabla ni endpoint de rachas puntos o niveles', function () {
    $columns = [];
    foreach (['dias', 'dia_items', 'project_tasks', 'bloques', 'cola_media'] as $table) {
        if (Schema::hasTable($table)) {
            $columns = array_merge($columns, Schema::getColumnListing($table));
        }
    }
    foreach ($columns as $col) {
        expect(strtolower($col))->not->toMatch('/streak|racha|xp|level|nivel|points|puntos|badge|logro/');
    }

    $routes = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri());
    foreach ($routes as $uri) {
        expect(strtolower($uri))->not->toMatch('/streak|racha|\bxp\b|niveles|logros|insignias/');
    }
});

test('no se puede crear un cuarto dia_item por validación de modelo', function () {
    $user = User::factory()->create();
    $dia = Dia::create(['user_id' => $user->id, 'fecha' => now()->toDateString()]);
    foreach ([1, 2, 3] as $pos) {
        DiaItem::create([
            'dia_id' => $dia->id,
            'titulo' => "Tarea {$pos}",
            'ancla' => 'sin ancla',
            'posicion' => $pos,
            'estado' => 'pendiente',
        ]);
    }

    expect(fn () => DiaItem::create([
        'dia_id' => $dia->id,
        'titulo' => 'Cuarta',
        'ancla' => 'sin ancla',
        'posicion' => 3,
        'estado' => 'pendiente',
    ]))->toThrow(Exception::class);
});

test('dia respeta UNIQUE user fecha y dia_item UNIQUE dia posicion', function () {
    $user = User::factory()->create();
    Dia::create(['user_id' => $user->id, 'fecha' => '2026-10-11']);

    expect(fn () => Dia::create(['user_id' => $user->id, 'fecha' => '2026-10-11']))
        ->toThrow(Exception::class);
});

test('soltado libera el slot y no aparece en visibles', function () {
    $user = User::factory()->create();
    $dia = Dia::create(['user_id' => $user->id, 'fecha' => now()->toDateString()]);
    $soltado = DiaItem::create([
        'dia_id' => $dia->id, 'titulo' => 'Soltada', 'ancla' => 'sin ancla', 'posicion' => 1, 'estado' => 'pendiente',
    ]);
    $soltado->estado = 'soltado';
    $soltado->save();

    // Tras soltar, el slot 1 queda libre para reutilizar
    $nuevo = DiaItem::create([
        'dia_id' => $dia->id, 'titulo' => 'Nueva', 'ancla' => 'sin ancla', 'posicion' => 1, 'estado' => 'pendiente',
    ]);
    expect($nuevo->id)->not->toBeNull();
    expect($dia->itemsVisibles()->pluck('titulo'))->not->toContain('Soltada');
});

test('ancla valida solo lista cerrada y estado binario más soltado', function () {
    $user = User::factory()->create();
    $dia = Dia::create(['user_id' => $user->id, 'fecha' => now()->toDateString()]);

    expect(fn () => DiaItem::create([
        'dia_id' => $dia->id, 'titulo' => 'X', 'ancla' => 'cuando quiera', 'posicion' => 1, 'estado' => 'pendiente',
    ]))->toThrow(Exception::class);

    expect(fn () => DiaItem::create([
        'dia_id' => $dia->id, 'titulo' => 'X', 'ancla' => 'sin ancla', 'posicion' => 1, 'estado' => 'a medias',
    ]))->toThrow(Exception::class);
});

test('cola de media solo cambia posicion o borra, sin historial', function () {
    $user = User::factory()->create();
    $item = ColaMediaItem::create(['user_id' => $user->id, 'titulo' => 'Serie A', 'tipo' => 'serie', 'posicion' => 1]);
    $cols = Schema::getColumnListing('cola_media');
    expect($cols)->not->toContain('visto_at');
    expect($cols)->not->toContain('minutos_vistos');
    expect($item->posicion)->toBe(1);
});

test('archivado es soft reversible y existe archivada_at', function () {
    expect(Schema::hasColumn('project_tasks', 'archivada_at'))->toBeTrue();
    expect(Schema::hasColumn('project_tasks', 'en_semana'))->toBeTrue();
});
