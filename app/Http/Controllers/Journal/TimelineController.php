<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Models\JournalDay;
use App\Models\JournalMoment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TimelineController extends Controller
{
    private const int EXCERPT_LIMIT = 180;

    public function __invoke(Request $request): Response
    {
        $days = JournalDay::query()
            ->forUser($request->user())
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereNotNull('body')->where('body', '!=', '');
                })
                    ->orWhereNotNull('mood')
                    ->orWhereHas('moments');
            })
            ->withCount('moments')
            ->with([
                'moments' => fn ($query) => $query
                    ->orderBy('occurred_at')
                    ->orderBy('id')
                    ->with('media'),
            ])
            ->orderByDesc('date')
            ->paginate(20)
            ->withQueryString();

        $total = $days->total();

        return Inertia::render('journal/All', [
            'days' => $days->through(fn (JournalDay $day): array => [
                'date' => $day->date->toDateString(),
                'excerpt' => $this->excerpt($day),
                'moments_count' => (int) $day->moments_count,
                'is_private' => $this->isPrivate($day),
                'photo_url' => $this->photoUrl($day),
            ]),
            'total' => $total,
        ]);
    }

    /**
     * The day text when it is public, or the body of its first public moment,
     * flattened for the card. Private text never reaches the timeline.
     */
    private function excerpt(JournalDay $day): string
    {
        $text = $day->is_private ? '' : Str::squish((string) $day->body);

        if ($text === '') {
            $text = Str::squish((string) $day->moments
                ->first(fn (JournalMoment $moment): bool => ! $moment->is_private && filled($moment->body))
                ?->body);
        }

        return $text === '' ? 'Sin texto' : Str::limit($text, self::EXCERPT_LIMIT);
    }

    /**
     * A day is locked when it is private or when it has no public content at
     * all, so a day holding only a private moment never leaks an excerpt.
     */
    private function isPrivate(JournalDay $day): bool
    {
        if ($day->is_private) {
            return true;
        }

        if (filled($day->body) || $day->mood !== null) {
            return false;
        }

        return ! $day->moments->contains(fn (JournalMoment $moment): bool => ! $moment->is_private);
    }

    /**
     * First photo of the first moment that has one, served by the owner-only
     * photos route.
     */
    private function photoUrl(JournalDay $day): ?string
    {
        foreach ($day->moments as $moment) {
            $media = $moment->media->firstWhere('collection_name', 'photos');

            if ($media instanceof Media) {
                return route('journal.photos.show', [
                    'moment' => $moment->id,
                    'media' => $media->id,
                ]);
            }
        }

        return null;
    }
}
