<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreRoutineRequest;
use App\Http\Requests\Api\UpdateRoutineRequest;
use App\Http\Resources\RoutineResource;
use App\Models\Routine;
use App\Services\Gym\RoutineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

class RoutineController extends Controller
{
    public function __construct(protected RoutineService $routines) {}

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
        $routine = $this->routines->create($request->user(), $this->routineData($request->validated()));

        return (new RoutineResource($routine->load('exercises')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateRoutineRequest $request, Routine $routine): RoutineResource
    {
        $this->authorize('update', $routine);

        $routine = $this->routines->update($request->user(), $routine, $this->routineData($request->validated()));

        return new RoutineResource($routine->load('exercises'));
    }

    public function destroy(Request $request, Routine $routine): HttpResponse
    {
        $this->authorize('delete', $routine);

        $this->routines->delete($request->user(), $routine);

        return response()->noContent();
    }

    /**
     * Map the API's exercise_ids contract onto the service's exercises array.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function routineData(array $validated): array
    {
        $exerciseIds = $validated['exercise_ids'] ?? null;
        unset($validated['exercise_ids']);

        if ($exerciseIds !== null) {
            $validated['exercises'] = collect($exerciseIds)
                ->map(fn ($id) => ['id' => $id])
                ->all();
        }

        return $validated;
    }
}
