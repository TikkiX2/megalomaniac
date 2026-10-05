<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;
use Illuminate\Support\Str;

/**
 * WikiArt public API v2.
 *
 * The brief pointed at the legacy
 * `https://www.wikiart.org/en/App/Painting/Newest?json=2` feed, but that route
 * now answers HTTP 400 (verified live 2026-10-05). The v2 endpoints are live
 * and server JSON without an app credential:
 *
 * - search:  `/en/api/2/PaintingSearch?term={q}` → `{data: [...], hasMore}`
 * - explore: `/en/api/2/MostViewedPaintings`      → same `data` shape
 *
 * WikiArt still declares a per-request `key` (the documented 400 req/h tier), so
 * this adapter keeps `needsKey: true` and `isConfigured()` true only when the
 * user stored `keys.wikiart.key`; the key is sent as `key` and the API ignores
 * it for now, leaving the source opt-in like the rest of tier 3.
 *
 * Pagination: the v2 `page` parameter is ignored (paging is token based via an
 * opaque `paginationToken`), so the adapter serves a single capped page and
 * always reports `hasMore: false`.
 *
 * Page URLs: `/en/{artistUrl}/{url}` when the painting carries its own `url`
 * slug. PaintingSearch frequently sends `url: null`, so the slug is derived
 * from the title with `Str::slug()` (the same shape WikiArt itself uses, e.g.
 * "Portrait of Eve" → /en/giuseppe-arcimboldo/portrait-of-eve). Entries without
 * an `artistUrl` or a usable slug are discarded.
 */
class WikiArtSource extends AbstractApiSource
{
    private const SEARCH_URL = 'https://www.wikiart.org/en/api/2/PaintingSearch';

    private const EXPLORE_URL = 'https://www.wikiart.org/en/api/2/MostViewedPaintings';

    private const PUBLIC_BASE_URL = 'https://www.wikiart.org/en';

    private const PAGE_SIZE = 24;

    public function key(): string
    {
        return 'wikiart';
    }

    public function label(): string
    {
        return 'WikiArt';
    }

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: true,
            supportsExplore: true,
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

        return $this->mapToPage($this->getJson(self::SEARCH_URL, [
            'term' => $query,
            'key' => $this->configuredKey() ?? '',
        ]));
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        return $this->mapToPage($this->getJson(self::EXPLORE_URL, [
            'key' => $this->configuredKey() ?? '',
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapToPage(array $payload): Page
    {
        $raw = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $items = [];

        foreach (array_slice($raw, 0, self::PAGE_SIZE) as $entry) {
            $normalized = $this->normalizeRaw($entry);

            if ($normalized === null) {
                continue;
            }

            $item = InspirationItem::fromSource($this->key(), $normalized);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return Page::fromItems($items, false, null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeRaw(mixed $entry): ?array
    {
        if (! is_array($entry)) {
            return null;
        }

        $imageUrl = $this->stringValue($entry['image'] ?? null);
        $artistSlug = $this->stringValue($entry['artistUrl'] ?? null);
        $title = $this->stringValue($entry['title'] ?? null);

        if ($imageUrl === null || $artistSlug === null) {
            return null;
        }

        $slug = $this->stringValue($entry['url'] ?? null)
            ?? ($title === null ? null : Str::slug($title));

        if ($slug === null || $slug === '') {
            return null;
        }

        $pageUrl = self::PUBLIC_BASE_URL.'/'.$artistSlug.'/'.$slug;

        return [
            'sourceId' => $this->identifier($entry['id'] ?? null, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $title,
            'author' => $this->stringValue($entry['artistName'] ?? null),
            'authorUrl' => self::PUBLIC_BASE_URL.'/'.$artistSlug,
            'thumbnailUrl' => $imageUrl,
            'width' => is_numeric($entry['width'] ?? null) ? (int) $entry['width'] : null,
            'height' => is_numeric($entry['height'] ?? null) ? (int) $entry['height'] : null,
            'tags' => [],
            'maturity' => null,
        ];
    }
}
