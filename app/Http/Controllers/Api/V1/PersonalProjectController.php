<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StorePersonalProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\TaskMilestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonalProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Project::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'personal')
            ->with(['tasks', 'milestones'])
            ->withCount('tasks');

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->get('search').'%');
        }

        $projects = $query->latest()->paginate(20);

        return ProjectResource::collection($projects);
    }

    public function store(StorePersonalProjectRequest $request): JsonResponse
    {
        $project = Project::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'type' => 'personal',
        ]);

        if ($request->has('milestones')) {
            foreach ($request->get('milestones') as $index => $milestone) {
                TaskMilestone::create([
                    ...$milestone,
                    'project_id' => $project->id,
                    'sort_order' => $milestone['sort_order'] ?? $index,
                ]);
            }
        }

        return (new ProjectResource($project->load(['tasks', 'milestones'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Project $project, Request $request): ProjectResource
    {
        abort_if($project->user_id !== $request->user()->id, 403);
        abort_if($project->type !== 'personal', 404);

        return new ProjectResource($project->load(['tasks', 'milestones']));
    }

    public function update(StorePersonalProjectRequest $request, Project $project): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);
        abort_if($project->type !== 'personal', 404);

        $project->update($request->validated());

        return new ProjectResource($project->fresh(['tasks', 'milestones']));
    }

    public function destroy(Project $project, Request $request): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);
        abort_if($project->type !== 'personal', 404);

        $project->delete();

        return response()->json(['message' => 'Personal project deleted.']);
    }

    public function storeMilestone(StorePersonalProjectRequest $request, Project $project): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);
        abort_if($project->type !== 'personal', 404);

        $validated = $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $milestone = TaskMilestone::create([
            ...$validated,
            'project_id' => $project->id,
            'status' => 'pending',
        ]);

        return (new ProjectResource($project->fresh('milestones')))
            ->response()
            ->setStatusCode(201);
    }
}
