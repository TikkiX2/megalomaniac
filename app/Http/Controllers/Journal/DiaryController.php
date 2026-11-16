<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journal\UpdateDayRequest;
use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\Person;
use App\Services\Journal\JournalMemories;
use App\Services\Journal\JournalService;
use App\Services\Journal\JournalStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DiaryController extends Controller
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly JournalStats $stats,
        private readonly JournalMemories $memories,
    ) {}

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

        $moments = $day === null
            ? collect()
            : $day->moments()
                ->with(['people:id,first_name,last_name', 'media'])
                ->orderBy('occurred_at')
                ->get();

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
            'moments' => $moments->map(fn (JournalMoment $moment): array => [
                'id' => $moment->id,
                'occurred_at' => $moment->occurred_at->toIso8601String(),
                'title' => $moment->title,
                'body' => $moment->body,
                'tags' => $moment->tags ?? [],
                'is_private' => $moment->is_private,
                'people' => $moment->people->map(fn (Person $person): array => [
                    'id' => $person->id,
                    'name' => $person->full_name,
                ])->values(),
                'photos' => $moment->media
                    ->where('collection_name', 'photos')
                    ->map(fn (Media $media): array => [
                        'id' => $media->id,
                        'url' => route('journal.photos.show', [
                            'moment' => $moment->id,
                            'media' => $media->id,
                        ]),
                        'name' => $media->name,
                    ])->values(),
            ])->values(),
            'people' => Person::query()
                ->where('user_id', $request->user()->id)
                ->visible()
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (Person $person): array => [
                    'id' => $person->id,
                    'name' => $person->full_name,
                ])
                ->values(),
            'prevDate' => $current->subDay()->toDateString(),
            'nextDate' => $current->addDay()->toDateString(),
            'onThisDay' => $this->memories->onThisDay($request->user(), $current, 1)->first(),
            'calendar' => [
                'month' => $today->format('Y-m'),
                'days' => $this->stats->month($request->user(), $today->startOfMonth()),
                'streak' => $this->stats->streak($request->user()),
            ],
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
