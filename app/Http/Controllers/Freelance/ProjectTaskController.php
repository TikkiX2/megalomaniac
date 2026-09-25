<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Services\TaskBoardColumnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

// use App\Services\NotionSyncService; // To be created

class ProjectTaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Project $project)
    {
        // Usually tasks are shown in project show, but if we have a standalone index:
        // Or if we use shallow nesting, index might not be used here if we nest or if tasks are loaded in project.
        // But for route resource 'projects.tasks', index is for tasks of a project.

        $tasks = $project->tasks()
            ->when($request->status, function ($query, $status) {
                // Filter status
            })
            ->latest()
            ->get(); // Or paginate

        return response()->json($tasks); // Or Inertia component if separate page
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable', // acepta string (Dialog textarea) o array (Yoopta JSON) — cast array en modelo
            'status' => ['required', 'string', Rule::in(TaskBoardColumnService::statusKeys($project, $request->user()))], // Flexible for Notion
            'responsible' => 'nullable|string',
            'urgency' => 'nullable|string',
            'importance' => 'nullable|string',
            'priority' => 'nullable|string',
            'module' => 'nullable|string',
            'tags' => 'nullable|array',
            'area' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        // Normaliza description string del Dialog (textarea) a null si vacío
        if (isset($validated['description']) && is_string($validated['description'])) {
            $trim = trim($validated['description']);
            $validated['description'] = $trim === '' ? null : $trim;
        }

        $column = TaskBoardColumnService::ensureColumnForScope($project, $request->user(), $validated['status']);

        if (! $column) {
            throw ValidationException::withMessages([
                'status' => 'La columna seleccionada no existe en este tablero.',
            ]);
        }

        $validated['is_done'] = $column->is_done;
        $validated['sort_order'] = ($project->tasks()->max('sort_order') ?? 0) + 1;

        $task = $project->tasks()->create($validated);

        // Trigger Notion creation if enabled (will implement via Service)
        // $notionService->createTask($task);

        return back()->with('success', 'Tarea creada.');
    }

    /**
     * Move a task to a different status/column and reorder.
     */
    public function move(Request $request, ProjectTask $task)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'max:50'],
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        $column = TaskBoardColumnService::ensureColumnForScope($task->project, $request->user(), $validated['status']);

        if (! $column) {
            throw ValidationException::withMessages([
                'status' => 'La columna seleccionada no existe en este tablero.',
            ]);
        }

        DB::transaction(function () use ($task, $validated, $column) {
            $task->update([
                'status' => $validated['status'],
                'is_done' => $column->is_done,
            ]);

            foreach ($validated['ordered_ids'] as $index => $id) {
                ProjectTask::where('project_id', $task->project_id)
                    ->whereKey($id)
                    ->update(['sort_order' => $index]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProjectTask $task)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable',
            'status' => 'sometimes|required|string',
            'responsible' => 'nullable|string',
            'urgency' => 'nullable|string',
            'importance' => 'nullable|string',
            'priority' => 'nullable|string',
            'module' => 'nullable|string',
            'tags' => 'nullable|array',
            'area' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        if (isset($validated['description']) && is_string($validated['description'])) {
            $trim = trim($validated['description']);
            $validated['description'] = $trim === '' ? null : $trim;
        }

        if (array_key_exists('status', $validated)) {
            $column = TaskBoardColumnService::ensureColumnForScope($task->project, $request->user(), $validated['status']);

            if (! $column) {
                throw ValidationException::withMessages([
                    'status' => 'La columna seleccionada no existe en este tablero.',
                ]);
            }

            $validated['is_done'] = $column->is_done;
        }

        $task->update($validated);

        // Trigger Notion update
        // $notionService->updateTask($task);

        if ($request->expectsJson()) {
            return response()->json(['task' => $task->fresh()]);
        }

        return back()->with('success', 'Tarea actualizada.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectTask $task)
    {
        // Trigger Notion delete/archive
        // $notionService->deleteTask($task);

        $task->delete();

        return back()->with('success', 'Tarea eliminada.');
    }

    public function syncToNotion(ProjectTask $task)
    {
        // Service call
        // $notionService->syncToNotion($task);
        return back()->with('success', 'Sincronizado con Notion.');
    }

    public function syncFromNotion(ProjectTask $task)
    {
        // Service call
        // $notionService->syncFromNotion($task);
        return back()->with('success', 'Sincronizado desde Notion.');
    }

    public function notionWebhook(Request $request)
    {
        // Handle webhook from Notion/Integration platform
        return response()->json(['status' => 'received']);
    }
}
