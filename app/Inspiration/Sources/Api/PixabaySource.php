<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * Pixabay official API.
 *
 * The user key travels as the `key` query parameter. Pixabay has no dedicated
 * explore feed, so explore() falls back to the search endpoint with a curated
 * term. Maturity maps onto `safesearch`: `safe` sends 1 while `allowed` sends 0
 * (unfiltered). Task 13 flips the settings toggle; the branch lives here.
 */
class PixabaySource extends AbstractApiSource
{
    private const API_URL = 'https://pixabay.com/api/';

    private const PAGE_SIZE = 24;

    private const EXPLORE_TERM = 'portrait';

    public function key(): string
    {
        return 'pixabay';
    }

    public function label(): string
    {
        return 'Pixabay';
    }

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: true,
            supportsExplore: false,
            needsKey: true,
            hasMaturityLevels: true,
            maxPageSize: self::PAGE_SIZE,
        );
    }

    public function isConfigured(): bool
    {
        return $this->configuredKey() !== null;
    }

    public function search(string $query, int $page, SourceQuery $queryOptions): Page
    {
        $query = trim($query);

        if ($query === '') {
            return Page::fromItems([], false, null);
        }

        $payload = $this->getJson(self::API_URL, [
            'key' => $this->configuredKey() ?? '',
            'q' => $query,
            'per_page' => self::PAGE_SIZE,
            'page' => max(1, $page),
            'safesearch' => $this->safeSearch($queryOptions),
        ]);

        return $this->mapToPage($payload, $page);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        return $this->search(self::EXPLORE_TERM, $page, $queryOptions);
    }

    private function safeSearch(SourceQuery $queryOptions): int
    {
        return $queryOptions->maturity === 'allowed' ? 0 : 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapToPage(array $payload, int $page): Page
    {
        $raw = is_array($payload['hits'] ?? null) ? $payload['hits'] : [];
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

        $current = max(1, $page);
        $total = is_numeric($payload['totalHits'] ?? null) ? (int) $payload['totalHits'] : 0;
        $hasMore = $total > $current * self::PAGE_SIZE;

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

        $id = $entry['id'] ?? null;
        $pageUrl = $this->stringValue($entry['pageURL'] ?? null);
        $imageUrl = $this->stringValue($entry['webformatURL'] ?? null)
            ?? $this->stringValue($entry['largeImageURL'] ?? null);

        if (! is_scalar($id) || trim((string) $id) === '' || $pageUrl === null || $imageUrl === null) {
            return null;
        }

        return [
            'sourceId' => $this->identifier($id, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => null,
            'author' => $this->stringValue($entry['user'] ?? null),
            'authorUrl' => null,
            'thumbnailUrl' => $this->stringValue($entry['previewURL'] ?? null),
            'width' => $entry['imageWidth'] ?? null,
            'height' => $entry['imageHeight'] ?? null,
            'tags' => $this->splitTags($entry['tags'] ?? null),
            'maturity' => null,
        ];
    }

    /**
     * Pixabay reports tags as a single comma-separated string.
     *
     * @return array<int, string>
     */
    private function splitTags(mixed $value): array
    {
        if (! is_string($value)) {
            return $this->tagList($value);
        }

        $tags = array_map('trim', explode(',', $value));

        return $this->tagList(array_values(array_filter($tags, static fn (string $tag): bool => $tag !== '')));
    }
}
