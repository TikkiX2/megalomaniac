<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personal\StorePersonalTaskRequest;
use App\Http\Requests\Personal\UpdatePersonalTaskRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskSavedView;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PersonalTaskController extends Controller
{
    public function index(Request $request)
    {
        $query = ProjectTask::query()
            ->where('user_id', $request->user()->id)
            ->where('is_archived', false)
            ->with(['project', 'properties'])
            ->when($request->project_id, fn ($q, $v) => $q->where('project_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->priority, fn ($q, $v) => $q->where('priority', $v))
            ->when($request->search, fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($request->due_from, fn ($q, $v) => $q->where('due_date', '>=', $v))
            ->when($request->due_to, fn ($q, $v) => $q->where('due_date', '<=', $v))
            ->when($request->tags, function ($q, $v) {
                $tags = is_array($v) ? $v : explode(',', $v);
                foreach ($tags as $tag) {
                    $q->where('tags', 'like', "%{$tag}%");
                }
            });

        // Sorting
        $sortField = $request->get('sort', 'sort_order');
        $sortDirection = $request->get('direction', 'asc');
        $allowedSorts = ['title', 'status', 'priority', 'due_date', 'start_date', 'sort_order', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }
        $query->orderBy('created_at', 'desc');

        $tasks = $query->paginate(50)->withQueryString();

        $projects = Project::where('user_id', $request->user()->id)
            ->where('type', 'personal')
            ->withCount('tasks')
            ->orderBy('sort_order')
            ->get()
            ->each(fn ($p) => $p->append('progress'));

        $savedViews = TaskSavedView::where('user_id', $request->user()->id)->orderBy('name')->get();

        return Inertia::render('personal/tasks/Index', [
            'tasks' => $tasks,
            'projects' => $projects,
            'savedViews' => $savedViews,
            'filters' => $request->only(['project_id', 'status', 'priority', 'search', 'due_from', 'due_to', 'tags', 'sort', 'direction']),
        ]);
    }

    public function store(StorePersonalTaskRequest $request)
    {
        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;

        // Handle project_id nullability - if provided, verify it belongs to user and is personal
        if (! empty($validated['project_id'])) {
            $project = Project::where('id', $validated['project_id'])
                ->where('user_id', $request->user()->id)
                ->where('type', 'personal')
                ->firstOrFail();
        }

        $properties = $validated['properties'] ?? null;
        unset($validated['properties']);

        // Normalize empty strings
        if (isset($validated['description']) && is_string($validated['description']) && trim($validated['description']) === '') {
            $validated['description'] = null;
        }

        $task = ProjectTask::create($validated);

        if ($properties) {
            foreach ($properties as $prop) {
                $task->properties()->create([
                    'key' => $prop['key'],
                    'type' => $prop['type'],
                    'value_text' => $prop['value_text'] ?? $prop['value'] ?? null,
                    'value_number' => $prop['value_number'] ?? (is_numeric($prop['value'] ?? null) ? $prop['value'] : null),
                    'value_date' => $prop['value_date'] ?? null,
                    'value_json' => $prop['value_json'] ?? (is_array($prop['value'] ?? null) ? $prop['value'] : null),
                ]);
            }
        }

        return back()->with('success', 'Tarea creada.');
    }

    public function show(ProjectTask $task)
    {
        abort_if($task->user_id !== auth()->id(), 403);

        $task->load(['project', 'properties']);

        $projects = Project::where('user_id', auth()->id())->where('type', 'personal')->get();

        return Inertia::render('personal/tasks/Show', [
            'task' => $task,
            'projects' => $projects,
        ]);
    }

    public function update(UpdatePersonalTaskRequest $request, ProjectTask $task)
    {
        abort_if($task->user_id !== auth()->id(), 403);

        $validated = $request->validated();

        if (isset($validated['description']) && is_string($validated['description']) && trim($validated['description']) === '') {
            $validated['description'] = null;
        }

        $task->update($validated);

        return back()->with('success', 'Tarea actualizada.');
    }

    public function destroy(ProjectTask $task)
    {
        abort_if($task->user_id !== auth()->id(), 403);

        $task->delete();

        return back()->with('success', 'Tarea eliminada.');
    }

    public function move(Request $request, ProjectTask $task)
    {
        abort_if($task->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'exists:projects,id'],
        ]);

        $task->update($validated);

        return back()->with('success', 'Tarea movida.');
    }
}
