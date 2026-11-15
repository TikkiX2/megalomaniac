<?php

declare(strict_types=1);

namespace App\Services\Journal;

use App\Models\JournalDay;
use App\Models\User;
use Carbon\CarbonImmutable;

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
        $day = $this->dayFor($user, $date);

        if (array_key_exists('tags', $data)) {
            $data['tags'] = $this->normalizeTags($data['tags']);
        }

        $day->fill($data)->save();

        return $this->pruneIfEmpty($day) ? null : $day;
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
