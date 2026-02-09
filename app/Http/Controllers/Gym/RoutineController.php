<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Routine;
use Illuminate\Http\Request;

class RoutineController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $routines = Routine::with('exercises')
            ->where('user_id', $request->user()->id)
            ->get();

        if ($request->wantsJson()) {
            return $routines;
        }

        return \Inertia\Inertia::render('fitness/routines', [
            'routines' => $routines,
            'exercises' => \App\Models\Exercise::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'focus' => 'nullable|string|max:255',
            'scheduled_date' => 'nullable|string|max:255',
            'exercises' => 'nullable|array',
            'exercises.*.id' => 'nullable|exists:exercises,id',
            'exercises.*.name' => 'required_without:exercises.*.id|string|max:255',
            'exercises.*.muscle_group' => 'nullable|string|max:255',
            'exercises.*.type' => 'nullable|string|max:255',
            'exercises.*.target_sets' => 'nullable|integer',
            'exercises.*.target_reps' => 'nullable|string',
            'exercises.*.target_weight' => 'nullable|string',
            'exercises.*.notes' => 'nullable|string',
        ]);

        $routine = $request->user()->routines()->create([
            'name' => $validated['name'],
            'focus' => $validated['focus'] ?? null,
            'scheduled_date' => $validated['scheduled_date'] ?? null,
            'status' => 'active',
        ]);

        if (! empty($validated['exercises'])) {
            foreach ($validated['exercises'] as $index => $exerciseData) {
                $exerciseId = $exerciseData['id'] ?? null;

                // Create new exercise if ID is not provided
                if (! $exerciseId && ! empty($exerciseData['name'])) {
                    $exercise = \App\Models\Exercise::create([
                        'name' => $exerciseData['name'],
                        'muscle_group' => $exerciseData['muscle_group'] ?? null,
                        'type' => $exerciseData['type'] ?? null,
                    ]);
                    $exerciseId = $exercise->id;
                }

                if ($exerciseId) {
                    $routine->exercises()->attach($exerciseId, [
                        'order' => $index + 1,
                        'target_sets' => $exerciseData['target_sets'] ?? null,
                        'target_reps' => $exerciseData['target_reps'] ?? null,
                        'target_weight' => $exerciseData['target_weight'] ?? null,
                        'notes' => $exerciseData['notes'] ?? null,
                    ]);
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json($routine->load('exercises'), 201);
        }

        return redirect()->back();
    }

    public function show(Routine $routine)
    {
        return $routine->load('exercises');
    }

    public function update(Request $request, Routine $routine)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'focus' => 'nullable|string|max:255',
            'scheduled_date' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive,archived',
            'exercises' => 'nullable|array',
            'exercises.*.id' => 'nullable|exists:exercises,id',
            'exercises.*.name' => 'required_without:exercises.*.id|string|max:255',
            'exercises.*.muscle_group' => 'nullable|string|max:255',
            'exercises.*.type' => 'nullable|string|max:255',
            'exercises.*.target_sets' => 'nullable|integer',
            'exercises.*.target_reps' => 'nullable|string',
            'exercises.*.target_weight' => 'nullable|string',
            'exercises.*.notes' => 'nullable|string',
        ]);

        $routine->update([
            'name' => $validated['name'] ?? $routine->name,
            'focus' => $validated['focus'] ?? $routine->focus,
            'scheduled_date' => $validated['scheduled_date'] ?? $routine->scheduled_date,
            'status' => $validated['status'] ?? $routine->status,
        ]);

        if (isset($validated['exercises'])) {
            $syncData = [];
            foreach ($validated['exercises'] as $index => $exerciseData) {
                $exerciseId = $exerciseData['id'] ?? null;

                // Create new exercise if ID is not provided
                if (! $exerciseId && ! empty($exerciseData['name'])) {
                    $exercise = \App\Models\Exercise::create([
                        'name' => $exerciseData['name'],
                        'muscle_group' => $exerciseData['muscle_group'] ?? null,
                        'type' => $exerciseData['type'] ?? null,
                    ]);
                    $exerciseId = $exercise->id;
                }

                if ($exerciseId) {
                    $syncData[$exerciseId] = [
                        'order' => $index + 1,
                        'target_sets' => $exerciseData['target_sets'] ?? null,
                        'target_reps' => $exerciseData['target_reps'] ?? null,
                        'target_weight' => $exerciseData['target_weight'] ?? null,
                        'notes' => $exerciseData['notes'] ?? null,
                    ];
                }
            }
            $routine->exercises()->sync($syncData);
        }

        if ($request->wantsJson()) {
            return response()->json($routine->load('exercises'), 200);
        }

        return redirect()->back();
    }

    public function destroy(Routine $routine)
    {
        $routine->delete();

        return response()->noContent();
    }
}
