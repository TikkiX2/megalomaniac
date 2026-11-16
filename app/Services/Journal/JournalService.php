<?php

declare(strict_types=1);

namespace App\Services\Journal;

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class JournalService
{
    /**
     * The user's local calendar date, regardless of the UTC clock.
     */
    public function today(User $user): CarbonImmutable
    {
        return CarbonImmutable::now($user->localTimezone())->startOfDay();
    }

    public function dayFor(User $user, string $date): JournalDay
    {
        return JournalDay::query()->firstOrCreate([
            'user_id' => $user->id,
            'date' => $date,
        ]);
    }

    /**
     * @param  array{body?: string|null, mood?: int|null, tags?: array<int, string>|null, is_private?: bool}  $data
     */
    public function saveDay(User $user, string $date, array $data): ?JournalDay
    {
        return DB::transaction(function () use ($user, $date, $data): ?JournalDay {
            $day = $this->dayFor($user, $date);

            if (array_key_exists('tags', $data)) {
                $data['tags'] = $this->normalizeTags($data['tags']);
            }

            $day->fill($data)->save();

            return $this->pruneIfEmpty($day) ? null : $day;
        });
    }

    /**
     * Remove a day that has no body, mood or moments left. Tags alone do not
     * keep a day alive, so an empty save never leaves a ghost anchor.
     */
    public function pruneIfEmpty(JournalDay $day): bool
    {
        if ($day->refresh()->hasContent()) {
            return false;
        }

        $day->delete();

        return true;
    }

    /**
     * @param  array{title?: string|null, body: string, tags?: array<int, string>|null, is_private?: bool, occurred_at?: string|null, person_ids?: array<int, int>|null}  $data
     */
    public function createMoment(JournalDay $day, array $data): JournalMoment
    {
        return DB::transaction(function () use ($day, $data): JournalMoment {
            $user = $day->user;
            $explicit = isset($data['occurred_at']) && $data['occurred_at'] !== '';
            $personIds = $data['person_ids'] ?? [];

            $occurredAt = $this->occurredAtFor(
                $user,
                $day->date->toDateString(),
                $data['occurred_at'] ?? null,
            );

            $anchor = $day;

            if ($explicit) {
                $localDate = $occurredAt->setTimezone($user->localTimezone())->toDateString();

                if ($localDate !== $day->date->toDateString()) {
                    $anchor = $this->dayFor($user, $localDate);
                }
            }

            $moment = new JournalMoment([
                'user_id' => $day->user_id,
                'journal_day_id' => $anchor->id,
                'occurred_at' => $occurredAt,
                'title' => $data['title'] ?? null,
                'body' => $data['body'],
                'tags' => $this->normalizeTags($data['tags'] ?? []),
                'is_private' => (bool) ($data['is_private'] ?? false),
            ]);

            $moment->save();
            $moment->people()->sync($personIds);

            if (! $anchor->is($day)) {
                $this->pruneIfEmpty($day);
            }

            return $moment;
        });
    }

    /**
     * @param  array{title?: string|null, body?: string, tags?: array<int, string>|null, is_private?: bool, occurred_at?: string|null, person_ids?: array<int, int>|null}  $data
     */
    public function updateMoment(JournalMoment $moment, array $data): JournalMoment
    {
        return DB::transaction(function () use ($moment, $data): JournalMoment {
            $occurredAt = $data['occurred_at'] ?? null;
            unset($data['occurred_at']);

            $personIds = $data['person_ids'] ?? null;
            unset($data['person_ids']);

            if (array_key_exists('tags', $data)) {
                $data['tags'] = $this->normalizeTags($data['tags']);
            }

            $moment->fill($data)->save();

            if ($personIds !== null) {
                $moment->people()->sync($personIds);
            }

            if ($occurredAt !== null) {
                $moment = $this->moveMoment($moment, CarbonImmutable::parse((string) $occurredAt)->utc());
            }

            return $moment->refresh();
        });
    }

    /**
     * Re-anchor the moment to the day of its new local date and prune the old
     * day when the move leaves it without content.
     */
    public function moveMoment(JournalMoment $moment, CarbonInterface $occurredAt): JournalMoment
    {
        $moment->loadMissing(['day', 'user']);

        $previousDay = $moment->day;
        $localDate = CarbonImmutable::instance($occurredAt)
            ->setTimezone($moment->user->localTimezone())
            ->toDateString();

        $targetDay = $previousDay !== null && $previousDay->date->toDateString() === $localDate
            ? $previousDay
            : $this->dayFor($moment->user, $localDate);

        $moment->fill([
            'occurred_at' => $occurredAt,
            'journal_day_id' => $targetDay->id,
        ])->save();

        if ($previousDay !== null && ! $previousDay->is($targetDay)) {
            $this->pruneIfEmpty($previousDay);
        }

        return $moment->refresh();
    }

    /**
     * Default the clock to "now" for today and to local noon for past or
     * future days, always stored in UTC.
     */
    private function occurredAtFor(User $user, string $date, mixed $value): CarbonImmutable
    {
        if ($value !== null && $value !== '') {
            return CarbonImmutable::parse((string) $value)->utc();
        }

        if ($date === $this->today($user)->toDateString()) {
            return CarbonImmutable::now()->utc();
        }

        return CarbonImmutable::parse($date.' 12:00:00', $user->localTimezone())->utc();
    }

    /**
     * Trim, lowercase, dedupe and cap the tag list at eight entries.
     *
     * @return array<int, string>
     */
    public function normalizeTags(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        $normalized = [];

        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            $tag = mb_strtolower(trim($tag));

            if ($tag === '' || in_array($tag, $normalized, true)) {
                continue;
            }

            $normalized[] = $tag;
        }

        return array_slice($normalized, 0, 8);
    }
}
