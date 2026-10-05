<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * Flickr REST API.
 *
 * Search hits `flickr.photos.search`; explore uses the site-wide
 * `flickr.interestingness.getList` feed, both served from the same endpoint.
 * Flickr replies with XML unless `format=json&nojsoncallback=1` is requested.
 *
 * Maturity maps onto `safe_search`: `safe` sends 1 (safe only) while `allowed`
 * sends 3 (unrestricted). Task 13 flips the settings toggle; this adapter
 * already honours the passed SourceQuery.
 */
class FlickrSource extends AbstractApiSource
{
    private const API_URL = 'https://api.flickr.com/services/rest/';

    private const SEARCH_METHOD = 'flickr.photos.search';

    private const EXPLORE_METHOD = 'flickr.interestingness.getList';

    private const EXTRAS = 'url_m,url_l,owner_name,tags';

    private const PAGE_SIZE = 24;

    public function key(): string
    {
        return 'flickr';
    }

    public function label(): string
    {
        return 'Flickr';
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

        $payload = $this->getJson(self::API_URL, array_merge($this->baseQuery($page, $queryOptions), [
            'method' => self::SEARCH_METHOD,
            'text' => $query,
        ]));

        return $this->mapToPage($payload, $page);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        $payload = $this->getJson(self::API_URL, array_merge($this->baseQuery($page, $queryOptions), [
            'method' => self::EXPLORE_METHOD,
        ]));

        return $this->mapToPage($payload, $page);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseQuery(int $page, SourceQuery $queryOptions): array
    {
        return [
            'api_key' => $this->configuredKey() ?? '',
            'page' => max(1, $page),
            'per_page' => self::PAGE_SIZE,
            'format' => 'json',
            'nojsoncallback' => 1,
            'safe_search' => $this->safeSearch($queryOptions),
            'extras' => self::EXTRAS,
        ];
    }

    private function safeSearch(SourceQuery $queryOptions): int
    {
        return $queryOptions->maturity === 'allowed' ? 3 : 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapToPage(array $payload, int $page): Page
    {
        $photos = is_array($payload['photos'] ?? null) ? $payload['photos'] : [];
        $raw = is_array($photos['photo'] ?? null) ? $photos['photo'] : [];

        // A single-result response may serialize `photo` as one assoc object.
        if (! array_is_list($raw) && isset($raw['id'])) {
            $raw = [$raw];
        }

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

        $current = is_numeric($photos['page'] ?? null) ? (int) $photos['page'] : $page;
        $pages = is_numeric($photos['pages'] ?? null) ? (int) $photos['pages'] : 0;
        $hasMore = $pages > $current;

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
        $owner = $this->stringValue($entry['owner'] ?? null);
        $thumbnail = $this->stringValue($entry['url_m'] ?? null);
        $imageUrl = $this->stringValue($entry['url_l'] ?? null) ?? $thumbnail;

        if (! is_scalar($id) || trim((string) $id) === '' || $owner === null || $imageUrl === null) {
            return null;
        }

        $pageUrl = 'https://www.flickr.com/photos/'.$owner.'/'.$id;

        return [
            'sourceId' => $this->identifier($id, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $this->stringValue($entry['title'] ?? null),
            'author' => $this->stringValue($entry['owner_name'] ?? null),
            'authorUrl' => 'https://www.flickr.com/photos/'.$owner,
            'thumbnailUrl' => $thumbnail,
            'width' => null,
            'height' => null,
            'tags' => $this->splitTags($entry['tags'] ?? null),
            'maturity' => null,
        ];
    }

    /**
     * Flickr reports tags as a single space-separated string.
     *
     * @return array<int, string>
     */
    private function splitTags(mixed $value): array
    {
        if (! is_string($value)) {
            return $this->tagList($value);
        }

        $tags = array_map('trim', preg_split('/\s+/', $value) ?: []);

        return $this->tagList(array_values(array_filter($tags, static fn (string $tag): bool => $tag !== '')));
    }
}
