<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class WorkoutWriteTool extends Tool
{
    protected string $name = 'workout-write';

    protected string $description = 'Create workouts, add exercises, and log sets for the authenticated user.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform: create_workout, add_exercise, log_set')->enum(['create_workout', 'add_exercise', 'log_set'])->required(),
            'routine_id' => $schema->integer()->description('Routine ID (required for create_workout)'),
            'workout_id' => $schema->integer()->description('Workout ID (required for add_exercise)'),
            'workout_exercise_id' => $schema->integer()->description('Workout exercise ID (required for log_set)'),
            'exercise_id' => $schema->integer()->description('Exercise ID (required for add_exercise)'),
            'set_number' => $schema->integer()->description('Set number (required for log_set)'),
            'weight' => $schema->number()->description('Weight in kg (for log_set)'),
            'reps' => $schema->integer()->description('Number of reps (for log_set)'),
            'rpe' => $schema->number()->description('Rate of perceived exertion 1-10 (for log_set)'),
            'completed' => $schema->boolean()->description('Whether the set was completed (for log_set, default: true)'),
            'notes' => $schema->string()->description('Notes (for create_workout)'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $action = (string) $request->get('action');

        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        return match ($action) {
            'create_workout' => $this->createWorkout($request, $user),
            'add_exercise' => $this->addExercise($request, $user),
            'log_set' => $this->logSet($request, $user),
            default => Response::error("Invalid action: {$action}"),
        };
    }

    private function createWorkout(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'routine_id' => ['nullable', 'integer', 'exists:routines,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $workout = Workout::create([
            'user_id' => $user->id,
            'routine_id' => $request->get('routine_id'),
            'started_at' => now(),
            'notes' => $request->get('notes'),
        ]);

        return Response::structured([
            'workout' => $workout->fresh(),
            'message' => 'Workout created successfully.',
        ]);
    }

    private function addExercise(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'workout_id' => ['required', 'integer', 'exists:workouts,id'],
            'exercise_id' => ['required', 'integer', 'exists:exercises,id'],
        ]);

        $workout = Workout::where('id', $request->get('workout_id'))
            ->where('user_id', $user->id)
            ->first();

        if (! $workout) {
            return Response::error('Workout not found or unauthorized.');
        }

        $order = $workout->exercises()->max('order') ?? 0;

        $workoutExercise = WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $request->get('exercise_id'),
            'order' => $order + 1,
        ]);

        return Response::structured([
            'workout_exercise' => $workoutExercise->fresh()->load('exercise'),
            'message' => 'Exercise added to workout.',
        ]);
    }

    private function logSet(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'workout_exercise_id' => ['required', 'integer', 'exists:workout_exercises,id'],
            'set_number' => ['required', 'integer', 'min:1'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'reps' => ['nullable', 'integer', 'min:1'],
            'rpe' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'completed' => ['nullable', 'boolean'],
        ]);

        $workoutExercise = WorkoutExercise::where('id', $request->get('workout_exercise_id'))
            ->whereHas('workout', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if (! $workoutExercise) {
            return Response::error('Workout exercise not found or unauthorized.');
        }

        $set = WorkoutSet::updateOrCreate(
            [
                'workout_exercise_id' => $workoutExercise->id,
                'set_number' => $request->get('set_number'),
            ],
            [
                'weight' => $request->get('weight'),
                'reps' => $request->get('reps'),
                'rpe' => $request->get('rpe'),
                'completed' => $request->get('completed', true),
            ]
        );

        return Response::structured([
            'set' => $set,
            'message' => 'Set logged successfully.',
        ]);
    }
}
