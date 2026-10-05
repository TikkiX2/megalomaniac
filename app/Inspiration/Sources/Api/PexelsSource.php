<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;
use Illuminate\Http\Client\PendingRequest;

/**
 * Pexels official search API.
 *
 * Requires the user key as a `Bearer` Authorization header. Pexels has no
 * anonymous explore feed, so explore() falls back to the search endpoint with a
 * curated term. There is no maturity parameter, hence `hasMaturityLevels:false`.
 */
class PexelsSource extends AbstractApiSource
{
    private const SEARCH_URL = 'https://api.pexels.com/v1/search';

    private const PAGE_SIZE = 24;

    private const EXPLORE_TERM = 'portrait';

    public function key(): string
    {
        return 'pexels';
    }

    public function label(): string
    {
        return 'Pexels';
    }

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: true,
            supportsExplore: false,
            needsKey: true,
            hasMaturityLevels: false,
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

        $payload = $this->getJson(self::SEARCH_URL, [
            'query' => $query,
            'per_page' => self::PAGE_SIZE,
            'page' => max(1, $page),
        ]);

        return $this->mapToPage($payload, $page);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        return $this->search(self::EXPLORE_TERM, $page, $queryOptions);
    }

    protected function authorize(PendingRequest $request): PendingRequest
    {
        $key = $this->configuredKey();

        return $key === null ? $request : $request->withToken($key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapToPage(array $payload, int $page): Page
    {
        $raw = is_array($payload['photos'] ?? null) ? $payload['photos'] : [];
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
        $hasMore = $this->stringValue($payload['next_page'] ?? null) !== null;

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

        $src = is_array($entry['src'] ?? null) ? $entry['src'] : [];

        $imageUrl = $this->stringValue($src['large'] ?? null)
            ?? $this->stringValue($src['large2x'] ?? null)
            ?? $this->stringValue($src['original'] ?? null)
            ?? $this->stringValue($src['medium'] ?? null)
            ?? $this->stringValue($src['small'] ?? null);
        $pageUrl = $this->stringValue($entry['url'] ?? null);
        $id = $entry['id'] ?? null;

        if ($imageUrl === null || $pageUrl === null || ! is_scalar($id) || trim((string) $id) === '') {
            return null;
        }

        return [
            'sourceId' => $this->identifier($id, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => null,
            'author' => $this->stringValue($entry['photographer'] ?? null),
            'authorUrl' => $this->stringValue($entry['photographer_url'] ?? null),
            'thumbnailUrl' => $this->stringValue($src['small'] ?? null)
                ?? $this->stringValue($src['tiny'] ?? null),
            'width' => $entry['width'] ?? null,
            'height' => $entry['height'] ?? null,
            'tags' => [],
            'maturity' => null,
        ];
    }
}
