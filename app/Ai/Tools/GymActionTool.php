<?php

namespace App\Ai\Tools;

use App\Models\PersonalRecord;
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
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GymActionTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        protected User $user,
        protected WorkoutSessionService $sessions,
        protected RoutineService $routines,
    ) {}

    public function description(): Stringable|string
    {
        return 'Create and update gym data on behalf of the user: start workouts (optionally from a routine, copying its template), add exercises by name or id, log sets, finish workouts, and create or extend routines with targets. Use this when the user asks to record, create or update training data.';
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Va a '.($this->actionLabels()[$request['action'] ?? ''] ?? 'modificar tus entrenamientos').'.');
    }

    /**
     * @return array<string, string>
     */
    protected function actionLabels(): array
    {
        return [
            'create_workout' => 'iniciar un entrenamiento',
            'add_exercise' => 'añadir un ejercicio al entrenamiento',
            'log_set' => 'registrar una serie',
            'finish_workout' => 'terminar el entrenamiento',
            'create_routine' => 'crear una rutina',
            'add_routine_exercise' => 'añadir un ejercicio a la rutina',
            'update_routine' => 'actualizar la rutina',
            'update_workout' => 'actualizar el entrenamiento',
            'delete_workout' => 'eliminar el entrenamiento',
            'remove_exercise' => 'quitar un ejercicio del entrenamiento',
            'remove_set' => 'quitar una serie',
            'delete_routine' => 'eliminar la rutina',
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return match ($request['action'] ?? '') {
                'create_workout' => $this->createWorkout($request),
                'add_exercise' => $this->addExercise($request),
                'log_set' => $this->logSet($request),
                'finish_workout' => $this->finishWorkout($request),
                'create_routine' => $this->createRoutine($request),
                'add_routine_exercise' => $this->addRoutineExercise($request),
                'update_routine' => $this->updateRoutine($request),
                'update_workout' => $this->updateWorkout($request),
                'delete_workout' => $this->deleteWorkout($request),
                'remove_exercise' => $this->removeExercise($request),
                'remove_set' => $this->removeSet($request),
                'delete_routine' => $this->deleteRoutine($request),
                default => $this->error('Invalid action. Use: create_workout, add_exercise, log_set, finish_workout, create_routine, add_routine_exercise, update_routine, update_workout, delete_workout, remove_exercise, remove_set, delete_routine'),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return $this->error($exception->getMessage());
        }
    }

    private function createWorkout(Request $request): string
    {
        if ($active = $this->sessions->activeFor($this->user)) {
            return $this->success('An active workout already exists.', [
                'workout' => $this->workoutPayload($active),
            ]);
        }

        $workout = $this->sessions->start(
            $this->user,
            isset($request['routine_id']) ? (int) $request['routine_id'] : null,
            $request['started_at'] ?? null,
            $request['notes'] ?? null,
        );

        return $this->success('Workout created', [
            'workout' => $this->workoutPayload($workout),
        ]);
    }

    private function addExercise(Request $request): string
    {
        $workout = $this->findWorkout($request['workout_id'] ?? null);

        if (! $workout) {
            return $this->error('Workout not found');
        }

        $workoutExercise = $this->sessions->addExercise(
            $this->user,
            $workout,
            isset($request['exercise_id']) ? (int) $request['exercise_id'] : null,
            $request['exercise_name'] ?? null,
        );

        return $this->success('Exercise added', [
            'workout_exercise' => $workoutExercise->load('exercise')->toArray(),
        ]);
    }

    private function logSet(Request $request): string
    {
        $workoutExercise = WorkoutExercise::whereHas('workout', fn ($query) => $query->where('user_id', $this->user->id))
            ->find($request['workout_exercise_id'] ?? null);

        if (! $workoutExercise) {
            return $this->error('Workout exercise not found');
        }

        $set = $this->sessions->logSet($this->user, $workoutExercise, array_filter([
            'set_number' => isset($request['set_number']) ? (int) $request['set_number'] : null,
            'weight' => $request['weight'] ?? null,
            'reps' => $request['reps'] ?? null,
            'rpe' => $request['rpe'] ?? null,
            'completed' => $request['completed'] ?? true,
        ], fn (mixed $value): bool => $value !== null));

        return $this->success('Set logged', [
            'set' => $set->toArray() + [
                'is_pr' => PersonalRecord::where('workout_set_id', $set->id)->exists(),
            ],
        ]);
    }

    private function finishWorkout(Request $request): string
    {
        $workout = $this->findWorkout($request['workout_id'] ?? null);

        if (! $workout) {
            return $this->error('Workout not found');
        }

        $workout = $this->sessions->finish(
            $this->user,
            $workout,
            $request['ended_at'] ?? null,
            $request['notes'] ?? null,
        );

        return $this->success('Workout finished', [
            'workout' => $workout->toArray(),
        ]);
    }

    private function createRoutine(Request $request): string
    {
        $name = trim((string) ($request['name'] ?? ''));

        if ($name === '') {
            return $this->error('Routine name is required');
        }

        $routine = $this->routines->create($this->user, [
            'name' => $name,
            'focus' => $request['focus'] ?? null,
            'scheduled_date' => $request['scheduled_date'] ?? null,
            'exercises' => $request['exercises'] ?? [],
        ]);

        return $this->success('Routine created', [
            'routine' => $routine->load('exercises')->toArray(),
        ]);
    }

    private function addRoutineExercise(Request $request): string
    {
        $routine = $this->findRoutine($request['routine_id'] ?? null);

        if (! $routine) {
            return $this->error('Routine not found');
        }

        $exercises = $routine->exercises->map(fn ($exercise) => [
            'id' => $exercise->id,
            'target_sets' => $exercise->pivot->target_sets,
            'target_reps' => $exercise->pivot->target_reps,
            'target_weight' => $exercise->pivot->target_weight,
            'notes' => $exercise->pivot->notes,
        ])->all();

        $exercises[] = array_filter([
            'id' => isset($request['exercise_id']) ? (int) $request['exercise_id'] : null,
            'name' => $request['exercise_name'] ?? null,
            'target_sets' => isset($request['target_sets']) ? (int) $request['target_sets'] : null,
            'target_reps' => $request['target_reps'] ?? null,
            'target_weight' => $request['target_weight'] ?? null,
            'notes' => $request['notes'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        $routine = $this->routines->update($this->user, $routine, ['exercises' => $exercises]);

        return $this->success('Exercise added to routine', [
            'routine' => $routine->load('exercises')->toArray(),
        ]);
    }

    private function updateRoutine(Request $request): string
    {
        $routine = $this->findRoutine($request['routine_id'] ?? null);

        if (! $routine) {
            return $this->error('Routine not found');
        }

        $routine = $this->routines->update($this->user, $routine, array_filter([
            'name' => $request['name'] ?? null,
            'focus' => $request['focus'] ?? null,
            'scheduled_date' => $request['scheduled_date'] ?? null,
            'status' => $request['status'] ?? null,
        ], fn (mixed $value): bool => $value !== null));

        return $this->success('Routine updated', [
            'routine' => $routine->load('exercises')->toArray(),
        ]);
    }

    private function updateWorkout(Request $request): string
    {
        $workout = $this->findWorkout($request['workout_id'] ?? null);

        if (! $workout) {
            return $this->error('Workout not found');
        }

        $workout = $this->sessions->update($this->user, $workout, array_filter([
            'notes' => $request['notes'] ?? null,
            'ended_at' => $request['ended_at'] ?? null,
        ], fn (mixed $value): bool => $value !== null));

        return $this->success('Workout updated', [
            'workout' => $workout->load(['exercises.sets', 'exercises.exercise'])->toArray(),
        ]);
    }

    private function deleteWorkout(Request $request): string
    {
        $workout = $this->findWorkout($request['workout_id'] ?? null);

        if (! $workout) {
            return $this->error('Workout not found');
        }

        $this->sessions->delete($this->user, $workout);

        return $this->success('Workout deleted', ['workout' => ['id' => $workout->id]]);
    }

    private function removeExercise(Request $request): string
    {
        $workoutExercise = WorkoutExercise::whereHas('workout', fn ($query) => $query->where('user_id', $this->user->id))
            ->find($request['workout_exercise_id'] ?? null);

        if (! $workoutExercise) {
            return $this->error('Workout exercise not found');
        }

        $this->sessions->removeExercise($this->user, $workoutExercise);

        return $this->success('Exercise removed from workout', [
            'workout_exercise' => ['id' => $workoutExercise->id],
        ]);
    }

    private function removeSet(Request $request): string
    {
        $set = WorkoutSet::whereHas('workoutExercise.workout', fn ($query) => $query->where('user_id', $this->user->id))
            ->find($request['workout_set_id'] ?? null);

        if (! $set) {
            return $this->error('Set not found');
        }

        $this->sessions->removeSet($this->user, $set);

        return $this->success('Set removed', ['set' => ['id' => $set->id]]);
    }

    private function deleteRoutine(Request $request): string
    {
        $routine = $this->findRoutine($request['routine_id'] ?? null);

        if (! $routine) {
            return $this->error('Routine not found');
        }

        $this->routines->delete($this->user, $routine);

        return $this->success('Routine deleted', ['routine' => ['id' => $routine->id]]);
    }

    private function findWorkout(mixed $workoutId): ?Workout
    {
        if (! $workoutId) {
            return null;
        }

        return $this->user->workouts()->find((int) $workoutId);
    }

    private function findRoutine(mixed $routineId): ?Routine
    {
        if (! $routineId) {
            return null;
        }

        return $this->user->routines()->find((int) $routineId);
    }

    /**
     * @return array<string, mixed>
     */
    private function workoutPayload(Workout $workout): array
    {
        return $workout->load(['exercises.sets', 'exercises.exercise'])->toArray();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function success(string $message, array $payload = []): string
    {
        return json_encode(array_merge([
            'success' => true,
            'message' => $message,
        ], $payload), JSON_PRETTY_PRINT);
    }

    private function error(string $message): string
    {
        return json_encode(['success' => false, 'error' => $message], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create_workout', 'add_exercise', 'log_set', 'finish_workout', 'create_routine', 'add_routine_exercise', 'update_routine', 'update_workout', 'delete_workout', 'remove_exercise', 'remove_set', 'delete_routine'])
                ->description('Action to perform')
                ->required(),
            'routine_id' => $schema->integer()->description('Routine ID (create_workout, add_routine_exercise, update_routine)'),
            'workout_id' => $schema->integer()->description('Workout ID (add_exercise, finish_workout)'),
            'workout_exercise_id' => $schema->integer()->description('Workout exercise ID (log_set; get it from create_workout/add_exercise/workout query)'),
            'exercise_id' => $schema->integer()->description('Existing exercise ID (add_exercise, add_routine_exercise)'),
            'exercise_name' => $schema->string()->description('Exercise name; created if missing (add_exercise, add_routine_exercise)'),
            'set_number' => $schema->integer()->description('Set number (log_set; auto-increments when omitted)'),
            'weight' => $schema->number()->description('Weight (log_set)'),
            'reps' => $schema->integer()->description('Reps (log_set)'),
            'rpe' => $schema->number()->description('RPE 1-10 (log_set)'),
            'completed' => $schema->boolean()->description('Completed (log_set; default true)'),
            'started_at' => $schema->string()->description('ISO 8601 start datetime (create_workout)'),
            'ended_at' => $schema->string()->description('ISO 8601 end datetime (finish_workout)'),
            'notes' => $schema->string()->description('Notes (create_workout, finish_workout, update_workout)'),
            'name' => $schema->string()->description('Routine name (create_routine, update_routine)'),
            'focus' => $schema->string()->description('Routine focus (create_routine, update_routine)'),
            'scheduled_date' => $schema->string()->description('Routine scheduled date (create_routine, update_routine)'),
            'status' => $schema->string()->description('Routine status (update_routine)')->enum(['active', 'inactive', 'archived']),
            'target_sets' => $schema->integer()->description('Target sets (add_routine_exercise)'),
            'target_reps' => $schema->string()->description('Target reps (add_routine_exercise)'),
            'target_weight' => $schema->string()->description('Target weight (add_routine_exercise)'),
            'exercises' => $schema->array()
                ->description('Routine exercises, in order (create_routine)')
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
}
