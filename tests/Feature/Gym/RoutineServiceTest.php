<?php

use App\Models\Exercise;
use App\Models\User;
use App\Services\Gym\RoutineService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function routines(): RoutineService
{
    return app(RoutineService::class);
}

it('creates a routine with exercises by name and targets', function () {
    $user = User::factory()->create();
    $existing = Exercise::factory()->create(['name' => 'Press banca']);

    $routine = routines()->create($user, [
        'name' => 'Pecho pesado',
        'focus' => 'Chest',
        'exercises' => [
            ['id' => $existing->id, 'target_sets' => 4, 'target_reps' => '6', 'target_weight' => '80'],
            ['name' => 'Aperturas', 'target_sets' => 3],
        ],
    ]);

    expect($routine->user_id)->toBe($user->id)
        ->and($routine->exercises)->toHaveCount(2)
        ->and($routine->exercises->first()->pivot->target_sets)->toBe(4)
        ->and(Exercise::where('name', 'Aperturas')->exists())->toBeTrue();
});

it('attaches an existing exercise by exercise_id key', function () {
    $user = User::factory()->create();
    $existing = Exercise::factory()->create(['name' => 'Remo con barra']);

    $routine = routines()->create($user, [
        'name' => 'Pull day',
        'exercises' => [
            ['exercise_id' => $existing->id, 'target_sets' => 3],
        ],
    ]);

    expect($routine->exercises)->toHaveCount(1)
        ->and($routine->exercises->first()->id)->toBe($existing->id);
});

it('updates metadata and replaces the exercise list', function () {
    $user = User::factory()->create();
    $routine = routines()->create($user, [
        'name' => 'Old',
        'exercises' => [['name' => 'Sentadilla']],
    ]);

    $updated = routines()->update($user, $routine, [
        'name' => 'New',
        'exercises' => [['name' => 'Peso muerto']],
    ]);

    expect($updated->name)->toBe('New')
        ->and($updated->exercises)->toHaveCount(1)
        ->and($updated->exercises->first()->name)->toBe('Peso muerto');
});

it('preserves metadata when null values are sent on update', function () {
    $user = User::factory()->create();
    $routine = routines()->create($user, [
        'name' => 'Old',
        'focus' => 'Chest',
        'scheduled_date' => 'Monday',
        'status' => 'inactive',
    ]);

    $updated = routines()->update($user, $routine, [
        'name' => 'New',
        'focus' => null,
        'scheduled_date' => null,
        'status' => null,
    ]);

    expect($updated->name)->toBe('New')
        ->and($updated->focus)->toBe('Chest')
        ->and($updated->scheduled_date)->toBe('Monday')
        ->and($updated->status)->toBe('inactive');
});

it('preserves exercises when null is sent on update', function () {
    $user = User::factory()->create();
    $routine = routines()->create($user, [
        'name' => 'Old',
        'exercises' => [['name' => 'Sentadilla']],
    ]);

    $updated = routines()->update($user, $routine, [
        'name' => 'New',
        'exercises' => null,
    ]);

    expect($updated->exercises)->toHaveCount(1)
        ->and($updated->exercises->first()->name)->toBe('Sentadilla');
});

it('deletes only own routines', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $routine = routines()->create($user, ['name' => 'Mine']);

    expect(fn () => routines()->delete($other, $routine))
        ->toThrow(AuthorizationException::class);

    routines()->delete($user, $routine);
    expect($routine->exists)->toBeFalse();
});
