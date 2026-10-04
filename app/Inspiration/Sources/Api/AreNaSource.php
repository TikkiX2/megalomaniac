<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * Are.na v2 search API.
 *
 * Are.na has no anonymous explore feed, so explore() falls back to the search
 * endpoint with a curated term. The v2 search envelope paginates with
 * `current_page`/`total_pages`; only `blocks[]` entries that carry an image URL
 * survive mapping (Text/Attachment blocks are skipped).
 *
 * The image shape shifted across v2 responses: modern blocks expose
 * `image.original.url`/`image.display.url`, while some payloads still nest
 * `original_image.display.url`. Both are handled, and the original URL is
 * preferred for the full-size image.
 */
class AreNaSource extends AbstractApiSource
{
    private const SEARCH_URL = 'https://api.are.na/v2/search';

    private const BLOCK_URL = 'https://www.are.na/block/';

    private const PAGE_SIZE = 24;

    private const EXPLORE_TERM = 'portrait';

    public function key(): string
    {
        return 'arena';
    }

    public function label(): string
    {
        return 'Are.na';
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
            'q' => $query,
            'type' => 'block',
            'page' => max(1, $page),
            'per' => self::PAGE_SIZE,
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
        $raw = is_array($payload['blocks'] ?? null) ? $payload['blocks'] : [];
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

        $current = is_numeric($payload['current_page'] ?? null) ? (int) $payload['current_page'] : $page;
        $total = is_numeric($payload['total_pages'] ?? null) ? (int) $payload['total_pages'] : null;
        $hasMore = $total !== null && $current < $total;

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

        if (! is_scalar($id) || trim((string) $id) === '') {
            return null;
        }

        $image = is_array($entry['image'] ?? null) ? $entry['image'] : [];
        $originalImage = is_array($entry['original_image'] ?? null) ? $entry['original_image'] : [];

        $imageUrl = $this->stringValue($image['original']['url'] ?? null)
            ?? $this->stringValue($originalImage['original']['url'] ?? null)
            ?? $this->stringValue($originalImage['display']['url'] ?? null)
            ?? $this->stringValue($image['display']['url'] ?? null)
            ?? $this->stringValue($entry['image_url'] ?? null);

        if ($imageUrl === null) {
            return null;
        }

        $pageUrl = self::BLOCK_URL.$id;
        $user = is_array($entry['user'] ?? null) ? $entry['user'] : [];

        return [
            'sourceId' => $this->identifier($id, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $this->stringValue($entry['title'] ?? null),
            'author' => $this->stringValue($user['username'] ?? null),
            'authorUrl' => null,
            'thumbnailUrl' => $this->stringValue($image['display']['url'] ?? null)
                ?? $this->stringValue($originalImage['display']['url'] ?? null),
            'width' => null,
            'height' => null,
            'tags' => [],
            'maturity' => null,
        ];
    }
}
