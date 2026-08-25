<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreWorkoutRequest;
use App\Http\Resources\WorkoutResource;
use App\Models\Workout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkoutController extends Controller
{
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
        $workout = $request->user()->workouts()->create(
            $request->validated()
        );

        $resource = new WorkoutResource(
            $workout->load(['exercises.exercise', 'exercises.sets', 'routine'])
        );

        return $resource->response()->setStatusCode(201);
    }

    public function show(Request $request, Workout $workout): WorkoutResource
    {
        abort_if(
            $workout->user_id !== $request->user()->id,
            403,
            'Unauthorized.'
        );

        return new WorkoutResource(
            $workout->load(['exercises.exercise', 'exercises.sets', 'routine'])
        );
    }

    public function update(Request $request, Workout $workout): WorkoutResource
    {
        abort_if(
            $workout->user_id !== $request->user()->id,
            403,
            'Unauthorized.'
        );

        $workout->update($request->validate([
            'routine_id' => ['nullable', 'exists:routines,id'],
            'started_at' => ['sometimes', 'date'],
            'ended_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]));

        return new WorkoutResource(
            $workout->load(['exercises.exercise', 'exercises.sets', 'routine'])
        );
    }

    public function destroy(Request $request, Workout $workout): JsonResponse
    {
        abort_if(
            $workout->user_id !== $request->user()->id,
            403,
            'Unauthorized.'
        );

        $workout->delete();

        return response()->json(['message' => 'Workout deleted']);
    }
}
