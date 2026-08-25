<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreRoutineRequest;
use App\Http\Resources\RoutineResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoutineController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $routines = $request->user()
            ->routines()
            ->with('exercises')
            ->latest()
            ->paginate(20);

        return RoutineResource::collection($routines);
    }

    public function store(StoreRoutineRequest $request): JsonResponse
    {
        $routine = $request->user()->routines()->create(
            $request->validated()
        );

        if ($request->has('exercise_ids')) {
            $exerciseData = collect($request->validated('exercise_ids'))
                ->mapWithKeys(fn ($id, $index) => [$id => ['order' => $index + 1]]);

            $routine->exercises()->attach($exerciseData);
        }

        return (new RoutineResource($routine->load('exercises')))
            ->response()
            ->setStatusCode(201);
    }
}
