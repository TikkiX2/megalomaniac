<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    private const int EXCERPT_LIMIT = 180;

    private const int MINIMUM_QUERY_LENGTH = 2;

    private const int MAXIMUM_QUERY_LENGTH = 100;

    private const int RESULT_LIMIT = 20;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $query = Str::squish((string) $request->query('q', ''));
        $valid = Str::length($query) >= self::MINIMUM_QUERY_LENGTH
            && Str::length($query) <= self::MAXIMUM_QUERY_LENGTH;

        return Inertia::render('journal/All', [
            'q' => $query,
            'days' => $valid ? $this->days($user, $query)->all() : [],
            'moments' => $valid ? $this->moments($user, $query)->all() : [],
        ]);
    }

    /**
     * @return Collection<int, array{id: int, type: 'day', date: string, excerpt: string, is_private: bool}>
     */
    private function days(User $user, string $query): Collection
    {
        $pattern = $this->pattern($query);

        return JournalDay::query()
            ->forUser($user)
            ->where('is_private', false)
            ->whereRaw("LOWER(body) LIKE ? ESCAPE '\\'", [$pattern])
            ->orderByDesc('date')
            ->limit(self::RESULT_LIMIT)
            ->get(['id', 'date', 'body'])
            ->map(fn (JournalDay $day): array => [
                'id' => (int) $day->id,
                'type' => 'day',
                'date' => $day->date->toDateString(),
                'excerpt' => Str::limit(Str::squish((string) $day->body), self::EXCERPT_LIMIT),
                'is_private' => false,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{id: int, type: 'moment', date: string, excerpt: string, is_private: bool}>
     */
    private function moments(User $user, string $query): Collection
    {
        $pattern = $this->pattern($query);
        $timezone = $user->localTimezone();

        return JournalMoment::query()
            ->where('user_id', $user->id)
            ->where('is_private', false)
            ->where(function (Builder $builder) use ($pattern): void {
                $builder->whereRaw("LOWER(title) LIKE ? ESCAPE '\\'", [$pattern])
                    ->orWhereRaw("LOWER(body) LIKE ? ESCAPE '\\'", [$pattern]);
            })
            ->orderByDesc('occurred_at')
            ->limit(self::RESULT_LIMIT)
            ->get(['id', 'occurred_at', 'title', 'body'])
            ->map(function (JournalMoment $moment) use ($timezone): array {
                $excerpt = Str::limit(Str::squish((string) $moment->body), self::EXCERPT_LIMIT);

                return [
                    'id' => (int) $moment->id,
                    'type' => 'moment',
                    'date' => $moment->occurred_at->copy()->setTimezone($timezone)->toDateString(),
                    'excerpt' => $excerpt === ''
                        ? Str::limit(Str::squish((string) $moment->title), self::EXCERPT_LIMIT)
                        : $excerpt,
                    'is_private' => false,
                ];
            })
            ->values();
    }

    /**
     * Case-insensitive "contains" pattern with the LIKE wildcards escaped. The
     * needle is folded too, so a capitalized search still matches the
     * lowercased column.
     */
    private function pattern(string $query): string
    {
        return '%'.addcslashes(Str::lower($query), '%_\\').'%';
    }
}
