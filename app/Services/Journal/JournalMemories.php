<?php

declare(strict_types=1);

namespace App\Services\Journal;

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the "on this day" and random-memory payloads shared by the review
 * page and the diary rail teaser.
 */
class JournalMemories
{
    private const int EXCERPT_LIMIT = 180;

    private const int ON_THIS_DAY_LIMIT = 6;

    private const int RANDOM_MINIMUM_AGE_DAYS = 30;

    /**
     * Public memories from the same local month-day in earlier years, newest
     * first.
     *
     * @return Collection<int, array{id: int, type: 'day'|'moment', date: string, excerpt: string, is_private: bool}>
     */
    public function onThisDay(User $user, CarbonImmutable $date, int $limit = self::ON_THIS_DAY_LIMIT): Collection
    {
        $timezone = $user->localTimezone();
        $target = CarbonImmutable::createFromFormat('!Y-m-d', $date->format('Y-m-d'), $timezone);

        if (! $target instanceof CarbonImmutable) {
            return collect();
        }

        $days = JournalDay::query()
            ->forUser($user)
            ->where('is_private', false)
            ->whereYear('date', '<', $target->year)
            ->whereMonth('date', $target->month)
            ->whereDay('date', $target->day)
            ->orderByDesc('date')
            ->limit($limit)
            ->get(['id', 'date', 'body'])
            ->map(fn (JournalDay $day): array => [
                'id' => (int) $day->id,
                'type' => 'day',
                'date' => $day->date->toDateString(),
                'excerpt' => $this->excerpt($day->body),
                'is_private' => false,
            ]);

        $moments = $this->momentsOnThisDay($user, $target, $timezone, $limit);

        return $days
            ->concat($moments)
            ->sortByDesc('date')
            ->values()
            ->take($limit)
            ->values();
    }

    /**
     * A public memory older than the minimum age, picked at random across both
     * days and moments.
     *
     * @return array{id: int, type: 'day'|'moment', date: string, excerpt: string, is_private: bool}|null
     */
    public function random(User $user, CarbonImmutable $today): ?array
    {
        $timezone = $user->localTimezone();
        $cutoff = $today->setTimezone($timezone)->startOfDay()->subDays(self::RANDOM_MINIMUM_AGE_DAYS);

        $days = JournalDay::query()
            ->forUser($user)
            ->where('is_private', false)
            ->where('date', '<=', $cutoff->toDateString());

        $moments = JournalMoment::query()
            ->where('user_id', $user->id)
            ->where('is_private', false)
            ->where('occurred_at', '<=', $cutoff->utc());

        $dayCount = $days->count();
        $total = $dayCount + $moments->count();

        if ($total === 0) {
            return null;
        }

        $offset = random_int(0, $total - 1);

        if ($offset < $dayCount) {
            $day = $days->orderBy('id')->offset($offset)->first(['id', 'date', 'body']);

            if ($day === null) {
                return null;
            }

            return [
                'id' => (int) $day->id,
                'type' => 'day',
                'date' => $day->date->toDateString(),
                'excerpt' => $this->excerpt($day->body),
                'is_private' => false,
            ];
        }

        $moment = $moments->orderBy('id')->offset($offset - $dayCount)->first(['id', 'occurred_at', 'title', 'body']);

        return $moment === null ? null : $this->momentMemory($moment, $timezone);
    }

    /**
     * Moments of the same local month-day in earlier years. The UTC window is
     * loaded per year (±1 day) and filtered in PHP so the comparison uses the
     * user's timezone instead of timezone-dependent SQL.
     *
     * @return Collection<int, array{id: int, type: 'day'|'moment', date: string, excerpt: string, is_private: bool}>
     */
    private function momentsOnThisDay(User $user, CarbonImmutable $target, string $timezone, int $limit): Collection
    {
        $windows = $this->utcWindows($user, $target);

        if ($windows === []) {
            return collect();
        }

        return JournalMoment::query()
            ->where('user_id', $user->id)
            ->where('is_private', false)
            ->where(function (Builder $query) use ($windows): void {
                foreach ($windows as $window) {
                    $query->orWhereBetween('occurred_at', $window);
                }
            })
            ->orderByDesc('occurred_at')
            ->get(['id', 'occurred_at', 'title', 'body'])
            ->map(fn (JournalMoment $moment): ?array => $this->momentMemory($moment, $timezone, $target))
            ->filter()
            ->values()
            ->take($limit)
            ->values();
    }

    /**
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function utcWindows(User $user, CarbonImmutable $target): array
    {
        $earliest = JournalMoment::query()
            ->where('user_id', $user->id)
            ->where('is_private', false)
            ->min('occurred_at');

        if (! is_string($earliest)) {
            return [];
        }

        $firstYear = (int) substr($earliest, 0, 4);
        $windows = [];

        for ($year = $firstYear; $year < $target->year; $year++) {
            $day = $target->setYear($year);

            $windows[] = [
                $day->subDay()->startOfDay()->utc(),
                $day->addDay()->endOfDay()->utc(),
            ];
        }

        return $windows;
    }

    /**
     * @return array{id: int, type: 'moment', date: string, excerpt: string, is_private: bool}|null
     */
    private function momentMemory(JournalMoment $moment, string $timezone, ?CarbonImmutable $target = null): ?array
    {
        $local = CarbonImmutable::instance($moment->occurred_at)->setTimezone($timezone);

        if ($target !== null && ($local->format('m-d') !== $target->format('m-d') || $local->year >= $target->year)) {
            return null;
        }

        $excerpt = $this->excerpt($moment->body);

        return [
            'id' => (int) $moment->id,
            'type' => 'moment',
            'date' => $local->toDateString(),
            'excerpt' => $excerpt === '' ? $this->excerpt($moment->title) : $excerpt,
            'is_private' => false,
        ];
    }

    private function excerpt(?string $text): string
    {
        return Str::limit(Str::squish((string) $text), self::EXCERPT_LIMIT);
    }
}
