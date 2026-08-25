<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
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
            'status' => 'required|string', // Flexible for Notion
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

        $task = $project->tasks()->create($validated);

        // Trigger Notion creation if enabled (will implement via Service)
        // $notionService->createTask($task);

        return back()->with('success', 'Tarea creada.');
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

        $task->update($validated);

        // Trigger Notion update
        // $notionService->updateTask($task);

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
