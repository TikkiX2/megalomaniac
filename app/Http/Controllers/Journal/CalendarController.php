<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalService;
use App\Services\Journal\JournalStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly JournalStats $stats,
    ) {}

    public function __invoke(Request $request): Response
    {
        $timezone = $request->user()->localTimezone();
        $month = $this->parseMonth($request->query('month'), $timezone);
        $today = $this->journal->today($request->user());

        return Inertia::render('journal/Calendar', [
            'month' => $month->format('Y-m'),
            'days' => $this->stats->month($request->user(), $month),
            'streak' => $this->stats->streak($request->user()),
            'today' => $today->toDateString(),
            'prevMonth' => $month->subMonth()->format('Y-m'),
            'nextMonth' => $month->addMonth()->format('Y-m'),
        ]);
    }

    private function parseMonth(mixed $value, string $timezone): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            $parsed = CarbonImmutable::createFromFormat('!Y-m', $value, $timezone);

            if ($parsed instanceof CarbonImmutable && $parsed->format('Y-m') === $value) {
                return $parsed;
            }
        }

        return CarbonImmutable::now($timezone)->startOfMonth();
    }
}
