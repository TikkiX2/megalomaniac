<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\Routine;
use App\Services\Gym\RoutineService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoutineController extends Controller
{
    public function __construct(protected RoutineService $routines) {}

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

        return Inertia::render('fitness/routines', [
            'routines' => $routines,
            'exercises' => Exercise::all(),
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

        $routine = $this->routines->create($request->user(), $validated);

        if ($request->wantsJson()) {
            return response()->json($routine->load('exercises'), 201);
        }

        return redirect()->back();
    }

    public function show(Request $request, Routine $routine)
    {
        $this->authorize('view', $routine);

        return $routine->load('exercises');
    }

    public function update(Request $request, Routine $routine)
    {
        $this->authorize('update', $routine);

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

        $routine = $this->routines->update($request->user(), $routine, $validated);

        if ($request->wantsJson()) {
            return response()->json($routine->load('exercises'), 200);
        }

        return redirect()->back();
    }

    public function destroy(Request $request, Routine $routine)
    {
        $this->authorize('delete', $routine);

        $this->routines->delete($request->user(), $routine);

        return response()->noContent();
    }
}
