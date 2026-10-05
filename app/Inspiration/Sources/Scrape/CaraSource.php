<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * Cara explore feed (tier 3, best effort).
 *
 * Cara is a Next.js app, so the explore shell carries its data in the standard
 * `__NEXT_DATA__` application/json script under `props.pageProps.posts` (an
 * `initialState.posts` / `feed.posts` shape is also scanned defensively). Each
 * post exposes `id`, an optional `title`/`caption`, a `user` object and a media
 * list — `postMedia` (current shape) or `media`/`images` (legacy) — whose entries
 * are `{url,width,height,type}`. Entries typed as anything other than `image`
 * (videos) are skipped, and a post without a usable image is discarded.
 *
 * Cara answers a Cloudflare challenge (HTTP 403) to non-browser traffic
 * (verified 2026-10-05), so explore() raises a SourceException and the cached
 * payload is served instead. search() delegates to the explore feed because Cara
 * ships no parseable term search; supportsSearch=false. No auth is used.
 */
final class CaraSource extends AbstractEmbeddedJsonSource
{
    private const EXPLORE_URL = 'https://cara.app/explore';

    private const POST_URL = 'https://cara.app/post/%s';

    private const PROFILE_URL = 'https://cara.app/profile/%s';

    public function key(): string
    {
        return 'cara';
    }

    public function label(): string
    {
        return 'Cara';
    }

    protected function exploreUrl(): string
    {
        return self::EXPLORE_URL;
    }

    protected function itemsFromPayloads(array $payloads): array
    {
        $items = [];

        foreach ($payloads as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            foreach ($this->postLists($payload) as $posts) {
                foreach ($posts as $post) {
                    $normalized = $this->normalizePost($post);

                    if ($normalized !== null) {
                        $items[$normalized['sourceId']] = $normalized;
                    }
                }
            }
        }

        return array_values($items);
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<int, array<mixed>>
     */
    private function postLists(array $payload): array
    {
        $candidates = [
            $payload['props']['pageProps']['posts'] ?? null,
            $payload['props']['pageProps']['initialState']['posts'] ?? null,
            $payload['props']['pageProps']['feed']['posts'] ?? null,
            $payload['pageProps']['posts'] ?? null,
            $payload['posts'] ?? null,
        ];

        return array_values(array_filter($candidates, 'is_array'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizePost(mixed $post): ?array
    {
        if (! is_array($post)) {
            return null;
        }

        $id = $this->scalarString($post['id'] ?? null);
        $media = $this->postImage($post['postMedia'] ?? $post['media'] ?? $post['images'] ?? null);

        if ($id === null || $media === null) {
            return null;
        }

        $author = $this->postAuthor($post['user'] ?? null);

        return [
            'sourceId' => $id,
            'pageUrl' => sprintf(self::POST_URL, $id),
            'imageUrl' => $media['url'],
            'thumbnailUrl' => $media['url'],
            'title' => $this->firstString(
                $post['title'] ?? null,
                $post['caption'] ?? null,
                $post['description'] ?? null,
            ),
            'author' => $author,
            'authorUrl' => $author === null ? null : sprintf(self::PROFILE_URL, $author),
            'width' => $media['width'],
            'height' => $media['height'],
        ];
    }

    /**
     * Pick the first still image in the media list, skipping video entries.
     *
     * @return array{url: string, width: ?int, height: ?int}|null
     */
    private function postImage(mixed $media): ?array
    {
        if (! is_array($media)) {
            return null;
        }

        foreach ($media as $entry) {
            if (is_string($entry)) {
                $url = $this->scalarString($entry);

                if ($url !== null) {
                    return ['url' => $url, 'width' => null, 'height' => null];
                }

                continue;
            }

            if (! is_array($entry)) {
                continue;
            }

            $type = $entry['type'] ?? null;

            if (is_string($type) && $type !== '' && strtolower($type) !== 'image') {
                continue;
            }

            $url = $this->scalarString($entry['url'] ?? null);

            if ($url === null) {
                continue;
            }

            return [
                'url' => $url,
                'width' => is_numeric($entry['width'] ?? null) ? (int) $entry['width'] : null,
                'height' => is_numeric($entry['height'] ?? null) ? (int) $entry['height'] : null,
            ];
        }

        return null;
    }

    private function postAuthor(mixed $user): ?string
    {
        if (! is_array($user)) {
            return $this->scalarString($user);
        }

        return $this->firstString(
            $user['username'] ?? null,
            $user['handle'] ?? null,
            $user['name'] ?? null,
        );
    }
}
