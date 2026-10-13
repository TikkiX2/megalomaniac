<?php

namespace App\Http\Controllers\Today;

use App\Http\Controllers\Controller;
use App\Http\Requests\Today\StoreTomorrowRequest;
use App\Models\Day;
use App\Models\DayItem;
use App\Models\ProjectTask;
use App\Services\Today\TaskFilterService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TomorrowController extends Controller
{
    public function show(Request $request, TaskFilterService $taskFilters)
    {
        $userId = $request->user()->id;
        $target = $this->resolveTarget($request->query('target'));
        $date = $target === 'today' ? now()->toDateString() : now()->addDay()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $filters = $taskFilters->resolve($request, 'tomorrow');
        $options = $taskFilters->options($userId);

        $pool = ProjectTask::where('user_id', $userId)
            ->inWeek()
            ->notDone()
            ->applyFilters($filters)
            ->with('project:id,name,color')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(10)
            ->get(['id', 'title', 'project_id']);

        $pendingYesterday = DayItem::whereHas('day', fn ($q) => $q->where('user_id', $userId)->whereDate('date', $yesterday))
            ->where('state', 'pending')
            ->with('day')
            ->get();

        $targetDay = Day::with('visibleItems')
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->first();

        return Inertia::render('today/Tomorrow', [
            'fecha' => $date,
            'target' => $target,
            'pool' => $pool,
            'pendientesAyer' => $pendingYesterday,
            'dia' => $targetDay,
            'projects' => $options['projects'],
            'availableTags' => $options['tags'],
            'filters' => $filters,
        ]);
    }

    public function store(StoreTomorrowRequest $request)
    {
        $userId = $request->user()->id;
        $target = $request->validated()['target'] ?? 'tomorrow';
        $date = $target === 'today' ? now()->toDateString() : now()->addDay()->toDateString();
        $items = $request->validated()['items'] ?? [];

        foreach ($items as $it) {
            if (! empty($it['task_id'])) {
                $task = ProjectTask::where('id', $it['task_id'])->where('user_id', $userId)->first();
                if (! $task || $task->archived_at) {
                    abort(422, 'Tarea inválida.');
                }
            }
        }

        $day = Day::where('user_id', $userId)->whereDate('date', $date)->first()
            ?? Day::create(['user_id' => $userId, 'date' => $date]);
        // Reemplaza visibles (released queda como rastro invisible, no ocupa slot).
        $day->items()->where('state', '!=', 'released')->delete();
        foreach ($items as $it) {
            $day->items()->create([
                'task_id' => $it['task_id'] ?? null,
                'title' => $it['title'],
                'anchor' => $it['anchor'],
                'position' => $it['position'],
                'state' => 'pending',
            ]);
        }

        // "Dejarlo vacío": items vacío deja el día vacío sin fricción.
        return redirect()->route('dashboard')->with('success', $target === 'today' ? 'Hoy listo.' : 'Mañana listo.');
    }

    public function putToday(Request $request, DayItem $item)
    {
        abort_if($item->day->user_id !== $request->user()->id, 403);
        $userId = $request->user()->id;
        $today = Day::firstOrCreate(['user_id' => $userId, 'date' => now()->toDateString()]);

        $visible = $today->visibleItems()->count();
        if ($visible >= 3) {
            abort(422, 'Máximo 3 ítems por día.');
        }
        $position = $today->visibleItems()->max('position') + 1;
        if ($position < 1 || $position > 3) {
            $position = $visible + 1;
        }

        $today->items()->create([
            'task_id' => $item->task_id,
            'title' => $item->title,
            'anchor' => $item->anchor,
            'position' => $position,
            'state' => 'pending',
        ]);
        $item->update(['state' => 'released']);

        return back()->with('success', 'Puesto hoy.');
    }

    /**
     * Resuelve el destino de la planificación: `?target=today|tomorrow` explícito
     * o, sin parámetro, por hora (madrugada 00–05 → hoy, resto → mañana).
     */
    private function resolveTarget(mixed $requested): string
    {
        if (in_array($requested, ['today', 'tomorrow'], true)) {
            return $requested;
        }

        return now()->hour < 5 ? 'today' : 'tomorrow';
    }
}
