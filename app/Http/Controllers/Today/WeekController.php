<?php

namespace App\Http\Controllers\Today;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\ProjectTask;
use App\Services\Today\TaskFilterService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WeekController extends Controller
{
    public function index(Request $request, TaskFilterService $taskFilters)
    {
        $userId = $request->user()->id;
        $filters = $taskFilters->resolve($request, 'week');
        $limit = $this->resolveLimit($request);
        $options = $taskFilters->options($userId);

        $pool = ProjectTask::where('user_id', $userId)
            ->inWeek()
            ->notDone()
            ->notArchived()
            ->applyFilters($filters)
            ->with('project:id,name,color')
            ->orderBy('sort_order')
            ->get(['id', 'title', 'project_id']);

        $candidateRows = ProjectTask::where('user_id', $userId)
            ->notDone()
            ->notArchived()
            ->where('in_week', false)
            ->applyFilters($filters)
            ->with('project:id,name,color')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get(['id', 'title', 'created_at', 'project_id']);

        $hasMoreCandidates = $candidateRows->count() > $limit;
        $candidates = $candidateRows->take($limit)->values();

        $blocks = Block::where('user_id', $userId)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get(['id', 'label', 'weekday', 'start_time', 'duration_min', 'active']);

        return Inertia::render('today/Week', [
            'pool' => $pool,
            'overloaded' => $pool->count() > 7,
            'candidates' => $candidates,
            'blocks' => $blocks,
            'projects' => $options['projects'],
            'availableTags' => $options['tags'],
            'filters' => $filters,
            'limit' => $limit,
            'hasMoreCandidates' => $hasMoreCandidates,
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

    public function search(Request $request, TaskFilterService $taskFilters)
    {
        $userId = $request->user()->id;
        $filters = $taskFilters->resolve($request, 'week');
        $q = $request->get('q', '');

        $tasks = ProjectTask::where('user_id', $userId)
            ->notArchived()
            ->notDone()
            ->where('in_week', false)
            ->applyFilters($filters)
            ->when($q, fn ($query) => $query->where('title', 'like', "%{$q}%"))
            ->with('project:id,name,color')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'title', 'project_id']);

        return response()->json($tasks);
    }

    /**
     * Tamaño de página de candidatas: clamp 20..100 con default 20; nunca se persiste.
     */
    private function resolveLimit(Request $request): int
    {
        return max(20, min(100, (int) $request->get('limit', 20)));
    }
}
