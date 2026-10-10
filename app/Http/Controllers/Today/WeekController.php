<?php

namespace App\Http\Controllers\Today;

use App\Http\Controllers\Controller;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WeekController extends Controller
{
    public function index(Request $request)
    {
        $pool = ProjectTask::where('user_id', $request->user()->id)
            ->inWeek()
            ->notDone()
            ->notArchived()
            ->orderBy('sort_order')
            ->get(['id', 'title']);

        return Inertia::render('today/Semana', [
            'pool' => $pool,
            'overloaded' => $pool->count() > 7,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['tarea_id' => ['required', 'exists:project_tasks,id']]);
        $task = ProjectTask::where('id', $data['tarea_id'])->where('user_id', $request->user()->id)->firstOrFail();
        $task->update(['in_week' => true]);

        return back()->with('success', 'Agregada al pool.');
    }

    public function destroy(Request $request, ProjectTask $task)
    {
        abort_if($task->user_id !== $request->user()->id, 403);
        $task->update(['in_week' => false]);

        return back()->with('success', 'Sacada del pool.');
    }

    public function search(Request $request)
    {
        $q = $request->get('q', '');
        $tasks = ProjectTask::where('user_id', $request->user()->id)
            ->notArchived()
            ->notDone()
            ->where('in_week', false)
            ->when($q, fn ($query) => $query->where('title', 'like', "%{$q}%"))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'title']);

        return response()->json($tasks);
    }
}
