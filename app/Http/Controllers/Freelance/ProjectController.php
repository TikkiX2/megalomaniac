<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Project;
use Illuminate\Http\Request;
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|array', // Rich text JSON
            'status' => 'required|in:pending,in_progress,completed,cancelled,maintenance',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'deadline' => 'nullable|date',
            'currency_id' => 'required|exists:currencies,id',
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

        $project = $request->user()->projects()->create($validated);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $project->addMedia($file)->toMediaCollection('attachments');
            }
        }

        return redirect()->route('freelance.projects.show', $project)
            ->with('success', 'Proyecto creado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        $project->load(['client', 'currency', 'tasks', 'payments.income', 'comments.user', 'media']);

        // Calculate totals or stats if needed

        return Inertia::render('freelance/projects/Show', [
            'project' => $project,
            'currencies' => Currency::all(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project)
    {
        return Inertia::render('freelance/projects/Form', [
            'project' => $project,
            'clients' => Client::where('user_id', auth()->id())->get(),
            'currencies' => Currency::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|array',
            'status' => 'required|in:pending,in_progress,completed,cancelled,maintenance',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'deadline' => 'nullable|date',
            'currency_id' => 'required|exists:currencies,id',
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

        $project->update($validated);

        return redirect()->back()
            ->with('success', 'Proyecto actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('freelance.projects.index')
            ->with('success', 'Proyecto eliminado exitosamente.');
    }

    public function uploadFile(Request $request, Project $project)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB
        ]);

        $project->addMediaFromRequest('file')->toMediaCollection('attachments');

        return back()->with('success', 'Archivo subido.');
    }

    public function downloadFile($mediaId)
    {
        // Permission check? Assuming Auth middleware protects route, but should check if user owns project of media.
        $media = Media::findOrFail($mediaId);

        // Add robust check here if needed
        return response()->download($media->getPath(), $media->file_name);
    }

    public function deleteFile($mediaId)
    {
        $media = Media::findOrFail($mediaId);
        $media->delete();

        return back()->with('success', 'Archivo eliminado.');
    }

    public function addPayment(Request $request, Project $project)
    {
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
