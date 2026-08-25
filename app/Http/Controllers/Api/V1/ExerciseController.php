<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreExerciseRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExerciseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Exercise::query();

        if ($request->has('muscle_group')) {
            $query->where('muscle_group', $request->get('muscle_group'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->get('search').'%');
        }

        $exercises = $query->latest()->paginate(20);

        return ExerciseResource::collection($exercises);
    }

    public function store(StoreExerciseRequest $request): JsonResponse
    {
        $exercise = Exercise::create($request->validated());

        return (new ExerciseResource($exercise))
            ->response()
            ->setStatusCode(201);
    }
}
