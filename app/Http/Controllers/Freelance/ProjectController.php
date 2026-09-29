<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Project;
use App\Services\Projects\ProjectService;
use App\Services\TaskBoardColumnService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $projects = Project::query()
            ->with(['client', 'currency'])
            ->where('user_id', $request->user()->id)
            ->where('type', 'freelance')
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('freelance/projects/Index', [
            'projects' => $projects,
            'filters' => $request->only(['status', 'search']),
            'clients' => Client::where('user_id', $request->user()->id)->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('freelance/projects/Form', [
            'clients' => Client::where('user_id', auth()->id())->get(),
            'currencies' => Currency::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, ProjectService $projects)
    {
        $validated = $request->validate([
            'type' => 'sometimes|in:personal,freelance',
            'client_id' => 'nullable|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|array', // Rich text JSON
            'status' => 'required|in:pending,in_progress,completed,cancelled,maintenance',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'deadline' => 'nullable|date',
            'currency_id' => 'nullable|exists:currencies,id',
            'hourly_rate' => 'nullable|numeric|min:0',
            'estimated_hours' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',
            'area' => 'nullable|string',
            'module' => 'nullable|string',
            'priority' => 'nullable|string',
            'urgency' => 'nullable|string',
            'importance' => 'nullable|string',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $type = $validated['type'] ?? 'freelance';

        if ($type === 'freelance' && empty($validated['client_id'])) {
            throw ValidationException::withMessages([
                'client_id' => 'Los proyectos freelance requieren un cliente.',
            ]);
        }

        $project = $projects->create($request->user(), [
            ...$validated,
            'type' => $type,
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $project->addMedia($file)->toMediaCollection('attachments');
            }
        }

        $route = $project->type === 'freelance' ? 'freelance.projects.show' : 'personal.projects.show';

        return redirect()->route($route, $project)
            ->with('success', 'Proyecto creado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        $this->authorize('view', $project);
        abort_if($project->type !== 'freelance', 404);

        $project->load(['client', 'currency', 'tasks', 'payments.income', 'comments.user', 'media']);

        // Calculate totals or stats if needed

        return Inertia::render('freelance/projects/Show', [
            'project' => $project,
            'boardColumns' => TaskBoardColumnService::columnsFor($project, $project->user)->values(),
            'currencies' => Currency::all(),
            'clients' => Client::where('user_id', $project->user_id)->orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project)
    {
        $this->authorize('view', $project);
        abort_if($project->type !== 'freelance', 404);

        return Inertia::render('freelance/projects/Form', [
            'project' => $project,
            'clients' => Client::where('user_id', auth()->id())->get(),
            'currencies' => Currency::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project, ProjectService $projects)
    {
        $this->authorize('update', $project);
        abort_if($project->type !== 'freelance', 404);

        $validated = $request->validate([
            'type' => 'sometimes|in:personal,freelance',
            'client_id' => 'nullable|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|array',
            'status' => 'required|in:pending,in_progress,completed,cancelled,maintenance',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'deadline' => 'nullable|date',
            'currency_id' => 'nullable|exists:currencies,id',
            'hourly_rate' => 'nullable|numeric|min:0',
            'estimated_hours' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',
            'area' => 'nullable|string',
            'module' => 'nullable|string',
            'priority' => 'nullable|string',
            'urgency' => 'nullable|string',
            'importance' => 'nullable|string',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $type = $validated['type'] ?? $project->type;

        if ($type === 'freelance' && empty($validated['client_id'] ?? $project->client_id)) {
            throw ValidationException::withMessages([
                'client_id' => 'Los proyectos freelance requieren un cliente.',
            ]);
        }

        $project = $projects->update($request->user(), $project, $validated);

        $route = $project->type === 'freelance' ? 'freelance.projects.show' : 'personal.projects.show';

        return redirect()->route($route, $project)
            ->with('success', 'Proyecto actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);
        abort_if($project->type !== 'freelance', 404);

        $project->delete();

        return redirect()->route('freelance.projects.index')
            ->with('success', 'Proyecto eliminado exitosamente.');
    }

    public function uploadFile(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        abort_if($project->type !== 'freelance', 404);

        $request->validate([
            'file' => 'required|file|max:10240', // 10MB
        ]);

        $project->addMediaFromRequest('file')->toMediaCollection('attachments');

        return back()->with('success', 'Archivo subido.');
    }

    public function downloadFile($mediaId)
    {
        $media = Media::findOrFail($mediaId);
        $project = $media->model;

        abort_unless($project instanceof Project, 404);
        $this->authorize('view', $project);

        return response()->download($media->getPath(), $media->file_name);
    }

    public function deleteFile($mediaId)
    {
        $media = Media::findOrFail($mediaId);
        $project = $media->model;

        abort_unless($project instanceof Project, 404);
        $this->authorize('update', $project);

        $media->delete();

        return back()->with('success', 'Archivo eliminado.');
    }

    public function addPayment(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        abort_if($project->type !== 'freelance', 404);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'expected_date' => 'nullable|date',
            'payment_method' => 'required|string',
            'status' => 'required|in:pending,received,delayed',
            'notes' => 'nullable|string',
        ]);

        $payment = $project->payments()->create($validated);

        // Income creation handled by ProjectPayment observer if status is 'received'
        // OR we can handle it here explicitly if logic is complex.

        return back()->with('success', 'Pago registrado.');
    }
}
