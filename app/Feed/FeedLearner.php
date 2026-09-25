<?php

namespace App\Feed;

use App\Models\FeedItem;
use App\Models\FeedPreference;
use App\Models\FeedSignal;

class FeedLearner
{
    public function record(FeedItem $item, string $signal): void
    {
        $recorded = FeedSignal::query()->firstOrCreate([
            'feed_item_id' => $item->id,
            'user_id' => $item->user_id,
            'type' => $signal,
        ]);

        if (! $recorded->wasRecentlyCreated) {
            return;
        }

        $preferences = FeedPreference::forUser($item->user);
        $delta = match ($signal) {
            FeedSignal::LIKE => 1.0,
            FeedSignal::SAVE => 1.5,
            FeedSignal::OPEN => 0.3,
            FeedSignal::DISLIKE => -1.0,
            FeedSignal::HIDE => -2.0,
            default => 0.0,
        };

        $weights = $preferences->topic_weights ?? [];

        foreach ($this->tokens($item) as $token) {
            $weights[$token] = max(-10.0, min(10.0, (float) ($weights[$token] ?? 0.0) + $delta));
        }

        $sourceWeights = $preferences->source_weights ?? [];
        $sourceWeights[$item->feed_source_id] = max(
            -2.0,
            min(5.0, (float) ($sourceWeights[$item->feed_source_id] ?? 1.0) + ($delta * 0.1)),
        );

        $counters = match ($signal) {
            FeedSignal::LIKE => ['likes' => $preferences->likes + 1],
            FeedSignal::DISLIKE => ['dislikes' => $preferences->dislikes + 1],
            FeedSignal::SAVE => ['saves' => $preferences->saves + 1],
            FeedSignal::OPEN => ['opens' => $preferences->opens + 1],
            default => [],
        };

        $preferences->forceFill(array_merge([
            'topic_weights' => $weights,
            'source_weights' => $sourceWeights,
        ], $counters))->save();

        if ($signal === FeedSignal::HIDE) {
            $item->forceFill(['hidden_at' => now()])->save();
        }

        if ($signal === FeedSignal::SAVE) {
            $item->forceFill(['is_saved' => true])->save();
        }

        $this->updateCentroid($preferences, $item, $delta);
    }

    protected function updateCentroid(FeedPreference $preferences, FeedItem $item, float $delta): void
    {
        if ($delta <= 0 || empty($item->embedding)) {
            return;
        }

        $centroid = $preferences->embedding;

        if (empty($centroid) || count($centroid) !== count($item->embedding)) {
            $preferences->forceFill(['embedding' => $item->embedding])->save();

            return;
        }

        $updated = [];

        foreach ($centroid as $index => $value) {
            $updated[$index] = (0.9 * (float) $value) + (0.1 * (float) $item->embedding[$index]);
        }

        $preferences->forceFill(['embedding' => $updated])->save();
    }

    /**
     * @return string[]
     */
    protected function tokens(FeedItem $item): array
    {
        $tokens = preg_split('/[^a-z0-9áéíóúñ]+/u', mb_strtolower($item->title.' '.($item->summary ?? ''))) ?: [];

        return array_values(array_unique(array_filter($tokens, fn (string $token): bool => mb_strlen($token) > 3)));
    }
}
