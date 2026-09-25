<?php

namespace App\Feed;

use App\Integrations\ConnectorRegistry;
use App\Models\Connection;
use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FeedIngestor
{
    public function ingest(FeedSource $source): int
    {
        try {
            $items = $this->fetch($source);
        } catch (Throwable $e) {
            $source->forceFill([
                'fetch_error' => Str::limit($e->getMessage(), 500),
                'last_fetched_at' => now(),
            ])->save();

            return 0;
        }

        $created = 0;

        foreach ($items as $data) {
            $attributes = [
                'user_id' => $source->user_id,
                'title' => Str::limit((string) $data['title'], 500, ''),
                'url' => Str::limit((string) $data['url'], 1000, ''),
                'author' => $data['author'] !== null ? Str::limit((string) $data['author'], 150, '') : null,
                'summary' => $data['summary'] !== null ? Str::limit(strip_tags((string) $data['summary']), 2000, '') : null,
                'content_hash' => sha1($data['title'].$data['url']),
                'published_at' => $data['published_at'],
                'fetched_at' => now(),
            ];

            $item = FeedItem::query()->firstOrCreate(
                [
                    'feed_source_id' => $source->id,
                    'external_id' => (string) $data['external_id'],
                ],
                $attributes,
            );

            if ($item->wasRecentlyCreated) {
                $created++;
            } else {
                $item->update(collect($attributes)->except('fetched_at')->all());
            }
        }

        $source->forceFill([
            'fetch_error' => null,
            'last_fetched_at' => now(),
        ])->save();

        $this->prune($source->user);

        return $created;
    }

    public function ingestDue(): int
    {
        $created = 0;

        FeedSource::query()
            ->enabled()
            ->each(function (FeedSource $source) use (&$created): void {
                $created += $this->ingest($source);
            });

        return $created;
    }

    /**
     * @return array<int, array{external_id: string, title: string, url: string, author: ?string, summary: ?string, published_at: mixed}>
     */
    protected function fetch(FeedSource $source): array
    {
        return match ($source->kind) {
            'reddit' => $this->fetchReddit($source),
            'youtube' => $this->fetchYoutube($source),
            default => $this->fetchRss($source),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchRss(FeedSource $source): array
    {
        $url = (string) ($source->config['url'] ?? ($source->kind === 'hackernews' ? 'https://news.ycombinator.com/rss' : ''));

        if ($url === '') {
            throw new RuntimeException('Falta la URL del feed.');
        }

        $connection = new Connection([
            'kind' => 'rss',
            'auth_type' => 'none',
            'base_url' => $url,
            'transport' => 'direct',
            'credentials' => [],
            'enabled' => true,
        ]);

        $result = app(ConnectorRegistry::class)
            ->for('rss')
            ->execute($connection, 'feed.fetch', ['limit' => (int) config('feed.ingest_limit')]);

        if (! $result->ok) {
            throw new RuntimeException($result->error ?? 'No se pudo leer el feed.');
        }

        return array_map(fn (array $item): array => [
            'external_id' => (string) $item['external_id'],
            'title' => (string) $item['title'],
            'url' => (string) $item['url'],
            'author' => $item['author'] ?? null,
            'summary' => $item['summary'] ?? null,
            'published_at' => $item['published_at'] ? Carbon::parse($item['published_at']) : null,
        ], $result->data['items'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchReddit(FeedSource $source): array
    {
        $connection = $this->connection($source);
        $subreddit = (string) ($source->config['subreddit'] ?? '');
        $sort = (string) ($source->config['sort'] ?? 'hot');

        $result = app(ConnectorRegistry::class)
            ->for('reddit')
            ->execute($connection, "subreddit.{$sort}", [
                'sub' => $subreddit,
                'limit' => (int) config('feed.ingest_limit'),
            ]);

        if (! $result->ok) {
            throw new RuntimeException($result->error ?? 'Reddit no respondió.');
        }

        $children = $result->data['data']['children'] ?? [];

        return array_map(fn (array $child): array => [
            'external_id' => (string) ($child['data']['name'] ?? ''),
            'title' => (string) ($child['data']['title'] ?? ''),
            'url' => (string) ($child['data']['url'] ?? ''),
            'author' => $child['data']['author'] ?? null,
            'summary' => $child['data']['selftext'] ?? null,
            'published_at' => isset($child['data']['created_utc']) ? Carbon::createFromTimestamp((int) $child['data']['created_utc']) : null,
        ], $children);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchYoutube(FeedSource $source): array
    {
        $connection = $this->connection($source);
        $connector = app(ConnectorRegistry::class)->for('youtube');
        $items = [];

        foreach ((array) ($source->config['channel_ids'] ?? []) as $channelId) {
            $channel = $connector->execute($connection, 'channels.get', ['channel_id' => $channelId]);

            if (! $channel->ok) {
                throw new RuntimeException($channel->error ?? 'YouTube no respondió.');
            }

            $uploads = $channel->data['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? null;

            if (! $uploads) {
                continue;
            }

            $playlist = $connector->execute($connection, 'playlist.items', [
                'playlist_id' => $uploads,
                'max_results' => (int) config('feed.ingest_limit'),
            ]);

            if (! $playlist->ok) {
                throw new RuntimeException($playlist->error ?? 'YouTube no respondió.');
            }

            foreach ($playlist->data['items'] ?? [] as $item) {
                $videoId = (string) ($item['snippet']['resourceId']['videoId'] ?? '');

                if ($videoId === '') {
                    continue;
                }

                $items[] = [
                    'external_id' => $videoId,
                    'title' => (string) ($item['snippet']['title'] ?? ''),
                    'url' => "https://youtube.com/watch?v={$videoId}",
                    'author' => $item['snippet']['channelTitle'] ?? null,
                    'summary' => $item['snippet']['description'] ?? null,
                    'published_at' => isset($item['snippet']['publishedAt']) ? Carbon::parse($item['snippet']['publishedAt']) : null,
                ];
            }
        }

        return $items;
    }

    protected function connection(FeedSource $source): Connection
    {
        $connection = $source->connection;

        if (! $connection) {
            throw new RuntimeException('La fuente no tiene una conexión configurada.');
        }

        return $connection;
    }

    protected function prune(User $user): void
    {
        FeedItem::query()
            ->forUser($user)
            ->where('is_saved', false)
            ->where('fetched_at', '<', now()->subDays((int) config('feed.retention_days')))
            ->delete();

        $max = (int) config('feed.max_items');

        $excess = FeedItem::query()->forUser($user)->where('is_saved', false)->count() - $max;

        if ($excess > 0) {
            FeedItem::query()
                ->forUser($user)
                ->where('is_saved', false)
                ->orderBy('fetched_at')
                ->limit($excess)
                ->delete();
        }
    }
}
