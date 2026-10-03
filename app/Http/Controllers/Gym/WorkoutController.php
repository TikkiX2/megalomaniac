<?php

namespace App\Http\Controllers\Gym;

use App\Exceptions\WorkoutAlreadyActiveException;
use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use App\Services\Gym\PersonalRecordService;
use App\Services\Gym\WorkoutSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class WorkoutController extends Controller
{
    public function __construct(
        protected WorkoutSessionService $sessions,
        protected PersonalRecordService $records,
    ) {}

    public function index(Request $request)
    {
        return Workout::with('routine')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('started_at')
            ->get();
    }

    public function history(Request $request): Response
    {
        $workouts = Workout::with(['exercises.exercise', 'exercises.sets', 'routine'])
            ->where('user_id', $request->user()->id)
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('fitness/history', [
            'workouts' => $workouts,
            'personalRecords' => $this->records->timeline($request->user(), 20),
            'routines' => $request->user()->routines()->withCount('exercises')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'routine_id' => [
                'nullable',
                Rule::exists('routines', 'id')->where('user_id', $request->user()->id),
            ],
            'started_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        try {
            $workout = $this->sessions->start(
                $request->user(),
                $validated['routine_id'] ?? null,
                $validated['started_at'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (WorkoutAlreadyActiveException $e) {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'active_workout' => $this->loadWorkoutWithHistory($e->workout),
                ], 409);
            }

            return back()->withErrors(['workout' => 'Ya tienes un entrenamiento activo.']);
        }

        if ($this->wantsJson($request)) {
            return response()->json($this->loadWorkoutWithHistory($workout), 201);
        }

        return redirect()->back();
    }

    public function repeat(Request $request, Workout $workout)
    {
        $this->authorize('view', $workout);

        try {
            $workout = $this->sessions->repeat($request->user(), $workout);
        } catch (WorkoutAlreadyActiveException $e) {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'active_workout' => $this->loadWorkoutWithHistory($e->workout),
                ], 409);
            }

            return back()->withErrors(['workout' => 'Ya tenés un entrenamiento activo.']);
        } catch (InvalidArgumentException $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['message' => $e->getMessage()], 409);
            }

            return back()->withErrors(['workout' => 'Ya tenés un entrenamiento activo.']);
        }

        if ($this->wantsJson($request)) {
            return response()->json($this->loadWorkoutWithHistory($workout), 201);
        }

        return redirect()->back();
    }

    public function logPast(Request $request)
    {
        $validated = $request->validate([
            'routine_id' => [
                'nullable',
                Rule::exists('routines', 'id')->where('user_id', $request->user()->id),
            ],
            'started_at' => 'required|date|before_or_equal:now',
            'notes' => 'nullable|string',
        ]);

        $workout = $this->sessions->logPast(
            $request->user(),
            $validated['routine_id'] ?? null,
            $validated['started_at'],
            $validated['notes'] ?? null,
        );

        if ($this->wantsJson($request)) {
            return response()->json($this->loadWorkoutWithHistory($workout), 201);
        }

        return redirect()->back();
    }

    public function progression(Request $request, Exercise $exercise)
    {
        return response()->json($this->sessions->progressionFor($request->user(), $exercise));
    }

    public function loadWorkoutWithHistory(Workout $workout)
    {
        $workout->load(['routine', 'exercises.sets', 'exercises.exercise']);

        foreach ($workout->exercises as $exercise) {
            $previous = WorkoutExercise::whereHas('workout', function ($query) use ($workout) {
                $query->where('user_id', $workout->user_id)
                    ->whereNotNull('ended_at')
                    ->where('workouts.id', '!=', $workout->id);
            })
                ->where('exercise_id', $exercise->exercise_id)
                ->latest('id')
                ->with('sets')
                ->first();

            $exercise->setAttribute(
                'previous',
                $previous ? $previous->sets->sortBy('set_number')->values() : null,
            );

            $exercise->setAttribute('best_weight', $exercise->exercise
                ? $this->records->bestWeightFor($workout->user, $exercise->exercise)
                : null);

            $this->records->annotateSets($exercise->sets);
        }

        return $workout;
    }

    public function show(Request $request, Workout $workout)
    {
        $this->authorize('view', $workout);

        return $this->loadWorkoutWithHistory($workout);
    }

    public function update(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        $validated = $request->validate([
            'ended_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $this->sessions->update($request->user(), $workout, $validated);

        if ($this->wantsJson($request)) {
            return response()->json($this->loadWorkoutWithHistory($workout));
        }

        return redirect()->back();
    }

    public function addExercise(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        $validated = $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
        ]);

        $workoutExercise = $this->sessions->addExercise(
            $request->user(),
            $workout,
            $validated['exercise_id'],
        );

        if ($this->wantsJson($request)) {
            return response()->json($workoutExercise->load(['exercise', 'sets']), 201);
        }

        return redirect()->back();
    }

    public function logSet(Request $request, WorkoutExercise $workoutExercise)
    {
        $this->authorize('update', $workoutExercise->workout);

        $validated = $request->validate([
            'set_number' => 'required|integer',
            'weight' => 'nullable|numeric',
            'reps' => 'nullable|integer',
            'rpe' => 'nullable|numeric',
            'completed' => 'nullable|boolean',
        ]);

        $set = $this->sessions->logSet($request->user(), $workoutExercise, $validated);

        if ($this->wantsJson($request)) {
            return response()->json($set, 200);
        }

        return redirect()->back();
    }

    public function removeExercise(Request $request, WorkoutExercise $workoutExercise): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $workoutExercise->workout);

        $this->sessions->removeExercise($request->user(), $workoutExercise);

        if ($this->wantsJson($request)) {
            return response()->json(['message' => 'Exercise removed']);
        }

        return redirect()->back();
    }

    public function removeSet(Request $request, WorkoutSet $workoutSet): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $workoutSet->workoutExercise->workout);

        $this->sessions->removeSet($request->user(), $workoutSet);

        if ($this->wantsJson($request)) {
            return response()->json(['message' => 'Set removed']);
        }

        return redirect()->back();
    }

    public function destroy(Request $request, Workout $workout): JsonResponse|HttpResponse
    {
        $this->authorize('delete', $workout);

        $this->sessions->delete($request->user(), $workout);

        return response()->noContent();
    }

    private function wantsJson(Request $request): bool
    {
        return $request->wantsJson() && ! $request->header('X-Inertia');
    }
}
