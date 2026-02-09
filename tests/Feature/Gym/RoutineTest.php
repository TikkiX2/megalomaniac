<?php

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can create a routine with existing exercises', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();

    $response = $this->actingAs($user)->postJson('/fitness/routines', [
        'name' => 'Chest Day',
        'focus' => 'Strength',
        'exercises' => [
            [
                'id' => $exercise->id,
                'target_sets' => 4,
                'target_reps' => '8-12',
                'target_weight' => '80',
                'notes' => 'Focus on form',
            ],
        ],
    ]);

    $response->assertStatus(201);

    $routine = Routine::first();
    expect($routine->name)->toBe('Chest Day');
    expect($routine->exercises)->toHaveCount(1);
    expect($routine->exercises->first()->id)->toBe($exercise->id);
    expect($routine->exercises->first()->pivot->target_sets)->toBe(4);
    expect($routine->exercises->first()->pivot->target_reps)->toBe('8-12');
});

test('user can create a routine with new exercises', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/fitness/routines', [
        'name' => 'Back Day',
        'exercises' => [
            [
                'name' => 'Lat Pulldown',
                'target_sets' => 3,
                'target_reps' => '10-15',
            ],
        ],
    ]);

    $response->assertStatus(201);

    $exercise = Exercise::where('name', 'Lat Pulldown')->first();
    expect($exercise)->not->toBeNull();

    $routine = Routine::first();
    expect($routine->exercises)->toHaveCount(1);
    expect($routine->exercises->first()->id)->toBe($exercise->id);
});

test('user can update a routine and sync exercises', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $exercise = Exercise::factory()->create();

    $response = $this->actingAs($user)->putJson("/gym/routines/{$routine->id}", [
        'name' => 'Updated Routine Name',
        'scheduled_date' => 'Monday',
        'exercises' => [
            [
                'id' => $exercise->id,
                'target_sets' => 5,
                'target_reps' => '5x5',
            ],
        ],
    ]);

    $response->assertStatus(200);

    $routine->refresh();
    expect($routine->name)->toBe('Updated Routine Name');
    expect($routine->scheduled_date)->toBe('Monday');
    expect($routine->exercises)->toHaveCount(1);
    expect($routine->exercises->first()->pivot->target_sets)->toBe(5);
});

test('user can delete a routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/gym/routines/{$routine->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('routines', ['id' => $routine->id]);
});
