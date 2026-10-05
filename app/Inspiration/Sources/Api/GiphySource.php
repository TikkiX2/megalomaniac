<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * GIPHY search and trending feeds.
 *
 * Search hits `/v1/gifs/search`, explore hits the keyless-query optional
 * `/v1/gifs/trending` feed; both share the same credential, pagination and
 * rating parameters. GIPHY accepts a single `rating` value per request, so the
 * `safe` branch sends `pg` (GIPHY's own values are g/pg/pg-13/r) and `allowed`
 * sends `r`. Task 13 flips the settings toggle; the branch lives here.
 *
 * There is no reliable offset echo in the response, so `hasMore` is inferred
 * from a full page of 24 raw entries.
 */
class GiphySource extends AbstractApiSource
{
    private const SEARCH_URL = 'https://api.giphy.com/v1/gifs/search';

    private const TRENDING_URL = 'https://api.giphy.com/v1/gifs/trending';

    private const PAGE_SIZE = 24;

    private const RATING_SAFE = 'pg';

    private const RATING_ALLOWED = 'r';

    public function key(): string
    {
        return 'giphy';
    }

    public function label(): string
    {
        return 'Giphy';
    }

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: true,
            supportsExplore: true,
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

        $payload = $this->getJson(self::SEARCH_URL, array_merge($this->baseQuery($page, $queryOptions), [
            'q' => $query,
        ]));

        return $this->mapToPage($payload, $page);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        $payload = $this->getJson(self::TRENDING_URL, $this->baseQuery($page, $queryOptions));

        return $this->mapToPage($payload, $page);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseQuery(int $page, SourceQuery $queryOptions): array
    {
        $current = max(1, $page);

        return [
            'api_key' => $this->configuredKey() ?? '',
            'limit' => self::PAGE_SIZE,
            'offset' => ($current - 1) * self::PAGE_SIZE,
            'rating' => $queryOptions->maturity === 'allowed' ? self::RATING_ALLOWED : self::RATING_SAFE,
        ];
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

        $current = max(1, $page);
        $hasMore = count($raw) >= self::PAGE_SIZE;

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

        $images = is_array($entry['images'] ?? null) ? $entry['images'] : [];
        $original = is_array($images['original'] ?? null) ? $images['original'] : [];
        $fixedWidth = is_array($images['fixed_width'] ?? null) ? $images['fixed_width'] : [];

        $id = $entry['id'] ?? null;
        $imageUrl = $this->stringValue($original['url'] ?? null)
            ?? $this->stringValue($fixedWidth['url'] ?? null);
        $slug = $this->stringValue($entry['slug'] ?? null);
        $pageUrl = $slug !== null
            ? 'https://giphy.com/gifs/'.$slug
            : ($this->stringValue($entry['url'] ?? null) ?? $this->stringValue($entry['embed_url'] ?? null));

        if ($imageUrl === null || $pageUrl === null || ! is_scalar($id) || trim((string) $id) === '') {
            return null;
        }

        return [
            'sourceId' => $this->identifier($id, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $this->stringValue($entry['title'] ?? null),
            'author' => $this->stringValue($entry['username'] ?? null),
            'authorUrl' => null,
            'thumbnailUrl' => $this->stringValue($fixedWidth['url'] ?? null),
            'width' => $original['width'] ?? null,
            'height' => $original['height'] ?? null,
            'tags' => [],
            'maturity' => $this->stringValue($entry['rating'] ?? null),
        ];
    }
}
