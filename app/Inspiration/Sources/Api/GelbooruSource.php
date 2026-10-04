<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * Gelbooru public JSON API.
 *
 * Gelbooru exposes no anonymous explore feed, so explore() falls back to the
 * search endpoint with a curated term. Maturity is expressed through the tag
 * string: `safe` appends `-rating:explicit`, while `allowed` leaves the query
 * untouched. The manager toggles the maturity value in Task 13; this adapter
 * already honours the passed SourceQuery so only the settings wiring is left.
 */
class GelbooruSource extends AbstractApiSource
{
    private const SEARCH_URL = 'https://gelbooru.com/index.php';

    private const PAGE_SIZE = 24;

    private const EXPLORE_TERM = 'portrait';

    private const EXPLICIT_FILTER = '-rating:explicit';

    public function key(): string
    {
        return 'gelbooru';
    }

    public function label(): string
    {
        return 'Gelbooru';
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
            'page' => 'dapi',
            's' => 'post',
            'q' => 'index',
            'json' => 1,
            'tags' => $this->tags($query, $queryOptions),
            'pid' => max(0, $page - 1),
            'limit' => self::PAGE_SIZE,
        ]);

        return $this->mapToPage($payload, $page);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        return $this->search(self::EXPLORE_TERM, $page, $queryOptions);
    }

    private function tags(string $query, SourceQuery $queryOptions): string
    {
        if ($queryOptions->maturity === 'allowed') {
            return $query;
        }

        return $query.' '.self::EXPLICIT_FILTER;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapToPage(array $payload, int $page): Page
    {
        $raw = is_array($payload['post'] ?? null) ? $payload['post'] : [];

        // A single-result response may serialize `post` as one assoc object.
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

        $hasMore = count($raw) === self::PAGE_SIZE;

        return Page::fromItems($items, $hasMore, $hasMore ? $page + 1 : null);
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
        $imageUrl = $this->stringValue($entry['file_url'] ?? null);

        if (! is_scalar($id) || trim((string) $id) === '' || $imageUrl === null) {
            return null;
        }

        $pageUrl = 'https://gelbooru.com/index.php?page=post&s=view&id='.$id;
        $owner = $this->stringValue($entry['owner'] ?? null);

        return [
            'sourceId' => $this->identifier($id, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => null,
            'author' => $owner,
            'authorUrl' => $owner !== null
                ? 'https://gelbooru.com/index.php?page=account&s=profile&uname='.rawurlencode($owner)
                : null,
            'thumbnailUrl' => $this->stringValue($entry['preview_url'] ?? null),
            'width' => $entry['width'] ?? null,
            'height' => $entry['height'] ?? null,
            'tags' => $this->tagList($this->splitTags($entry['tags'] ?? null)),
            'maturity' => $this->stringValue($entry['rating'] ?? null),
        ];
    }

    /**
     * Gelbooru reports tags as a single space-separated string.
     *
     * @return array<int, string>
     */
    private function splitTags(mixed $value): array
    {
        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', explode(' ', $value))));
        }

        return is_array($value) ? $value : [];
    }
}
