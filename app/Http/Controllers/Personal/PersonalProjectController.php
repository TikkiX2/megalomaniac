<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personal\StorePersonalProjectRequest;
use App\Http\Requests\Personal\UpdatePersonalProjectRequest;
use App\Models\Client;
use App\Models\Project;
use App\Services\Projects\ProjectService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PersonalProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'personal')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->withCount('tasks')
            ->with(['milestones', 'members.user'])
            ->orderBy('is_archived')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        // Append progress attribute
        $projects->getCollection()->transform(function ($project) {
            $project->append('progress');

            return $project;
        });

        return Inertia::render('personal/projects/Index', [
            'projects' => $projects,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function store(StorePersonalProjectRequest $request, ProjectService $projects)
    {
        $validated = $request->validated();

        $project = $projects->create($request->user(), [
            ...$validated,
            'type' => $validated['type'] ?? 'personal',
        ]);

        $route = $project->type === 'freelance' ? 'freelance.projects.show' : 'personal.projects.show';

        return redirect()->route($route, $project)->with('success', 'Proyecto creado.');
    }

    public function create()
    {
        return Inertia::render('personal/projects/Form', [
            'clients' => Client::where('user_id', auth()->id())->orderBy('name')->get(),
        ]);
    }

    public function show(Project $project)
    {
        abort_if($project->type !== 'personal', 404);
        $this->authorize('view', $project);

        $project->load(['tasks.properties', 'milestones', 'members.user', 'client', 'currency']);
        $project->append('progress');
        $project->loadCount('tasks');

        return Inertia::render('personal/projects/Show', [
            'project' => $project,
            'clients' => Client::where('user_id', auth()->id())->orderBy('name')->get(),
        ]);
    }

    public function edit(Project $project)
    {
        abort_if($project->type !== 'personal', 404);
        $this->authorize('view', $project);

        return Inertia::render('personal/projects/Form', [
            'project' => $project,
            'clients' => Client::where('user_id', auth()->id())->orderBy('name')->get(),
        ]);
    }

    public function update(UpdatePersonalProjectRequest $request, Project $project, ProjectService $projects)
    {
        abort_if($project->type !== 'personal', 404);
        $this->authorize('update', $project);

        $project = $projects->update($request->user(), $project, $request->validated());

        $route = $project->type === 'freelance' ? 'freelance.projects.show' : 'personal.projects.show';

        return redirect()->route($route, $project)->with('success', 'Proyecto actualizado.');
    }

    public function destroy(Project $project)
    {
        abort_if($project->type !== 'personal', 404);
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('personal.projects.index')->with('success', 'Proyecto eliminado.');
    }

    public function storeMilestone(Request $request, Project $project)
    {
        abort_if($project->type !== 'personal', 404);
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pending,completed,cancelled'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $project->milestones()->create($validated);

        return back()->with('success', 'Hito creado.');
    }
}
