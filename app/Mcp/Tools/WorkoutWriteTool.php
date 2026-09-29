<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use App\Services\Gym\RoutineService;
use App\Services\Gym\WorkoutSessionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class WorkoutWriteTool extends Tool
{
    protected string $name = 'workout-write';

    protected string $description = 'Create and update workouts, exercises, sets and routines for the authenticated user.';

    public function __construct(
        protected WorkoutSessionService $sessions,
        protected RoutineService $routines,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description('Action: create_workout, add_exercise, log_set, finish_workout, create_routine, add_routine_exercise, update_workout, delete_workout, remove_exercise, remove_set, delete_routine')
                ->enum(['create_workout', 'add_exercise', 'log_set', 'finish_workout', 'create_routine', 'add_routine_exercise', 'update_workout', 'delete_workout', 'remove_exercise', 'remove_set', 'delete_routine'])
                ->required(),
            'routine_id' => $schema->integer()->description('Routine ID (create_workout, add_routine_exercise)'),
            'workout_id' => $schema->integer()->description('Workout ID (add_exercise, finish_workout)'),
            'workout_exercise_id' => $schema->integer()->description('Workout exercise ID (log_set, remove_exercise)'),
            'workout_set_id' => $schema->integer()->description('Workout set ID (remove_set)'),
            'exercise_id' => $schema->integer()->description('Existing exercise ID (add_exercise, add_routine_exercise)'),
            'exercise_name' => $schema->string()->description('Exercise name; created if missing (add_exercise, add_routine_exercise)'),
            'set_number' => $schema->integer()->description('Set number (log_set; auto-increments when omitted)'),
            'weight' => $schema->number()->description('Weight in kg (log_set)'),
            'reps' => $schema->integer()->description('Reps (log_set)'),
            'rpe' => $schema->number()->description('RPE 1-10 (log_set)'),
            'completed' => $schema->boolean()->description('Completed (log_set; default true)'),
            'started_at' => $schema->string()->description('ISO 8601 start datetime (create_workout)'),
            'ended_at' => $schema->string()->description('ISO 8601 end datetime (finish_workout)'),
            'notes' => $schema->string()->description('Notes (create_workout, finish_workout, update_workout, routine exercise)'),
            'name' => $schema->string()->description('Routine name (create_routine)'),
            'focus' => $schema->string()->description('Routine focus (create_routine)'),
            'scheduled_date' => $schema->string()->description('Routine schedule (create_routine)'),
            'target_sets' => $schema->integer()->description('Target sets (add_routine_exercise)'),
            'target_reps' => $schema->string()->description('Target reps (add_routine_exercise)'),
            'target_weight' => $schema->string()->description('Target weight (add_routine_exercise)'),
            'exercises' => $schema->array()
                ->description('Routine exercises (create_routine)')
                ->items($schema->object([
                    'name' => $schema->string()->description('Exercise name; created if missing'),
                    'exercise_id' => $schema->integer()->description('Existing exercise ID'),
                    'target_sets' => $schema->integer(),
                    'target_reps' => $schema->string(),
                    'target_weight' => $schema->string(),
                    'notes' => $schema->string(),
                ])),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        try {
            return match ((string) $request->get('action')) {
                'create_workout' => $this->createWorkout($request, $user),
                'add_exercise' => $this->addExercise($request, $user),
                'log_set' => $this->logSet($request, $user),
                'finish_workout' => $this->finishWorkout($request, $user),
                'create_routine' => $this->createRoutine($request, $user),
                'add_routine_exercise' => $this->addRoutineExercise($request, $user),
                'update_workout' => $this->updateWorkout($request, $user),
                'delete_workout' => $this->deleteWorkout($request, $user),
                'remove_exercise' => $this->removeExercise($request, $user),
                'remove_set' => $this->removeSet($request, $user),
                'delete_routine' => $this->deleteRoutine($request, $user),
                default => Response::error('Invalid action: '.(string) $request->get('action')),
            };
        } catch (ModelNotFoundException) {
            return Response::error('Resource not found or unauthorized.');
        } catch (AuthorizationException|InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }
    }

    private function createWorkout(Request $request, User $user): Response|ResponseFactory
    {
        if ($active = $this->sessions->activeFor($user)) {
            return Response::structured([
                'workout' => $active->load('exercises.sets'),
                'message' => 'An active workout already exists.',
            ]);
        }

        $workout = $this->sessions->start(
            $user,
            $request->get('routine_id') !== null ? (int) $request->get('routine_id') : null,
            $request->get('started_at'),
            $request->get('notes'),
        );

        return Response::structured([
            'workout' => $workout->load('exercises.sets'),
            'message' => 'Workout created successfully.',
        ]);
    }

    private function addExercise(Request $request, User $user): Response|ResponseFactory
    {
        $workout = Workout::where('user_id', $user->id)->find((int) $request->get('workout_id'));

        if (! $workout) {
            return Response::error('Workout not found or unauthorized.');
        }

        $workoutExercise = $this->sessions->addExercise(
            $user,
            $workout,
            $request->get('exercise_id') !== null ? (int) $request->get('exercise_id') : null,
            $request->get('exercise_name'),
        );

        return Response::structured([
            'workout_exercise' => $workoutExercise->load(['exercise', 'sets']),
            'message' => 'Exercise added to workout.',
        ]);
    }

    private function logSet(Request $request, User $user): Response|ResponseFactory
    {
        $workoutExercise = WorkoutExercise::whereHas('workout', fn ($query) => $query->where('user_id', $user->id))
            ->find((int) $request->get('workout_exercise_id'));

        if (! $workoutExercise) {
            return Response::error('Workout exercise not found or unauthorized.');
        }

        if ($request->get('set_number') !== null && (int) $request->get('set_number') < 1) {
            return Response::error('set_number must be at least 1.');
        }

        if ($request->get('weight') !== null && (float) $request->get('weight') < 0) {
            return Response::error('weight must be at least 0.');
        }

        if ($request->get('reps') !== null && (int) $request->get('reps') < 1) {
            return Response::error('reps must be at least 1.');
        }

        $rpe = $request->get('rpe');

        if ($rpe !== null && ((float) $rpe < 1 || (float) $rpe > 10)) {
            return Response::error('rpe must be between 1 and 10.');
        }

        $data = array_filter([
            'set_number' => $request->get('set_number') !== null ? (int) $request->get('set_number') : null,
            'weight' => $request->get('weight'),
            'reps' => $request->get('reps'),
            'rpe' => $request->get('rpe'),
            'completed' => $request->get('completed', true),
        ], fn (mixed $value): bool => $value !== null);

        $set = $this->sessions->logSet($user, $workoutExercise, $data);

        return Response::structured([
            'set' => $set,
            'message' => 'Set logged successfully.',
        ]);
    }

    private function finishWorkout(Request $request, User $user): Response|ResponseFactory
    {
        $workout = Workout::where('user_id', $user->id)->find((int) $request->get('workout_id'));

        if (! $workout) {
            return Response::error('Workout not found or unauthorized.');
        }

        $workout = $this->sessions->finish($user, $workout, $request->get('ended_at'), $request->get('notes'));

        return Response::structured([
            'workout' => $workout,
            'message' => 'Workout finished.',
        ]);
    }

    private function updateWorkout(Request $request, User $user): Response|ResponseFactory
    {
        $workout = $user->workouts()->find($request->get('workout_id', 0));

        if (! $workout) {
            return Response::error('Workout not found or unauthorized.');
        }

        $workout = $this->sessions->update($user, $workout, array_filter([
            'notes' => $request->get('notes'),
            'ended_at' => $request->get('ended_at'),
        ], fn ($value) => $value !== null));

        return Response::structured([
            'workout' => $workout->load(['exercises.sets', 'exercises.exercise']),
            'message' => 'Workout updated successfully.',
        ]);
    }

    private function deleteWorkout(Request $request, User $user): Response|ResponseFactory
    {
        $workout = $user->workouts()->find($request->get('workout_id', 0));

        if (! $workout) {
            return Response::error('Workout not found or unauthorized.');
        }

        $this->sessions->delete($user, $workout);

        return Response::structured(['message' => 'Workout deleted successfully.']);
    }

    private function removeExercise(Request $request, User $user): Response|ResponseFactory
    {
        $workoutExercise = WorkoutExercise::whereHas('workout', fn ($query) => $query->where('user_id', $user->id))
            ->find($request->get('workout_exercise_id', 0));

        if (! $workoutExercise) {
            return Response::error('Workout exercise not found or unauthorized.');
        }

        $this->sessions->removeExercise($user, $workoutExercise);

        return Response::structured(['message' => 'Exercise removed from workout.']);
    }

    private function removeSet(Request $request, User $user): Response|ResponseFactory
    {
        $set = WorkoutSet::whereHas('workoutExercise.workout', fn ($query) => $query->where('user_id', $user->id))
            ->find($request->get('workout_set_id', 0));

        if (! $set) {
            return Response::error('Set not found or unauthorized.');
        }

        $this->sessions->removeSet($user, $set);

        return Response::structured(['message' => 'Set removed successfully.']);
    }

    private function deleteRoutine(Request $request, User $user): Response|ResponseFactory
    {
        $routine = $user->routines()->find($request->get('routine_id', 0));

        if (! $routine) {
            return Response::error('Routine not found or unauthorized.');
        }

        $this->routines->delete($user, $routine);

        return Response::structured(['message' => 'Routine deleted successfully.']);
    }

    private function createRoutine(Request $request, User $user): Response|ResponseFactory
    {
        $name = trim((string) $request->get('name'));

        if ($name === '') {
            return Response::error('Routine name is required.');
        }

        $routine = $this->routines->create($user, [
            'name' => $name,
            'focus' => $request->get('focus'),
            'scheduled_date' => $request->get('scheduled_date'),
            'exercises' => $request->get('exercises', []),
        ]);

        return Response::structured([
            'routine' => $routine->load('exercises'),
            'message' => 'Routine created.',
        ]);
    }

    private function addRoutineExercise(Request $request, User $user): Response|ResponseFactory
    {
        $routine = Routine::where('user_id', $user->id)->find((int) $request->get('routine_id'));

        if (! $routine) {
            return Response::error('Routine not found or unauthorized.');
        }

        $exercises = $routine->exercises->map(fn ($exercise) => [
            'id' => $exercise->id,
            'target_sets' => $exercise->pivot->target_sets,
            'target_reps' => $exercise->pivot->target_reps,
            'target_weight' => $exercise->pivot->target_weight,
            'notes' => $exercise->pivot->notes,
        ])->all();

        $exercises[] = array_filter([
            'id' => $request->get('exercise_id') !== null ? (int) $request->get('exercise_id') : null,
            'name' => $request->get('exercise_name'),
            'target_sets' => $request->get('target_sets') !== null ? (int) $request->get('target_sets') : null,
            'target_reps' => $request->get('target_reps'),
            'target_weight' => $request->get('target_weight'),
            'notes' => $request->get('notes'),
        ], fn (mixed $value): bool => $value !== null);

        $routine = $this->routines->update($user, $routine, ['exercises' => $exercises]);

        return Response::structured([
            'routine' => $routine->load('exercises'),
            'message' => 'Exercise added to routine.',
        ]);
    }
}
