<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreProjectTaskRequest;
use App\Http\Resources\ProjectTaskResource;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Services\TaskBoardColumnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectTaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ProjectTask::query()
            ->whereHas('project', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                    ->where('type', 'freelance');
            })
            ->with('properties');

        if ($request->has('project_id')) {
            $query->where('project_id', $request->get('project_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('search')) {
            $query->where('title', 'like', '%'.$request->get('search').'%');
        }

        $tasks = $query->latest()->paginate(20);

        return ProjectTaskResource::collection($tasks);
    }

    public function store(StoreProjectTaskRequest $request): JsonResponse
    {
        $project = Project::where('id', $request->get('project_id'))
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($project, 403);

        $columns = TaskBoardColumnService::columnsFor($project, $request->user());
        $column = $columns->firstWhere('key', $request->validated('status') ?? null) ?? $columns->first();

        $task = ProjectTask::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status' => $column->key,
            'is_done' => $column->is_done,
        ]);

        return (new ProjectTaskResource($task->load('properties')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProjectTask $task, Request $request): ProjectTaskResource
    {
        abort_unless(
            $task->project && $task->project->user_id === $request->user()->id,
            403
        );

        return new ProjectTaskResource($task->load('properties'));
    }

    public function update(StoreProjectTaskRequest $request, ProjectTask $task): JsonResponse
    {
        abort_unless(
            $task->project && $task->project->user_id === $request->user()->id,
            403
        );

        $task->update($request->validated());

        return new ProjectTaskResource($task->fresh('properties'));
    }

    public function destroy(ProjectTask $task, Request $request): JsonResponse
    {
        abort_unless(
            $task->project && $task->project->user_id === $request->user()->id,
            403
        );

        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }
}
