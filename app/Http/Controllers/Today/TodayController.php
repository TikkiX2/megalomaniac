<?php

namespace App\Http\Controllers\Today;

use App\Http\Controllers\Controller;
use App\Http\Requests\Today\UpdateDayItemRequest;
use App\Models\Block;
use App\Models\Day;
use App\Models\DayItem;
use App\Models\QueueItem;
use App\Models\Routine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TodayController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $today = now()->toDateString();

        $day = Day::with(['visibleItems.task'])
            ->where('user_id', $userId)
            ->where('date', $today)
            ->first();

        $block = Block::where('user_id', $userId)
            ->where('weekday', now()->dayOfWeek)
            ->where('active', true)
            ->orderBy('start_time')
            ->first();

        $routine = Routine::where('user_id', $userId)
            ->where('scheduled_date', now()->format('l'))
            ->first();

        $queueFirst = QueueItem::where('user_id', $userId)
            ->orderBy('position')
            ->first();

        return Inertia::render('today/Index', [
            'fecha' => $today,
            'day' => $day,
            'block' => $block,
            'routine' => $routine ? ['id' => $routine->id, 'name' => $routine->name, 'focus' => $routine->focus] : null,
            'queueFirst' => $queueFirst,
        ]);
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
}
