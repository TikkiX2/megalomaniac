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
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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
            'results' => $results,
            'search' => '',
            'source' => 'all',
            ...$this->boardsAndSaved($user),
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
            'sources' => $this->sources->statuses($user),
            'projects' => $this->projects($user),
            ...$this->boardsAndSaved($user),
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
            // The first render only fans out over the curated home subset;
            // every other active source loads lazily via its chip.
            if (! in_array($key, $this->homeSubset(), true)) {
                continue;
            }

            try {
                $page = $this->sources->explore($user, $key, 1);
                $page['items'] = array_slice($page['items'], 0, self::MASHUP_PER_SOURCE);

                $results[] = $this->entry($key, $page);
            } catch (SourceException) {
                $results[] = $this->downEntry($key);
            } catch (Throwable $exception) {
                $this->logInternalFailure($key, $exception);
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
        } catch (Throwable $exception) {
            $this->logInternalFailure($key, $exception);

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
            'items' => array_map(
                fn (array $item): array => $this->clientItem($item),
                $page['items'],
            ),
            'has_more' => $page['has_more'],
            'from_cache' => $page['from_cache'],
            'age_minutes' => $page['age_minutes'],
            'stale' => $page['stale'] ?? false,
        ];
    }

    private function homeSubset(): array
    {
        return array_values(array_filter(
            (array) config('inspiration.home_sources', []),
            static fn (mixed $key): bool => is_string($key),
        ));
    }

    /**
     * Map one source DTO payload (camelCase) to the snake_case keys the client
     * surface declares in `components/inspiration/shared.ts`.
     *
     * The internal DTO stays camelCase; this is the single web boundary where
     * the translation happens, so every entry() caller (mashup, search-all and
     * single-source search) emits the same shape the cards and SaveModal read.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function clientItem(array $item): array
    {
        return [
            'source' => $item['source'] ?? null,
            'source_id' => $item['sourceId'] ?? null,
            'title' => $item['title'] ?? null,
            'author' => $item['author'] ?? null,
            'author_url' => $item['authorUrl'] ?? null,
            'page_url' => $item['pageUrl'] ?? null,
            'image_url' => $item['imageUrl'] ?? null,
            'thumbnail_url' => $item['thumbnailUrl'] ?? null,
            'width' => $item['width'] ?? null,
            'height' => $item['height'] ?? null,
            'tags' => $item['tags'] ?? [],
            'dominant_color' => $item['dominantColor'] ?? null,
            'license' => $item['license'] ?? null,
            'maturity' => $item['maturity'] ?? null,
        ];
    }

    /**
     * Persist an internal adapter failure (an exception the manager does not
     * normalize) without leaking its message to the client.
     */
    private function logInternalFailure(string $key, Throwable $exception): void
    {
        Log::warning('inspiration: '.$key.' threw '.$exception::class, [
            'message' => $exception->getMessage(),
        ]);
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
            'stale' => false,
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
     * @return list<array{id: int, name: string, project_id: ?int, project_name: ?string, count: int}>
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
                'project_id' => $board->project_id !== null ? (int) $board->project_id : null,
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

    /**
     * Boards and saved index shared by the mashup and the search response.
     *
     * The search route renders them too so a partial `only(['saved', 'boards'])`
     * reload fired after a save resolves on either URL (see the explore React
     * page's `onSaved` handler).
     *
     * @return array{boards: list<array{id: int, name: string, project_id: ?int, project_name: ?string, count: int}>, saved: array<string, int>}
     */
    private function boardsAndSaved(User $user): array
    {
        return [
            'boards' => $this->boards($user),
            'saved' => $this->savedIndex($user),
        ];
    }
}
