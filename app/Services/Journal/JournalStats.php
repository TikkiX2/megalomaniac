<?php

declare(strict_types=1);

namespace App\Services\Journal;

use App\Models\JournalDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class JournalStats
{
    public function __construct(private readonly JournalService $journal) {}

    /**
     * Diary days of the month, ordered by date ascending. A day row only
     * exists while it carries content or moments.
     *
     * @return Collection<int, array{date: string, mood: int|null, public: bool, moments: int}>
     */
    public function month(User $user, CarbonImmutable $month): Collection
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        return JournalDay::query()
            ->forUser($user)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->withCount([
                'moments as moments_count',
                'moments as public_moments_count' => fn (Builder $query): Builder => $query->where('is_private', false),
            ])
            ->orderBy('date')
            ->get()
            ->map(fn (JournalDay $day): array => [
                'date' => $day->date->toDateString(),
                'mood' => $day->mood,
                'public' => (! $day->is_private && (filled($day->body) || $day->mood !== null))
                    || $day->public_moments_count > 0,
                'moments' => (int) $day->moments_count,
            ])
            ->values();
    }

    /**
     * Consecutive local days with content, alive from today or yesterday.
     */
    public function streak(User $user): int
    {
        $dates = JournalDay::query()
            ->forUser($user)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereNotNull('body')->where('body', '!=', '');
                })
                    ->orWhereNotNull('mood')
                    ->orWhereHas('moments');
            })
            ->get(['date'])
            ->map(fn (JournalDay $day): string => $day->date->toDateString())
            ->flip();

        $cursor = $this->journal->today($user);

        if (! $dates->has($cursor->toDateString())) {
            $cursor = $cursor->subDay();
        }

        $streak = 0;

        while ($dates->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }
}
