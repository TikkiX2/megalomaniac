<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Project::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'freelance')
            ->with(['client', 'tasks', 'comments'])
            ->withCount('payments');

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->get('client_id'));
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->get('search').'%');
        }

        $projects = $query->latest()->paginate(20);

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'type' => 'freelance',
        ]);

        return (new ProjectResource($project->load(['client', 'tasks', 'comments'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Project $project, Request $request): ProjectResource
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        return new ProjectResource($project->load([
            'client', 'tasks', 'comments', 'payments', 'milestones',
        ]));
    }

    public function update(StoreProjectRequest $request, Project $project): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        $project->update($request->validated());

        return new ProjectResource($project->fresh(['client', 'tasks', 'comments']));
    }

    public function destroy(Project $project, Request $request): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    public function addPayment(Request $request, Project $project): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date'],
            'expected_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:pending,received,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = ProjectPayment::create([
            ...$validated,
            'project_id' => $project->id,
        ]);

        return (new ProjectResource($project->fresh(['client', 'tasks', 'comments', 'payments'])))
            ->response()
            ->setStatusCode(201);
    }
}
