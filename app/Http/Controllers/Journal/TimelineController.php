<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Services\Journal\JournalAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TimelineController extends Controller
{
    private const int EXCERPT_LIMIT = 180;

    public function __construct(private readonly JournalAccess $access) {}

    public function __invoke(Request $request): Response
    {
        $locked = ! $this->access->unlocked($request);

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
            'days' => $days->through(function (JournalDay $day) use ($locked): array {
                $dayLocked = $locked && ! $this->hasPublicContent($day);

                return [
                    'date' => $day->date->toDateString(),
                    'excerpt' => $dayLocked ? null : $this->excerpt($day, ! $locked),
                    'moments_count' => (int) $day->moments_count,
                    'is_private' => $this->isPrivate($day),
                    'photo_url' => $dayLocked ? null : $this->photoUrl($day, ! $locked),
                    'locked' => $dayLocked,
                ];
            }),
            'total' => $total,
        ]);
    }

    /**
     * The day text when it is public (or unlocked), or the body of its first
     * readable moment, flattened for the card. Locked private text never
     * reaches the timeline.
     */
    private function excerpt(JournalDay $day, bool $unlocked): string
    {
        $text = (! $day->is_private || $unlocked) ? Str::squish((string) $day->body) : '';

        if ($text === '') {
            $text = Str::squish((string) $day->moments
                ->first(fn (JournalMoment $moment): bool => ($unlocked || ! $moment->is_private) && filled($moment->body))
                ?->body);
        }

        return $text === '' ? 'Sin texto' : Str::limit($text, self::EXCERPT_LIMIT);
    }

    /**
     * Whether the day is marked private, or holds only private content, so the
     * card shows the privacy marker.
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
     * A day keeps its place in the timeline while locked, but only its public
     * text or moments justify showing anything besides the lock.
     */
    private function hasPublicContent(JournalDay $day): bool
    {
        if (! $day->is_private && (filled($day->body) || $day->mood !== null)) {
            return true;
        }

        return $day->moments->contains(fn (JournalMoment $moment): bool => ! $moment->is_private);
    }

    /**
     * First photo of the first readable moment, served by the owner-only
     * photos route. Locked private photos are skipped instead of pointing to a
     * route that answers 423.
     */
    private function photoUrl(JournalDay $day, bool $unlocked): ?string
    {
        foreach ($day->moments as $moment) {
            if ($moment->is_private && ! $unlocked) {
                continue;
            }

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
