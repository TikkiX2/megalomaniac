<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $user = auth()->user();

        $activeWorkout = $user->workouts()
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if ($activeWorkout) {
            $activeWorkout = (new WorkoutController())->loadWorkoutWithHistory($activeWorkout);
        }

        return Inertia::render('fitness/gym-routine', [
            'exercises' => Exercise::orderBy('name')->get(),
            'routines' => $user->routines()->with('exercises')->get(),
            'activeWorkout' => $activeWorkout,
            'suggestedRoutine' => $user->routines()
                ->where('scheduled_date', now()->format('l'))
                ->with('exercises')
                ->first(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'muscle_group' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'muscle_wiki_id' => 'nullable|integer',
            'video_url' => 'nullable|url',
        ]);

        $exercise = Exercise::create($validated);

        return response()->json($exercise, 201);
    }

    public function show(Exercise $exercise)
    {
        return $exercise;
    }

    public function update(Request $request, Exercise $exercise)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'muscle_group' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'muscle_wiki_id' => 'nullable|integer',
            'video_url' => 'nullable|url',
        ]);

        $exercise->update($validated);

        return $exercise;
    }

    public function destroy(Exercise $exercise)
    {
        $exercise->delete();

        return response()->noContent();
    }
}
