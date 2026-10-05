<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * Rijksmuseum collection API.
 *
 * The user key travels as the `key` query parameter. `imgonly=true` restricts
 * the response to objects with a usable image. The museum has no anonymous
 * explore feed, so explore() delegates to search with a curated term, and no
 * maturity parameter.
 *
 * The v1 response exposes no reliable total count, so `hasMore` is inferred
 * from a full page of 24 raw `artObjects`.
 */
class RijksmuseumSource extends AbstractApiSource
{
    private const API_URL = 'https://www.rijksmuseum.nl/api/en/collection';

    private const PAGE_SIZE = 24;

    private const EXPLORE_TERM = 'portrait';

    public function key(): string
    {
        return 'rijksmuseum';
    }

    public function label(): string
    {
        return 'Rijksmuseum';
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
            'key' => $this->configuredKey() ?? '',
            'q' => $query,
            'ps' => self::PAGE_SIZE,
            'p' => $current,
            'imgonly' => 'true',
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
        $raw = is_array($payload['artObjects'] ?? null) ? $payload['artObjects'] : [];
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

        $hasMore = count($raw) >= self::PAGE_SIZE;

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

        $webImage = is_array($entry['webImage'] ?? null) ? $entry['webImage'] : [];
        $links = is_array($entry['links'] ?? null) ? $entry['links'] : [];

        $imageUrl = $this->stringValue($webImage['url'] ?? null);
        $pageUrl = $this->stringValue($links['web'] ?? null);
        $id = $entry['objectNumber'] ?? $entry['id'] ?? null;

        if ($imageUrl === null || $pageUrl === null || ! is_scalar($id) || trim((string) $id) === '') {
            return null;
        }

        return [
            'sourceId' => $this->identifier($id, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $this->stringValue($entry['title'] ?? null),
            'author' => $this->stringValue($entry['principalOrFirstMaker'] ?? null),
            'authorUrl' => null,
            'thumbnailUrl' => $imageUrl,
            'width' => $webImage['width'] ?? null,
            'height' => $webImage['height'] ?? null,
            'tags' => [],
            'maturity' => null,
        ];
    }
}
