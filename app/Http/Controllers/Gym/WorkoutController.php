<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Workout;
use Illuminate\Http\Request;

class WorkoutController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return Workout::with('routine')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('started_at')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'routine_id' => 'nullable|exists:routines,id',
            'started_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        // Check if there's already an active workout
        $activeWorkout = $request->user()->workouts()->whereNull('ended_at')->first();
        if ($activeWorkout) {
            return response()->json($activeWorkout->load(['routine', 'exercises.sets', 'exercises.exercise']));
        }

        $workout = $request->user()->workouts()->create([
            'routine_id' => $validated['routine_id'] ?? null,
            'started_at' => $validated['started_at'] ?? now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        // If routine is provided, copy exercises and pre-fill sets from history/targets
        if ($workout->routine_id) {
            $routine = \App\Models\Routine::with('exercises')->find($workout->routine_id);
            foreach ($routine->exercises as $exercise) {
                $workoutExercise = $workout->exercises()->create([
                    'exercise_id' => $exercise->id,
                    'order' => $exercise->pivot->order ?? 0,
                ]);

                // Find previous sets for this exercise
                $previousExercise = \App\Models\WorkoutExercise::whereHas('workout', function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id)->whereNotNull('ended_at');
                })
                    ->where('exercise_id', $exercise->id)
                    ->latest()
                    ->with('sets')
                    ->first();

                $targetSets = $exercise->pivot->target_sets ?? 3;

                for ($i = 1; $i <= $targetSets; $i++) {
                    $prevSet = $previousExercise ? $previousExercise->sets->where('set_number', $i)->first() : null;

                    $workoutExercise->sets()->create([
                        'set_number' => $i,
                        'weight' => $prevSet ? $prevSet->weight : ($exercise->pivot->target_weight ?? null),
                        'reps' => $prevSet ? $prevSet->reps : ($exercise->pivot->target_reps ?? null),
                        'completed' => false,
                    ]);
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json($this->loadWorkoutWithHistory($workout), 201);
        }

        return redirect()->back();
    }

    public function loadWorkoutWithHistory(Workout $workout)
    {
        $workout->load(['routine', 'exercises.sets', 'exercises.exercise']);

        foreach ($workout->exercises as $exercise) {
            $previous = \App\Models\WorkoutExercise::whereHas('workout', function ($query) use ($workout) {
                $query->where('user_id', $workout->user_id)
                    ->whereNotNull('ended_at')
                    ->where('workouts.id', '!=', $workout->id);
            })
                ->where('exercise_id', $exercise->exercise_id)
                ->latest()
                ->with('sets')
                ->first();

            $exercise->previous = $previous ? $previous->sets->sortBy('set_number')->values() : null;
        }

        return $workout;
    }

    public function show(Workout $workout)
    {
        return $this->loadWorkoutWithHistory($workout);
    }

    public function update(Request $request, Workout $workout)
    {
        $validated = $request->validate([
            'ended_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $workout->update($validated);

        if ($request->wantsJson()) {
            return $this->loadWorkoutWithHistory($workout);
        }

        return redirect()->back();
    }

    public function addExercise(Request $request, Workout $workout)
    {
        $validated = $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
        ]);

        $maxOrder = $workout->exercises()->max('order') ?? 0;

        $workoutExercise = $workout->exercises()->create([
            'exercise_id' => $validated['exercise_id'],
            'order' => $maxOrder + 1,
        ]);

        if ($request->wantsJson()) {
            return response()->json($workoutExercise->load(['exercise', 'sets']), 201);
        }

        return redirect()->back();
    }

    public function logSet(Request $request, \App\Models\WorkoutExercise $workoutExercise)
    {
        $validated = $request->validate([
            'set_number' => 'required|integer',
            'weight' => 'nullable|numeric',
            'reps' => 'nullable|integer',
            'rpe' => 'nullable|numeric',
            'completed' => 'nullable|boolean',
        ]);

        $set = $workoutExercise->sets()->updateOrCreate(
            ['set_number' => $validated['set_number']],
            $validated
        );

        if ($request->wantsJson()) {
            return response()->json($set, 200);
        }

        return redirect()->back();
    }

    public function destroy(Workout $workout)
    {
        $workout->delete();

        return response()->noContent();
    }
}
