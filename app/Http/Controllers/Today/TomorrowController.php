<?php

namespace App\Http\Controllers\Today;

use App\Http\Controllers\Controller;
use App\Http\Requests\Today\StoreTomorrowRequest;
use App\Models\Day;
use App\Models\DayItem;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TomorrowController extends Controller
{
    public function show(Request $request)
    {
        $userId = $request->user()->id;
        $tomorrow = now()->addDay()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $pool = ProjectTask::where('user_id', $userId)
            ->inWeek()
            ->orderBy('sort_order')
            ->limit(10)
            ->get(['id', 'title']);

        $pendingYesterday = DayItem::whereHas('day', fn ($q) => $q->where('user_id', $userId)->where('date', $yesterday))
            ->where('state', 'pending')
            ->with('day')
            ->get();

        $tomorrowDay = Day::with('visibleItems')
            ->where('user_id', $userId)
            ->where('date', $tomorrow)
            ->first();

        return Inertia::render('today/Tomorrow', [
            'fecha' => $tomorrow,
            'pool' => $pool,
            'pendientesAyer' => $pendingYesterday,
            'diaManana' => $tomorrowDay,
        ]);
    }

    public function store(StoreTomorrowRequest $request)
    {
        $userId = $request->user()->id;
        $tomorrow = now()->addDay()->toDateString();
        $items = $request->validated()['items'] ?? [];

        foreach ($items as $it) {
            if (! empty($it['task_id'])) {
                $task = ProjectTask::where('id', $it['task_id'])->where('user_id', $userId)->first();
                if (! $task || $task->archived_at) {
                    abort(422, 'Tarea inválida.');
                }
            }
        }

        $day = Day::firstOrCreate(['user_id' => $userId, 'date' => $tomorrow]);
        // Reemplaza visibles (released queda como rastro invisible, no ocupa slot).
        $day->items()->where('state', '!=', 'released')->delete();
        foreach ($items as $it) {
            $day->items()->create([
                'task_id' => $it['task_id'] ?? null,
                'title' => $it['titulo'],
                'anchor' => $it['anchor'],
                'position' => $it['position'],
                'state' => 'pending',
            ]);
        }

        // "Dejarlo vacío": items vacío deja el día vacío sin fricción.
        return redirect()->route('dashboard')->with('success', 'Mañana listo.');
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
}
