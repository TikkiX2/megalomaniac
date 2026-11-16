<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\JournalReflection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Drops the stored weekly reflection whenever a memory it may quote stops
 * being public (or stops belonging to that week). The reflection is built from
 * public text only, so invalidating it is enough: the next recap regenerates it
 * from what is public today instead of serving stale text.
 */
class JournalReflectionInvalidator
{
    public function deleting(JournalDay|JournalMoment $memory): void
    {
        if (! $memory instanceof JournalDay) {
            return;
        }

        $weeks = [$this->weekOf($memory)];
        $user = $memory->user;

        if ($user instanceof User) {
            foreach ($memory->moments()->pluck('occurred_at') as $occurredAt) {
                $weeks[] = $this->weekFromOccurredAt($user, $occurredAt);
            }
        }

        $this->forget($memory, $weeks);
    }

    public function deleted(JournalDay|JournalMoment $memory): void
    {
        $this->forget($memory, [$this->weekOf($memory)]);
    }

    public function saved(JournalDay|JournalMoment $memory): void
    {
        $weeks = [];

        if ($memory->wasChanged('is_private') && $memory->is_private) {
            $weeks[] = $this->weekOf($memory);
        }

        if ($memory->wasChanged('date') || $memory->wasChanged('occurred_at')) {
            $weeks[] = $this->weekOf($memory);
            $weeks[] = $this->originalWeekOf($memory);
        }

        if ($memory instanceof JournalMoment && $memory->wasChanged('journal_day_id')) {
            foreach ([$memory->getOriginal('journal_day_id'), $memory->journal_day_id] as $dayId) {
                $weeks[] = $this->weekOfDay($memory, $dayId);
            }
        }

        $this->forget($memory, $weeks);
    }

    private function weekOf(JournalDay|JournalMoment $memory): ?string
    {
        $user = $memory->user;

        if (! $user instanceof User) {
            return null;
        }

        if ($memory instanceof JournalMoment) {
            return $this->weekFromOccurredAt($user, $memory->occurred_at);
        }

        $date = $memory->date;

        return $this->weekFromDate($user, $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date);
    }

    private function originalWeekOf(JournalDay|JournalMoment $memory): ?string
    {
        $user = $memory->user;

        if (! $user instanceof User) {
            return null;
        }

        $attribute = $memory instanceof JournalMoment ? 'occurred_at' : 'date';

        return $memory instanceof JournalMoment
            ? $this->weekFromOccurredAt($user, $memory->getOriginal($attribute))
            : $this->weekFromDate($user, $memory->getOriginal($attribute));
    }

    private function weekOfDay(JournalMoment $moment, mixed $dayId): ?string
    {
        $user = $moment->user;

        if ($dayId === null || ! $user instanceof User) {
            return null;
        }

        $date = JournalDay::query()->whereKey($dayId)->value('date');

        return is_string($date) ? $this->weekFromDate($user, $date) : null;
    }

    private function weekFromOccurredAt(User $user, mixed $occurredAt): ?string
    {
        if ($occurredAt === null || $occurredAt === '') {
            return null;
        }

        try {
            $local = CarbonImmutable::parse((string) $occurredAt, config('app.timezone'))
                ->setTimezone($user->localTimezone());
        } catch (Throwable) {
            return null;
        }

        return $local->startOfWeek(CarbonInterface::MONDAY)->toDateString();
    }

    private function weekFromDate(User $user, mixed $date): ?string
    {
        if (! is_string($date) || $date === '') {
            return null;
        }

        try {
            $local = CarbonImmutable::createFromFormat('!Y-m-d', $date, $user->localTimezone());
        } catch (Throwable) {
            return null;
        }

        if (! $local instanceof CarbonImmutable) {
            return null;
        }

        return $local->startOfWeek(CarbonInterface::MONDAY)->toDateString();
    }

    /**
     * @param  array<int, string|null>  $weeks
     */
    private function forget(JournalDay|JournalMoment $memory, array $weeks): void
    {
        $weeks = array_values(array_unique(array_filter($weeks)));

        foreach ($weeks as $week) {
            JournalReflection::query()
                ->where('user_id', $memory->user_id)
                ->whereDate('week_start', $week)
                ->delete();
        }
    }
}
