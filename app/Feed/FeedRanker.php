<?php

namespace App\Feed;

use App\Ai\Support\AiProviderResolver;
use App\Models\FeedItem;
use App\Models\FeedPreference;
use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;
use Throwable;

class FeedRanker
{
    /**
     * @return Collection<int, FeedItem>
     */
    public function top(User $user, int $limit = 30): Collection
    {
        $windowDays = (int) config('feed.window_days');

        $items = FeedItem::query()
            ->forUser($user)
            ->visible()
            ->where(fn ($query) => $query
                ->where('published_at', '>=', now()->subDays($windowDays))
                ->orWhereNull('published_at'))
            ->orderByDesc('published_at')
            ->limit(300)
            ->get();

        if ($items->isEmpty()) {
            return collect();
        }

        $preferences = FeedPreference::forUser($user);
        [$embeddingsProvider, $embeddingsModel] = AiProviderResolver::embeddingsFor($user);

        $useEmbeddings = $embeddingsProvider !== null && filled($preferences->embedding);

        if ($useEmbeddings) {
            $this->ensureEmbeddings($items, $embeddingsProvider, $embeddingsModel);
        } else {
            $this->scoreWithLlm($user, $preferences, $items);
        }

        $scored = $items->map(function (FeedItem $item) use ($preferences, $useEmbeddings, $windowDays): FeedItem {
            $similarity = $useEmbeddings
                ? $this->cosine($preferences->embedding ?? [], $item->embedding ?? [])
                : ($item->score !== null ? (float) $item->score : $this->lexical($preferences, $item));

            $reference = $item->published_at ?? $item->fetched_at;
            $hours = $reference ? max(0, $reference->diffInHours(now())) : $windowDays * 24;
            $recency = max(0.0, 1 - ($hours / max(1, $windowDays * 24)));

            $sourceWeight = (float) (($preferences->source_weights ?? [])[$item->feed_source_id] ?? 1.0);
            $engagement = $item->is_saved ? 1.0 : 0.0;

            $item->setAttribute(
                'rank_score',
                (0.55 * $similarity) + (0.25 * $recency) + (0.15 * min(1.0, $sourceWeight / 2)) + (0.05 * $engagement),
            );

            return $item;
        });

        return $scored->sortByDesc('rank_score')->take($limit)->values();
    }

    /**
     * @param  Collection<int, FeedItem>  $items
     */
    protected function ensureEmbeddings(Collection $items, string $provider, ?string $model): void
    {
        $missing = $items->filter(fn (FeedItem $item): bool => empty($item->embedding))->take(100);

        if ($missing->isEmpty()) {
            return;
        }

        try {
            $response = Embeddings::for(
                $missing->map(fn (FeedItem $item): string => $item->title.' '.($item->summary ?? ''))->all(),
            )->cache()->generate(provider: $provider, model: $model);

            foreach ($missing->values() as $index => $item) {
                $vector = $response->embeddings[$index] ?? null;

                if (is_array($vector)) {
                    $item->forceFill(['embedding' => $vector])->save();
                }
            }
        } catch (Throwable) {
            // Sin embeddings se cae al scoring léxico.
        }
    }

    /**
     * @param  Collection<int, FeedItem>  $items
     */
    protected function scoreWithLlm(User $user, FeedPreference $preferences, Collection $items): void
    {
        [$provider, $model] = AiProviderResolver::for($user);

        if ($provider === null) {
            return;
        }

        $candidates = $items
            ->filter(fn (FeedItem $item): bool => $item->scored_at === null)
            ->take(30)
            ->map(fn (FeedItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'summary' => (string) $item->summary,
            ])
            ->values();

        if ($candidates->isEmpty()) {
            return;
        }

        try {
            $response = (new FeedScoringAgent($candidates->all(), $preferences->topic_weights ?? []))
                ->prompt('Puntuá estos items.', provider: $provider, model: $model, timeout: 60);

            $scores = $this->parseScores((string) $response);

            foreach ($items as $item) {
                if (array_key_exists($item->id, $scores)) {
                    $item->forceFill(['score' => $scores[$item->id], 'scored_at' => now()])->save();
                }
            }
        } catch (Throwable) {
            // Sin scoring LLM queda el léxico.
        }
    }

    /**
     * @return array<int, float>
     */
    protected function parseScores(string $text): array
    {
        $start = strpos($text, '[');
        $end = strrpos($text, ']');

        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        if (! is_array($decoded)) {
            return [];
        }

        $scores = [];

        foreach ($decoded as $entry) {
            if (is_array($entry) && isset($entry['id'], $entry['score'])) {
                $scores[(int) $entry['id']] = max(0.0, min(1.0, (float) $entry['score']));
            }
        }

        return $scores;
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    protected function cosine(array $a, array $b): float
    {
        if ($a === [] || $b === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $normA += $value ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $dot / (sqrt($normA) * sqrt($normB))));
    }

    protected function lexical(FeedPreference $preferences, FeedItem $item): float
    {
        $weights = $preferences->topic_weights ?? [];

        if ($weights === []) {
            return 0.0;
        }

        $tokens = preg_split('/[^a-z0-9áéíóúñ]+/u', mb_strtolower($item->title.' '.($item->summary ?? ''))) ?: [];
        $tokens = array_filter($tokens, fn (string $token): bool => mb_strlen($token) > 3);

        if ($tokens === []) {
            return 0.0;
        }

        $sum = 0.0;

        foreach ($tokens as $token) {
            $sum += (float) ($weights[$token] ?? 0.0);
        }

        return max(0.0, min(1.0, $sum / sqrt(count($tokens)) / 5));
    }
}
