<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journal\UpdateDayRequest;
use App\Models\JournalDay;
use App\Services\Journal\JournalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiaryController extends Controller
{
    public function __construct(private readonly JournalService $journal) {}

    public function index(Request $request): Response
    {
        $date = $request->query('date');

        if (! is_string($date) || $date === '') {
            $date = $this->journal->today($request->user())->toDateString();
        }

        return $this->render($request, $date);
    }

    public function show(Request $request, string $date): Response
    {
        return $this->render($request, $date);
    }

    public function update(UpdateDayRequest $request, string $date): RedirectResponse
    {
        $this->parseDate($date);

        $day = $this->journal->saveDay($request->user(), $date, $request->validated());

        if ($day === null) {
            return back()->with('success', 'Día vacío eliminado.');
        }

        return back()->with('success', 'Día guardado.');
    }

    private function render(Request $request, string $date): Response
    {
        $current = $this->parseDate($date);
        $today = $this->journal->today($request->user());

        $day = JournalDay::query()
            ->forUser($request->user())
            ->where('date', $date)
            ->first();

        return Inertia::render('journal/Index', [
            'date' => $date,
            'today' => $today->toDateString(),
            'day' => $day === null ? null : [
                'id' => $day->id,
                'body' => $day->body,
                'mood' => $day->mood,
                'tags' => $day->tags ?? [],
                'is_private' => $day->is_private,
            ],
            'prevDate' => $current->subDay()->toDateString(),
            'nextDate' => $current->addDay()->toDateString(),
        ]);
    }

    private function parseDate(string $date): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            abort(404);
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            $parsed = null;
        }

        if (! $parsed instanceof CarbonImmutable || $parsed->format('Y-m-d') !== $date) {
            abort(404);
        }

        return $parsed;
    }
}
