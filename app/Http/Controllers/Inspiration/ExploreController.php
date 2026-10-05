<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inspiration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inspiration\SearchInspirationRequest;
use App\Inspiration\Exceptions\SourceException;
use App\Inspiration\SourceManager;
use App\Models\Moodboard;
use App\Models\Project;
use App\Models\SavedImage;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only inspiration surface: the explore mashup and the search fan-out.
 *
 * Every per-source call goes through SourceManager, which already owns the
 * write-through cache, the per-source throttle and the failure persistence.
 * A source that throws is downgraded to an empty entry here so a single dead
 * adapter can never turn the page into a 500.
 */
class ExploreController extends Controller
{
    /**
     * Items kept per source in the explore mashup (mirrors searchAll's cap).
     */
    private const MASHUP_PER_SOURCE = 12;

    public function __construct(private readonly SourceManager $sources) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $results = $this->mashup($user);

        return Inertia::render('inspiration/explore', [
            'sources' => $this->sources->statuses($user),
            'projects' => $this->projects($user),
            'boards' => $this->boards($user),
            'results' => $results,
            'saved' => $this->savedIndex($user),
            'search' => '',
            'source' => 'all',
        ]);
    }

    public function search(SearchInspirationRequest $request): Response
    {
        $user = $request->user();
        $query = (string) $request->validated('q', '');
        $source = (string) $request->validated('source', 'all');
        $page = (int) ($request->validated('page') ?? 1);

        $results = $source === 'all'
            ? $this->searchAll($user, $query)
            : $this->searchOne($user, $source, $query, $page);

        return Inertia::render('inspiration/explore', [
            'results' => $results,
            'search' => $query,
            'source' => $source,
        ]);
    }

    /**
     * One explore page per active and configured source, degraded per source.
     *
     * @return list<array{source: string, items: array<int, array<string, mixed>>, has_more: bool, from_cache: bool, age_minutes: ?int}>
     */
    private function mashup(User $user): array
    {
        $results = [];

        foreach ($this->sources->activeConfigured($user) as $key => $source) {
            try {
                $page = $this->sources->explore($user, $key, 1);
                $page['items'] = array_slice($page['items'], 0, self::MASHUP_PER_SOURCE);

                $results[] = $this->entry($key, $page);
            } catch (SourceException) {
                $results[] = $this->downEntry($key);
            }
        }

        return $results;
    }

    /**
     * Fan-out search, one page per active source (SourceManager isolates them).
     *
     * @return list<array{source: string, items: array<int, array<string, mixed>>, has_more: bool, from_cache: bool, age_minutes: ?int}>
     */
    private function searchAll(User $user, string $query): array
    {
        $results = [];

        foreach ($this->sources->searchAll($user, $query) as $key => $page) {
            $results[] = $this->entry($key, $page);
        }

        return $results;
    }

    /**
     * @return list<array{source: string, items: array<int, array<string, mixed>>, has_more: bool, from_cache: bool, age_minutes: ?int}>
     */
    private function searchOne(User $user, string $key, string $query, int $page): array
    {
        try {
            return [$this->entry($key, $this->sources->search($user, $key, $query, $page))];
        } catch (SourceException) {
            return [$this->downEntry($key)];
        }
    }

    /**
     * @param  array{items: array<int, array<string, mixed>>, has_more: bool, from_cache: bool, age_minutes: ?int}  $page
     * @return array{source: string, items: array<int, array<string, mixed>>, has_more: bool, from_cache: bool, age_minutes: ?int}
     */
    private function entry(string $key, array $page): array
    {
        return [
            'source' => $key,
            'items' => $page['items'],
            'has_more' => $page['has_more'],
            'from_cache' => $page['from_cache'],
            'age_minutes' => $page['age_minutes'],
        ];
    }

    /**
     * @return array{source: string, items: array<int, array<string, mixed>>, has_more: bool, from_cache: bool, age_minutes: ?int}
     */
    private function downEntry(string $key): array
    {
        return [
            'source' => $key,
            'items' => [],
            'has_more' => false,
            'from_cache' => false,
            'age_minutes' => null,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function projects(User $user): array
    {
        return Project::query()
            ->where('user_id', $user->id)
            ->personal()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, project_name: ?string, count: int}>
     */
    private function boards(User $user): array
    {
        return Moodboard::query()
            ->forUser($user)
            ->with('project:id,name')
            ->withCount('savedImages')
            ->orderBy('id')
            ->get()
            ->map(static fn (Moodboard $board): array => [
                'id' => $board->id,
                'name' => $board->name,
                'project_name' => $board->project?->name,
                'count' => (int) $board->saved_images_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Dedupe index for the UI: `"source:sourceId" => moodboard_id`.
     *
     * @return array<string, int>
     */
    private function savedIndex(User $user): array
    {
        return SavedImage::query()
            ->forUser($user)
            ->get(['source', 'source_id', 'moodboard_id'])
            ->mapWithKeys(static fn (SavedImage $image): array => [
                $image->source.':'.$image->source_id => (int) $image->moodboard_id,
            ])
            ->all();
    }
}
