<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * 500px v1 API (search endpoint).
 *
 * STATUS 2026-10: `api.500px.com/v1/photos/search` answers anonymously with
 * full results (total dashboard shows ~5.5M photos); `feature=popular` on the
 * list endpoint returns an empty page without credentials, so explore()
 * falls back to a curated search term like other single-surface sources. If
 * 500px starts enforcing `consumer_key` again, the source gains a credentials
 * field without changing its contract.
 */
class FiveHundredPxSource extends AbstractApiSource
{
    private const SEARCH_URL = 'https://api.500px.com/v1/photos/search';

    private const PAGE_SIZE = 24;

    private const EXPLORE_TERM = 'portrait';

    public function key(): string
    {
        return '500px';
    }

    public function label(): string
    {
        return '500px';
    }

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: true,
            supportsExplore: false,
            needsKey: false,
            hasMaturityLevels: false,
            maxPageSize: self::PAGE_SIZE,
        );
    }

    public function search(string $query, int $page, SourceQuery $queryOptions): Page
    {
        $query = trim($query);

        if ($query === '') {
            return Page::fromItems([], false, null);
        }

        $payload = $this->getJson(self::SEARCH_URL, [
            'term' => $query,
            'page' => $page,
            'rpp' => self::PAGE_SIZE,
        ]);

        return $this->mapToPage($payload);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        return $this->search(self::EXPLORE_TERM, $page, $queryOptions);
    }

    private function mapToPage(array $payload): Page
    {
        $items = [];

        foreach ($payload['photos'] ?? [] as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $item = InspirationItem::fromSource($this->key(), $this->normalize($raw));

            if ($item !== null) {
                $items[] = $item;
            }
        }

        $totalPages = (int) ($payload['total_pages'] ?? 1);

        return Page::fromItems($items, count($items) === self::PAGE_SIZE && $totalPages > 1, null);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalize(array $raw): array
    {
        $path = is_string($raw['url'] ?? null) ? $raw['url'] : null;
        $images = is_array($raw['images'] ?? null) ? $raw['images'] : [];
        $image = $this->bestImage($images);
        $user = is_array($raw['user'] ?? null) ? $raw['user'] : [];

        return [
            'sourceId' => (string) ($raw['id'] ?? ''),
            'title' => $this->stringValue($raw['name'] ?? null),
            'author' => $this->stringValue($user['fullname'] ?? null),
            'authorUrl' => $this->stringValue($user['username'] ?? null) !== null
                ? 'https://500px.com/p/'.$this->stringValue($user['username'])
                : null,
            'pageUrl' => $path !== null ? 'https://500px.com'.$path : null,
            'imageUrl' => $image['url'] ?? null,
            'thumbnailUrl' => $image['thumb'] ?? null,
            'tags' => [],
            'license' => null,
            'maturity' => null,
        ];
    }

    /**
     * Pick the 600px image as the primary and the 300px as the thumbnail.
     *
     * @param  array<int, array<string, mixed>>  $images
     * @return array{url: ?string, thumb: ?string}
     */
    private function bestImage(array $images): array
    {
        $url = null;
        $thumb = null;

        foreach ($images as $image) {
            if (! is_array($image)) {
                continue;
            }

            $imageUrl = $this->stringValue($image['url'] ?? null);

            if ($imageUrl === null) {
                continue;
            }

            $size = (int) ($image['size'] ?? 0);

            // Higher size numbers are larger renderings; 21 is 600px tall, 33
            // is a 600px crop, 3 is a 140px square.
            if ($size >= 21 && $url === null) {
                $url = $imageUrl;
            } elseif ($size < 21 && $thumb === null) {
                $thumb = $imageUrl;
            }
        }

        if ($url === null) {
            $url = $thumb;
        }

        if ($thumb === null) {
            $thumb = $url;
        }

        return ['url' => $url, 'thumb' => $thumb];
    }
}
