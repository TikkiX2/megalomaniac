<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * Openverse official API.
 *
 * Openverse has no dedicated explore feed, so explore() falls back to the
 * search endpoint with a curated term. Search sends no maturity parameter.
 */
class OpenverseSource extends AbstractApiSource
{
    private const SEARCH_URL = 'https://api.openverse.org/v1/images/';

    private const PAGE_SIZE = 20;

    private const EXPLORE_TERM = 'portrait';

    public function key(): string
    {
        return 'openverse';
    }

    public function label(): string
    {
        return 'Openverse';
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
            'page_size' => self::PAGE_SIZE,
            'page' => $page,
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

        $pageCount = is_numeric($payload['page_count'] ?? null) ? (int) $payload['page_count'] : null;
        $hasMore = $pageCount !== null && $page < $pageCount;

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

        $pageUrl = $this->stringValue($entry['foreign_landing_url'] ?? null);
        $imageUrl = $this->stringValue($entry['url'] ?? null);

        if ($pageUrl === null || $imageUrl === null) {
            return null;
        }

        $license = $this->stringValue($entry['license'] ?? null);
        $version = $this->stringValue($entry['license_version'] ?? null);

        if ($license !== null && $version !== null) {
            $license = $license.' '.$version;
        }

        return [
            'sourceId' => $this->identifier($entry['id'] ?? null, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $this->stringValue($entry['title'] ?? null),
            'author' => $this->stringValue($entry['creator'] ?? null),
            'authorUrl' => $this->stringValue($entry['creator_url'] ?? null),
            'thumbnailUrl' => $this->stringValue($entry['thumbnail'] ?? null),
            'width' => $entry['width'] ?? null,
            'height' => $entry['height'] ?? null,
            'tags' => $this->tagList($entry['tags'] ?? null),
            'license' => $license,
            'maturity' => filter_var($entry['maturity'] ?? false, FILTER_VALIDATE_BOOL) ? 'mature' : 'safe',
        ];
    }
}
