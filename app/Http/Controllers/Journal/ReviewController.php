<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Models\JournalReflection;
use App\Services\Journal\JournalMemories;
use App\Services\Journal\JournalReflectionService;
use App\Services\Journal\JournalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly JournalMemories $memories,
        private readonly JournalReflectionService $reflections,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $today = $this->journal->today($user);
        $weekStart = $this->reflections->weekStart($user);
        $current = $this->reflections->forWeek($user, $weekStart);

        return Inertia::render('journal/Review', [
            'onThisDay' => $this->memories->onThisDay($user, $today)->all(),
            'random' => fn (): ?array => $this->memories->random($user, $today),
            'today' => $today->toDateString(),
            'weekStart' => $weekStart->toDateString(),
            'reflection' => $current === null ? null : $this->present($current),
            'reflections' => JournalReflection::query()
                ->where('user_id', $user->id)
                ->latest('week_start')
                ->limit(8)
                ->get()
                ->map(fn (JournalReflection $reflection): array => $this->present($reflection))
                ->all(),
        ]);
    }

    /**
     * Serializable reflection for `ReflectionCard`.
     *
     * @return array{body: string, source: string, model: string|null, generated_at: string|null, week_start: string}
     */
    private function present(JournalReflection $reflection): array
    {
        return [
            'body' => $reflection->body,
            'source' => $reflection->source,
            'model' => $reflection->model,
            'generated_at' => $reflection->generated_at?->toIso8601String(),
            'week_start' => $reflection->week_start->toDateString(),
        ];
    }
}
