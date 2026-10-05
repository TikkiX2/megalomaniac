<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * Europeana Search API (record v2).
 *
 * The user key travels as the `wskey` query parameter. Europeana has no
 * anonymous explore feed, so explore() delegates to search with a curated
 * term. There is no maturity parameter, but items flagged
 * `previewNoDistribute` must not be shown, so they are filtered out before
 * mapping.
 *
 * `edmPreview` is a thumbnail and may arrive as a string or a list;
 * `edmIsShownBy` (when present) is the full-size image.
 */
class EuropeanaSource extends AbstractApiSource
{
    private const API_URL = 'https://api.europeana.eu/record/v2/search.json';

    private const PAGE_SIZE = 24;

    private const EXPLORE_TERM = 'portrait';

    public function key(): string
    {
        return 'europeana';
    }

    public function label(): string
    {
        return 'Europeana';
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

        $current = max(1, $page);

        $payload = $this->getJson(self::API_URL, [
            'query' => $query,
            'rows' => self::PAGE_SIZE,
            'start' => ($current - 1) * self::PAGE_SIZE + 1,
            'wskey' => $this->configuredKey() ?? '',
        ]);

        return $this->mapToPage($payload, $current);
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
        $raw = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $items = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            // Rights restriction: never surface these items.
            if ($this->boolValue($entry['previewNoDistribute'] ?? false)) {
                continue;
            }

            $normalized = $this->normalizeRaw($entry);

            if ($normalized === null) {
                continue;
            }

            $item = InspirationItem::fromSource($this->key(), $normalized);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        $total = is_numeric($payload['totalResults'] ?? null) ? (int) $payload['totalResults'] : 0;
        $hasMore = $total > ($page - 1) * self::PAGE_SIZE + count($raw);

        return Page::fromItems($items, $hasMore, $hasMore ? $page + 1 : null);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private function normalizeRaw(array $entry): ?array
    {
        $pageUrl = $this->stringValue($entry['guid'] ?? null);
        $imageUrl = $this->firstString($entry['edmIsShownBy'] ?? null)
            ?? $this->firstString($entry['edmPreview'] ?? null);

        if ($pageUrl === null || $imageUrl === null) {
            return null;
        }

        return [
            'sourceId' => $this->identifier($entry['id'] ?? null, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $this->firstString($entry['title'] ?? null),
            'author' => $this->firstString($entry['dcCreator'] ?? null)
                ?? $this->firstString($entry['dataProvider'] ?? null),
            'authorUrl' => null,
            'thumbnailUrl' => $this->firstString($entry['edmPreview'] ?? null),
            'width' => null,
            'height' => null,
            'tags' => [],
            'maturity' => null,
        ];
    }

    /**
     * Europeana frequently serializes fields as either a string or a list.
     */
    private function firstString(mixed $value): ?string
    {
        if (is_array($value)) {
            foreach ($value as $entry) {
                $string = $this->stringValue($entry);

                if ($string !== null) {
                    return $string;
                }
            }

            return null;
        }

        return $this->stringValue($value);
    }
}
