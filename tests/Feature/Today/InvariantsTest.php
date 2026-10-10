<?php

use App\Models\Day;
use App\Models\DayItem;
use App\Models\ProjectTask;
use App\Models\QueueItem;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('no existe campo tabla ni endpoint de rachas puntos o niveles', function () {
    $columns = [];
    foreach (['days', 'day_items', 'project_tasks', 'blocks', 'queue_items'] as $table) {
        if (Schema::hasTable($table)) {
            $columns = array_merge($columns, Schema::getColumnListing($table));
        }
    }
    foreach ($columns as $col) {
        expect(strtolower($col))->not->toMatch('/streak|racha|xp|level|nivel|points|puntos|badge|logro|heatmap/');
    }
    foreach (Route::getRoutes()->getRoutes() as $r) {
        expect(strtolower($r->uri()))->not->toMatch('/streak|racha|\bxp\b|niveles|logros|insignias/');
    }
});

test('no se puede crear un cuarto day_item', function () {
    $user = User::factory()->create();
    $day = Day::create(['user_id' => $user->id, 'date' => now()->toDateString()]);
    foreach ([1, 2, 3] as $pos) {
        DayItem::create([
            'day_id' => $day->id, 'title' => "T{$pos}", 'anchor' => 'no_anchor',
            'position' => $pos, 'state' => 'pending',
        ]);
    }
    expect(fn () => DayItem::create([
        'day_id' => $day->id, 'title' => 'Cuarta', 'anchor' => 'no_anchor',
        'position' => 3, 'state' => 'pending',
    ]))->toThrow(Exception::class);
});

test('released libera slot y no aparece en visibles', function () {
    $user = User::factory()->create();
    $day = Day::create(['user_id' => $user->id, 'date' => now()->toDateString()]);
    $item = DayItem::create(['day_id' => $day->id, 'title' => 'Soltada', 'anchor' => 'no_anchor', 'position' => 1, 'state' => 'pending']);
    $item->update(['state' => 'released']);

    DayItem::create(['day_id' => $day->id, 'title' => 'Nueva', 'anchor' => 'no_anchor', 'position' => 1, 'state' => 'pending']);
    expect($day->visibleItems()->pluck('title'))->not->toContain('Soltada');
});

test('day es unico por usuario/fecha y queue no guarda historial', function () {
    $user = User::factory()->create();
    Day::create(['user_id' => $user->id, 'date' => '2026-10-11']);
    expect(fn () => Day::create(['user_id' => $user->id, 'date' => '2026-10-11']))->toThrow(Exception::class);

    QueueItem::create(['user_id' => $user->id, 'title' => 'Dune', 'type' => 'libro', 'position' => 1]);
    $cols = Schema::getColumnListing('queue_items');
    expect($cols)->not->toContain('watched_at');
    expect($cols)->not->toContain('minutes_watched');
});

test('project_tasks usa in_week y archived_at', function () {
    expect(Schema::hasColumn('project_tasks', 'in_week'))->toBeTrue();
    expect(Schema::hasColumn('project_tasks', 'archived_at'))->toBeTrue();
    expect(Schema::hasColumn('project_tasks', 'en_semana'))->toBeFalse();

    $user = User::factory()->create();
    ProjectTask::factory()->create(['user_id' => $user->id, 'in_week' => true, 'archived_at' => null]);
    expect(ProjectTask::inWeek()->count())->toBe(1);
});
