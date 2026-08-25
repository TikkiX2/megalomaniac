<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response|JsonResponse
    {
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(Exercise::orderBy('name')->get());
        }

        $user = auth()->user();

        $activeWorkout = $user->workouts()
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if ($activeWorkout) {
            $activeWorkout = (new WorkoutController)->loadWorkoutWithHistory($activeWorkout);
        }

        // Weekly volume + streak for gym-routine (last 7 days)
        $start = now()->copy()->subDays(6)->startOfDay();
        $weeklyWorkouts = $user->workouts()->with('exercises.sets')
            ->where('started_at', '>=', $start)
            ->get();

        $volMap = [];
        $labels = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i);
            $key = $d->toDateString();
            $volMap[$key] = 0;
            $labels[$key] = $d->format('D');
        }
        $datesWithWorkout = [];
        foreach ($weeklyWorkouts as $w) {
            $key = $w->started_at->toDateString();
            if (! isset($volMap[$key])) {
                continue;
            }
            $vol = 0;
            foreach ($w->exercises as $we) {
                foreach ($we->sets as $set) {
                    $weight = is_numeric($set->weight) ? (float) $set->weight : 0;
                    $reps = is_numeric($set->reps) ? (int) $set->reps : 0;
                    if ($weight > 0 && $reps > 0) {
                        $vol += $weight * $reps;
                    }
                }
            }
            $volMap[$key] += $vol;
            $datesWithWorkout[$key] = true;
        }
        $weeklyVolumeByDay = [];
        $weeklyVolumes = [];
        foreach ($volMap as $date => $vol) {
            $weeklyVolumes[] = $vol;
            $weeklyVolumeByDay[] = ['date' => $date, 'label' => $labels[$date], 'volume' => $vol];
        }
        $streakDays = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $start->copy()->addDays($i);
            $key = $d->toDateString();
            $has = isset($datesWithWorkout[$key]);
            array_unshift($streakDays, ['date' => $key, 'label' => $labels[$key], 'hasWorkout' => $has]);
        }
        $currentStreak = 0;
        for ($i = 6; $i >= 0; $i--) {
            $d = $start->copy()->addDays($i);
            $key = $d->toDateString();
            if (isset($datesWithWorkout[$key])) {
                $currentStreak++;
            } else {
                break;
            }
        }

        return Inertia::render('fitness/gym-routine', [
            'exercises' => Exercise::orderBy('name')->get(),
            'routines' => $user->routines()->with('exercises')->get(),
            'activeWorkout' => $activeWorkout,
            'suggestedRoutine' => $user->routines()
                ->where('scheduled_date', now()->format('l'))
                ->with('exercises')
                ->first(),
            'weeklyVolumeByDay' => $weeklyVolumeByDay,
            'weeklyVolumes' => $weeklyVolumes,
            'weeklyVolumeTotal' => array_sum($weeklyVolumes),
            'streak' => ['current' => $currentStreak, 'days' => $streakDays],
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

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($exercise, 201);
        }

        return redirect()->back();
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
