<?php

namespace App\Http\Controllers\Hoy;

use App\Http\Controllers\Controller;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SemanaController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $pool = ProjectTask::where('user_id', $userId)->enSemana()->orderBy('sort_order')->get(['id', 'title']);

        return Inertia::render('hoy/Semana', ['pool' => $pool]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['tarea_id' => ['required', 'exists:project_tasks,id']]);
        $task = ProjectTask::where('id', $data['tarea_id'])->where('user_id', $request->user()->id)->firstOrFail();
        $task->update(['en_semana' => true]);

        return back()->with('success', 'Agregada al pool.');
    }

    public function destroy(Request $request, ProjectTask $task)
    {
        abort_if($task->user_id !== $request->user()->id, 403);
        $task->update(['en_semana' => false]);

        return back()->with('success', 'Sacada del pool.');
    }

    public function buscar(Request $request)
    {
        $q = $request->get('q', '');
        $tasks = ProjectTask::where('user_id', $request->user()->id)
            ->noArchivadas()
            ->where('en_semana', false)
            ->when($q, fn ($query) => $query->where('title', 'like', "%{$q}%"))
            ->limit(15)
            ->get(['id', 'title']);

        return response()->json($tasks);
    }
}
