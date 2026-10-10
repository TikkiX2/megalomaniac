<?php

namespace App\Http\Controllers\Today;

use App\Http\Controllers\Controller;
use App\Http\Requests\Today\UpdateDayItemRequest;
use App\Models\Block;
use App\Models\Day;
use App\Models\DayItem;
use App\Models\MealLog;
use App\Models\QueueItem;
use App\Models\Routine;
use App\Models\Supplement;
use App\Models\Workout;
use App\Services\Media\DailyPickService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TodayController extends Controller
{
    public function index(Request $request, DailyPickService $pickService)
    {
        $userId = $request->user()->id;
        $today = now()->toDateString();

        $day = Day::with('visibleItems')
            ->where('user_id', $userId)
            ->whereDate('date', $today)
            ->first();

        $block = Block::where('user_id', $userId)
            ->where('weekday', now()->dayOfWeek)
            ->where('active', true)
            ->orderBy('start_time')
            ->first();

        $routine = Routine::where('user_id', $userId)
            ->where('scheduled_date', now()->format('l'))
            ->first();

        $pickType = $day?->pick_type ?? 'pelicula';
        $queueItems = QueueItem::where('user_id', $userId)
            ->where('type', $pickType)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $pick = $pickService->pick($userId, $today, $pickType, $queueItems);

        return Inertia::render('fitness/dashboard', array_merge(
            $this->dashboardProps($userId),
            [
                'date' => $today,
                'day' => $this->dayPayload($day),
                'block' => $block,
                'routine' => $routine ? ['id' => $routine->id, 'name' => $routine->name, 'focus' => $routine->focus] : null,
                'pick' => $pick ? [
                    'title' => $pick->title,
                    'type' => $pick->type,
                    'cover_url' => $pick->cover_url,
                ] : null,
                'pickTypes' => $this->pickTypes(),
            ]
        ));
    }

    /**
     * Persiste el tipo de media del día (crea el day de hoy si no existe).
     */
    public function pickType(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', QueueItem::TYPES)],
        ]);

        $userId = $request->user()->id;
        $today = now()->toDateString();

        $day = Day::where('user_id', $userId)->whereDate('date', $today)->first()
            ?? new Day(['user_id' => $userId, 'date' => $today]);
        $day->pick_type = $data['type'];
        $day->save();

        return back()->with('success', 'Listo.');
    }

    public function update(UpdateDayItemRequest $request, DayItem $item)
    {
        $validated = $request->validated();
        $item->update([
            'state' => $validated['state'],
            'closing_note' => $validated['closing_note'] ?? null,
            'done_at' => $validated['state'] === 'done' ? now() : null,
        ]);

        return back()->with('success', 'Listo.');
    }

    public function release(Request $request, DayItem $item)
    {
        abort_if($item->day->user_id !== $request->user()->id, 403);
        $item->update(['state' => 'released']);

        return back()->with('success', 'Soltado.');
    }

    /**
     * Opciones del selector de tipo de la línea de media.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function pickTypes(): array
    {
        return collect(QueueItem::TYPES)
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => QueueItem::TYPE_LABELS[$type] ?? $type,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, pick_type: string, items: array<int, array{id: int, title: string, anchor: string, position: int, state: string, closing_note: string|null}>}|null
     */
    private function dayPayload(?Day $day): ?array
    {
        if (! $day) {
            return null;
        }

        return [
            'id' => $day->id,
            'pick_type' => $day->pick_type,
            'items' => $day->visibleItems->map(fn (DayItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'anchor' => $item->anchor,
                'position' => $item->position,
                'state' => $item->state,
                'closing_note' => $item->closing_note,
            ])->values()->all(),
        ];
    }

    /**
     * Props que consume resources/js/pages/fitness/dashboard.tsx,
     * recuperados del closure original del dashboard (commit f97d459).
     *
     * @return array<string, mixed>
     */
    private function dashboardProps(int $userId): array
    {
        $mealLogsToday = MealLog::with('items')
            ->where('user_id', $userId)
            ->whereDate('date', now()->toDateString())
            ->get();

        $caloriesToday = $mealLogsToday->sum(fn ($log) => $log->total_calories ?? 0);
        $macrosToday = ['protein' => 0, 'carbs' => 0, 'fats' => 0];
        foreach ($mealLogsToday as $log) {
            $macros = $log->total_macros ?? ['protein' => 0, 'carbs' => 0, 'fats' => 0];
            $macrosToday['protein'] += (float) ($macros['protein'] ?? 0);
            $macrosToday['carbs'] += (float) ($macros['carbs'] ?? 0);
            $macrosToday['fats'] += (float) ($macros['fats'] ?? 0);
        }

        return [
            'workoutCount' => Workout::where('user_id', $userId)->count(),
            'recentWorkouts' => Workout::with('routine')
                ->where('user_id', $userId)
                ->orderByDesc('started_at')
                ->limit(5)
                ->get(),
            'caloriesToday' => $caloriesToday,
            'macrosToday' => $macrosToday,
            'goals' => ['calories' => 2400, 'protein' => 180, 'carbs' => 250, 'fats' => 70],
            'lowStockSupplements' => Supplement::where('user_id', $userId)->get()->filter->is_low_stock->values(),
        ];
    }
}
