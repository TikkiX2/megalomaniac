<?php

use App\Mcp\Servers\MegalomaniacServer;
use App\Mcp\Tools\WorkoutReadTool;
use App\Mcp\Tools\WorkoutWriteTool;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a workout and logs sets through the mcp server', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutWriteTool::class, ['action' => 'create_workout', 'notes' => 'MCP session'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->has('workout')->etc();
        });

    $workout = Workout::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutWriteTool::class, [
            'action' => 'add_exercise',
            'workout_id' => $workout->id,
            'exercise_name' => 'Remo con barra',
        ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($workout) {
            $json->where('workout_exercise.exercise.name', 'Remo con barra')
                ->where('workout_exercise.workout_id', $workout->id)
                ->etc();
        });

    $workoutExercise = $workout->exercises()->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutWriteTool::class, [
            'action' => 'log_set',
            'workout_exercise_id' => $workoutExercise->id,
            'weight' => 60,
            'reps' => 8,
            'completed' => true,
        ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('set.set_number', 1)->etc();
        });
});

it('finishes workouts and creates routines through mcp', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutWriteTool::class, ['action' => 'finish_workout', 'workout_id' => $workout->id])
        ->assertOk();

    expect($workout->refresh()->ended_at)->not->toBeNull();

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutWriteTool::class, [
            'action' => 'create_routine',
            'name' => 'Push day',
            'exercises' => [
                ['name' => 'Press banca', 'target_sets' => 4],
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('routine.name', 'Push day')->etc();
        });
});

it('refuses to write on another users workout via mcp', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $other->id]);

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutWriteTool::class, ['action' => 'finish_workout', 'workout_id' => $workout->id])
        ->assertHasErrors(['Workout not found or unauthorized']);
});

it('exposes exercise ids and routines in workout-read', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Sentadilla']);
    $routine = Routine::factory()->create(['user_id' => $user->id, 'name' => 'Leg day']);
    $routine->exercises()->attach($exercise->id, ['order' => 1, 'target_sets' => 5]);
    $workout = Workout::factory()->create(['user_id' => $user->id, 'routine_id' => $routine->id]);
    $workout->exercises()->create(['exercise_id' => $exercise->id, 'order' => 1]);

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutReadTool::class, [])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($exercise) {
            $json->where('workouts.0.exercises.0.exercise_id', $exercise->id)
                ->where('routines.0.exercises.0.exercise_id', $exercise->id)
                ->etc();
        });
});
