<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;
use Illuminate\Http\Client\PendingRequest;

/**
 * Unsplash official search API.
 *
 * Requires the user key as a `Client-ID` Authorization header. Unsplash has no
 * anonymous explore feed, so explore() falls back to the search endpoint with a
 * curated term. There is no maturity parameter, hence `hasMaturityLevels:false`.
 */
class UnsplashSource extends AbstractApiSource
{
    private const SEARCH_URL = 'https://api.unsplash.com/search/photos';

    private const PAGE_SIZE = 24;

    private const EXPLORE_TERM = 'portrait';

    public function key(): string
    {
        return 'unsplash';
    }

    public function label(): string
    {
        return 'Unsplash';
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

        return $key === null ? $request : $request->withToken($key, 'Client-ID');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapToPage(array $payload, int $page): Page
    {
        $raw = is_array($payload['results'] ?? null) ? $payload['results'] : [];
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
        $totalPages = is_numeric($payload['total_pages'] ?? null) ? (int) $payload['total_pages'] : 0;
        $hasMore = $totalPages > $current;

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

        $urls = is_array($entry['urls'] ?? null) ? $entry['urls'] : [];
        $links = is_array($entry['links'] ?? null) ? $entry['links'] : [];
        $user = is_array($entry['user'] ?? null) ? $entry['user'] : [];
        $userLinks = is_array($user['links'] ?? null) ? $user['links'] : [];

        $imageUrl = $this->stringValue($urls['full'] ?? null)
            ?? $this->stringValue($urls['regular'] ?? null)
            ?? $this->stringValue($urls['small'] ?? null);
        $pageUrl = $this->stringValue($links['html'] ?? null);
        $id = $entry['id'] ?? null;

        if ($imageUrl === null || $pageUrl === null || ! is_scalar($id) || trim((string) $id) === '') {
            return null;
        }

        return [
            'sourceId' => $this->identifier($id, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $this->stringValue($entry['alt_description'] ?? null)
                ?? $this->stringValue($entry['description'] ?? null),
            'author' => $this->stringValue($user['name'] ?? null),
            'authorUrl' => $this->stringValue($userLinks['html'] ?? null),
            'thumbnailUrl' => $this->stringValue($urls['small'] ?? null),
            'width' => $entry['width'] ?? null,
            'height' => $entry['height'] ?? null,
            'tags' => $this->unsplashTags($entry['tags'] ?? null),
            'maturity' => null,
        ];
    }

    /**
     * Unsplash tags are `{ "title": "…" }` objects (occasionally plain strings).
     *
     * @return array<int, string>
     */
    private function unsplashTags(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $tags = [];

        foreach ($value as $tag) {
            if (is_string($tag) && trim($tag) !== '') {
                $tags[] = trim($tag);

                continue;
            }

            if (is_array($tag) && ($title = $this->stringValue($tag['title'] ?? null)) !== null) {
                $tags[] = $title;
            }
        }

        return array_values(array_unique($tags));
    }
}
