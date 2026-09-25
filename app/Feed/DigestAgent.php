<?php

namespace App\Feed;

use App\Ai\Agents\TelegramNotifier;
use App\Ai\Support\AiProviderResolver;
use App\Models\FeedDigest;
use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class DigestAgent
{
    public function __construct(
        private readonly FeedRanker $ranker,
        private readonly TelegramNotifier $notifier,
    ) {}

    /**
     * @param  Collection<int, FeedItem>  $items
     */
    public function generate(User $user, Collection $items): FeedDigest
    {
        $content = $this->summarize($user, $items);

        $digest = FeedDigest::query()
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->first()
            ?? new FeedDigest(['user_id' => $user->id, 'date' => now()->toDateString()]);

        $digest->fill([
            'content' => $content,
            'item_ids' => $items->pluck('id')->all(),
        ])->save();

        $headline = Str::limit(strip_tags($content), 200, preserveWords: true);

        if ($this->notifier->send($user, "📰 Digest de hoy\n{$headline}")) {
            $digest->forceFill(['sent_at' => now()])->save();
        }

        return $digest;
    }

    public function generateDue(): int
    {
        $generated = 0;

        if (now()->hour < (int) config('feed.digest_hour')) {
            return 0;
        }

        $userIds = FeedSource::query()->enabled()->distinct()->pluck('user_id');

        User::query()
            ->whereIn('id', $userIds)
            ->each(function (User $user) use (&$generated): void {
                if (FeedDigest::query()->where('user_id', $user->id)->whereDate('date', now()->toDateString())->exists()) {
                    return;
                }

                $items = $this->ranker->top($user, 10);

                if ($items->isEmpty()) {
                    return;
                }

                $this->generate($user, $items);
                $generated++;
            });

        return $generated;
    }

    /**
     * @param  Collection<int, FeedItem>  $items
     */
    protected function summarize(User $user, Collection $items): string
    {
        [$provider, $model] = AiProviderResolver::for($user);

        if ($provider !== null) {
            try {
                $response = (new FeedDigestAgent(
                    $items->map(fn (FeedItem $item): array => [
                        'title' => $item->title,
                        'url' => $item->url,
                        'source' => (string) $item->source?->name,
                        'summary' => Str::limit((string) $item->summary, 300),
                    ])->all(),
                ))->prompt('Generá el digest de hoy.', provider: $provider, model: $model, timeout: 90);

                $text = trim((string) $response);

                if ($text !== '') {
                    return $text;
                }
            } catch (Throwable) {
                // Cae al digest determinístico.
            }
        }

        return $this->fallback($items);
    }

    /**
     * @param  Collection<int, FeedItem>  $items
     */
    protected function fallback(Collection $items): string
    {
        $lines = $items->map(fn (FeedItem $item): string => "- **{$item->title}** — {$item->url}")->implode("\n");

        return "## Digest del día\n\n{$lines}";
    }
}
