<?php

namespace App\Http\Controllers\Feed;

use App\Ai\Support\AiProviderResolver;
use App\Feed\DigestAgent;
use App\Feed\FeedLearner;
use App\Feed\FeedRanker;
use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\FeedDigest;
use App\Models\FeedItem;
use App\Models\FeedSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public function __construct(
        private readonly FeedRanker $ranker,
        private readonly FeedLearner $learner,
        private readonly DigestAgent $digest,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $tab = (string) $request->query('tab', 'all');

        $items = $this->ranker->top($user, 50);

        if ($tab === 'saved') {
            $items = $items->filter(fn (FeedItem $item): bool => $item->is_saved)->values();
        }

        $digest = FeedDigest::query()
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        return Inertia::render('feed/index', [
            'digest' => $digest ? [
                'content' => $digest->content,
                'sent_at' => $digest->sent_at?->toIso8601String(),
            ] : null,
            'items' => $items->map(fn (FeedItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'url' => $item->url,
                'summary' => $item->summary,
                'source' => $item->source?->name,
                'source_kind' => $item->source?->kind,
                'published_at' => $item->published_at?->toIso8601String(),
                'is_saved' => $item->is_saved,
                'score' => round((float) $item->getAttribute('rank_score'), 3),
            ])->values(),
            'sources' => FeedSource::query()->forUser($user)->orderBy('name')->get()->map(fn (FeedSource $source): array => [
                'id' => $source->id,
                'name' => $source->name,
                'kind' => $source->kind,
                'enabled' => $source->enabled,
                'fetch_error' => $source->fetch_error,
                'last_fetched_at' => $source->last_fetched_at?->toIso8601String(),
            ])->values(),
            'tab' => $tab,
            'hasEmbeddings' => AiProviderResolver::embeddingsFor($user)[0] !== null,
        ]);
    }

    public function settings(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('feed/settings', [
            'sources' => FeedSource::query()->forUser($user)->orderBy('name')->get()->map(fn (FeedSource $source): array => [
                'id' => $source->id,
                'kind' => $source->kind,
                'name' => $source->name,
                'config' => $source->config,
                'enabled' => $source->enabled,
                'connection_id' => $source->connection_id,
                'fetch_error' => $source->fetch_error,
                'last_fetched_at' => $source->last_fetched_at?->toIso8601String(),
            ])->values(),
            'connections' => Connection::query()
                ->forUser($user)
                ->enabled()
                ->whereIn('kind', ['reddit', 'youtube'])
                ->orderBy('name')
                ->get()
                ->map(fn ($connection): array => ['id' => $connection->id, 'name' => $connection->name, 'kind' => $connection->kind])
                ->values(),
            'digestHour' => (int) config('feed.digest_hour'),
        ]);
    }

    public function signal(Request $request, int $item): RedirectResponse
    {
        $validated = $request->validate([
            'signal' => ['required', Rule::in(['like', 'dislike', 'save', 'hide', 'open'])],
        ]);

        $model = FeedItem::query()
            ->forUser($request->user())
            ->findOrFail($item);

        $this->learner->record($model, $validated['signal']);

        return back();
    }

    public function digestNow(Request $request): RedirectResponse
    {
        $items = $this->ranker->top($request->user(), 10);

        if ($items->isEmpty()) {
            return back()->with('error', 'No hay items para resumir todavía.');
        }

        $this->digest->generate($request->user(), $items);

        return back()->with('success', 'Digest generado.');
    }
}
