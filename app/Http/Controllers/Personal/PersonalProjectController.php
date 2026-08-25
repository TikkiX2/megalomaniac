<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personal\StorePersonalProjectRequest;
use App\Http\Requests\Personal\UpdatePersonalProjectRequest;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Project;
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

    public function store(StorePersonalProjectRequest $request)
    {
        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;
        $validated['type'] = 'personal';

        // Auto-assign client and currency for personal projects if not provided
        if (empty($validated['client_id'])) {
            $client = Client::firstOrCreate(
                ['user_id' => $request->user()->id, 'name' => 'Personal'],
                ['email' => null, 'phone' => null]
            );
            $validated['client_id'] = $client->id;
        }
        if (empty($validated['currency_id'])) {
            $currency = Currency::first();
            $validated['currency_id'] = $currency?->id ?? 1;
        }

        $project = Project::create($validated);

        return redirect()->route('personal.projects.show', $project)->with('success', 'Proyecto creado.');
    }

    public function create()
    {
        return Inertia::render('personal/projects/Form');
    }

    public function show(Project $project)
    {
        abort_if($project->type !== 'personal', 404);
        abort_if($project->user_id !== auth()->id(), 403);

        $project->load(['tasks.properties', 'milestones', 'members.user', 'client', 'currency']);
        $project->append('progress');
        $project->loadCount('tasks');

        return Inertia::render('personal/projects/Show', [
            'project' => $project,
        ]);
    }

    public function edit(Project $project)
    {
        abort_if($project->type !== 'personal', 404);
        abort_if($project->user_id !== auth()->id(), 403);

        return Inertia::render('personal/projects/Form', [
            'project' => $project,
        ]);
    }

    public function update(UpdatePersonalProjectRequest $request, Project $project)
    {
        abort_if($project->type !== 'personal', 404);
        abort_if($project->user_id !== auth()->id(), 403);

        $project->update($request->validated());

        return back()->with('success', 'Proyecto actualizado.');
    }

    public function destroy(Project $project)
    {
        abort_if($project->type !== 'personal', 404);
        abort_if($project->user_id !== auth()->id(), 403);

        $project->delete();

        return redirect()->route('personal.projects.index')->with('success', 'Proyecto eliminado.');
    }

    public function storeMilestone(Request $request, Project $project)
    {
        abort_if($project->type !== 'personal', 404);
        abort_if($project->user_id !== auth()->id(), 403);

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
