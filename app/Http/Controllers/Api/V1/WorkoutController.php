<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreWorkoutRequest;
use App\Http\Resources\WorkoutExerciseResource;
use App\Http\Resources\WorkoutResource;
use App\Http\Resources\WorkoutSetResource;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Services\Gym\WorkoutSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class WorkoutController extends Controller
{
    public function __construct(protected WorkoutSessionService $sessions) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $workouts = $request->user()
            ->workouts()
            ->with(['exercises.exercise', 'exercises.sets', 'routine'])
            ->latest()
            ->paginate(20);

        return WorkoutResource::collection($workouts);
    }

    public function store(StoreWorkoutRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($active = $this->sessions->activeFor($user)) {
            return (new WorkoutResource(
                $active->load(['exercises.exercise', 'exercises.sets', 'routine'])
            ))->response()->setStatusCode(200);
        }

        $workout = $this->sessions->start(
            $user,
            $request->validated('routine_id'),
            $request->validated('started_at'),
            $request->validated('notes'),
        );

        return (new WorkoutResource(
            $workout->load(['exercises.exercise', 'exercises.sets', 'routine'])
        ))->response()->setStatusCode(201);
    }

    public function show(Request $request, Workout $workout): WorkoutResource
    {
        $this->authorize('view', $workout);

        return new WorkoutResource(
            $workout->load(['exercises.exercise', 'exercises.sets', 'routine'])
        );
    }

    public function update(Request $request, Workout $workout): WorkoutResource
    {
        $this->authorize('update', $workout);

        $validated = $request->validate([
            'routine_id' => ['nullable', Rule::exists('routines', 'id')->where('user_id', $request->user()->id)],
            'started_at' => ['sometimes', 'date'],
            'ended_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->sessions->update($request->user(), $workout, $validated);

        return new WorkoutResource(
            $workout->load(['exercises.exercise', 'exercises.sets', 'routine'])
        );
    }

    public function addExercise(Request $request, Workout $workout): JsonResponse
    {
        $this->authorize('update', $workout);

        $validated = $request->validate([
            'exercise_id' => ['nullable', 'integer', 'exists:exercises,id', 'required_without:exercise_name'],
            'exercise_name' => ['nullable', 'string', 'max:255', 'required_without:exercise_id'],
        ]);

        $workoutExercise = $this->sessions->addExercise(
            $request->user(),
            $workout,
            $validated['exercise_id'] ?? null,
            $validated['exercise_name'] ?? null,
        );

        return (new WorkoutExerciseResource($workoutExercise->load(['exercise', 'sets'])))
            ->response()
            ->setStatusCode(201);
    }

    public function logSet(Request $request, WorkoutExercise $workoutExercise): JsonResponse
    {
        $this->authorize('update', $workoutExercise->workout);

        $validated = $request->validate([
            'set_number' => ['nullable', 'integer', 'min:1'],
            'weight' => ['nullable', 'numeric'],
            'reps' => ['nullable', 'integer'],
            'rpe' => ['nullable', 'numeric'],
            'completed' => ['nullable', 'boolean'],
        ]);

        $set = $this->sessions->logSet($request->user(), $workoutExercise, $validated);

        return (new WorkoutSetResource($set))->response()->setStatusCode(200);
    }

    public function destroy(Request $request, Workout $workout): JsonResponse
    {
        $this->authorize('delete', $workout);

        $this->sessions->delete($request->user(), $workout);

        return response()->json(['message' => 'Workout deleted']);
    }
}
