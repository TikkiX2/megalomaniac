<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * Wallhaven official API.
 *
 * Wallhaven exposes no public explore feed, so explore() falls back to the
 * search endpoint with a curated term. Maturity is fixed to safe
 * (`purity=100`); the global mapping lands in Task 13.
 */
class WallhavenSource extends AbstractApiSource
{
    private const SEARCH_URL = 'https://wallhaven.cc/api/v1/search';

    private const PAGE_SIZE = 24;

    private const EXPLORE_TERM = 'portrait';

    public function key(): string
    {
        return 'wallhaven';
    }

    public function label(): string
    {
        return 'Wallhaven';
    }

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: true,
            supportsExplore: false,
            needsKey: false,
            hasMaturityLevels: true,
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
            'q' => $query,
            'page' => $page,
            'purity' => 100,
            'sorting' => 'toplist',
        ]);

        return $this->mapToPage($payload, $page);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        return $this->search(self::EXPLORE_TERM, $page, $queryOptions);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapToPage(array $payload, int $page): Page
    {
        $raw = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $items = [];

        foreach ($raw as $entry) {
            $normalized = $this->normalizeRaw($entry);

            if ($normalized === null) {
                continue;
            }

            $item = InspirationItem::fromSource($this->key(), $normalized);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $current = is_numeric($meta['current_page'] ?? null) ? (int) $meta['current_page'] : $page;
        $last = is_numeric($meta['last_page'] ?? null) ? (int) $meta['last_page'] : null;
        $hasMore = $last !== null && $current < $last;

        return Page::fromItems($items, $hasMore, $hasMore ? $current + 1 : null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeRaw(mixed $entry): ?array
    {
        if (! is_array($entry)) {
            return null;
        }

        $pageUrl = $this->stringValue($entry['url'] ?? null);
        $imageUrl = $this->stringValue($entry['path'] ?? null);

        if ($pageUrl === null || $imageUrl === null) {
            return null;
        }

        $thumbs = is_array($entry['thumbs'] ?? null) ? $entry['thumbs'] : [];
        $colors = is_array($entry['colors'] ?? null) ? $entry['colors'] : [];
        $category = $this->stringValue($entry['category'] ?? null);

        return [
            'sourceId' => $this->identifier($entry['id'] ?? null, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => null,
            'author' => null,
            'authorUrl' => null,
            'thumbnailUrl' => $this->stringValue($thumbs['large'] ?? null)
                ?? $this->stringValue($thumbs['small'] ?? null)
                ?? $this->stringValue($thumbs['original'] ?? null),
            'width' => $entry['dimension_x'] ?? null,
            'height' => $entry['dimension_y'] ?? null,
            'tags' => $category !== null ? [$category] : [],
            'dominantColor' => $this->stringValue($colors[0] ?? null),
            'maturity' => $this->stringValue($entry['purity'] ?? null),
        ];
    }
}
