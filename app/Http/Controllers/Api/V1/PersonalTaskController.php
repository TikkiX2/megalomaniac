<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StorePersonalTaskRequest;
use App\Http\Resources\ProjectTaskResource;
use App\Models\ProjectTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonalTaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ProjectTask::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('project_id')
            ->with('properties');

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('search')) {
            $query->where('title', 'like', '%'.$request->get('search').'%');
        }

        $tasks = $query->latest()->paginate(20);

        return ProjectTaskResource::collection($tasks);
    }

    public function store(StorePersonalTaskRequest $request): JsonResponse
    {
        $task = ProjectTask::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'project_id' => null,
        ]);

        return (new ProjectTaskResource($task->load('properties')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProjectTask $task, Request $request): ProjectTaskResource
    {
        abort_if($task->user_id !== $request->user()->id, 403);
        abort_if($task->project_id !== null, 404);

        return new ProjectTaskResource($task->load('properties'));
    }

    public function update(StorePersonalTaskRequest $request, ProjectTask $task): JsonResponse
    {
        abort_if($task->user_id !== $request->user()->id, 403);
        abort_if($task->project_id !== null, 404);

        $task->update($request->validated());

        return new ProjectTaskResource($task->fresh('properties'));
    }

    public function destroy(ProjectTask $task, Request $request): JsonResponse
    {
        abort_if($task->user_id !== $request->user()->id, 403);
        abort_if($task->project_id !== null, 404);

        $task->delete();

        return response()->json(['message' => 'Personal task deleted.']);
    }

    public function move(Request $request, ProjectTask $task): JsonResponse
    {
        abort_if($task->user_id !== $request->user()->id, 403);
        abort_if($task->project_id !== null, 404);

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $task->update($validated);

        return new ProjectTaskResource($task->fresh('properties'));
    }
}
