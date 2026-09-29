<?php

use App\Ai\Tools\GymQueryTool;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

it('returns workouts with exercise ids and filters by exercise name', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Press banca']);
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workout->exercises()->create(['exercise_id' => $exercise->id, 'order' => 1]);

    $data = json_decode((new GymQueryTool($user))->handle(new Request(['days' => 30])), true);

    expect($data)->toHaveCount(1)
        ->and($data[0]['exercises'][0]['exercise_id'])->toBe($exercise->id);

    $filtered = json_decode((new GymQueryTool($user))->handle(new Request(['exercise' => 'banca'])), true);

    expect($filtered)->toHaveCount(1);
});

it('lists the exercise library and routines with targets', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Sentadilla']);
    $routine = $user->routines()->create(['name' => 'Leg day']);
    $routine->exercises()->attach($exercise->id, ['order' => 1, 'target_sets' => 5]);

    $library = json_decode((new GymQueryTool($user))->handle(new Request(['resource' => 'exercises', 'search' => 'senta'])), true);

    expect($library)->toHaveCount(1)
        ->and($library[0]['name'])->toBe('Sentadilla');

    $routines = json_decode((new GymQueryTool($user))->handle(new Request(['resource' => 'routines'])), true);

    expect($routines)->toHaveCount(1)
        ->and($routines[0]['exercises'][0]['exercise_id'])->toBe($exercise->id)
        ->and($routines[0]['exercises'][0]['target_sets'])->toBe(5);
});
