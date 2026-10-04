<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * ArtStation public v2 feeds — best effort, degraded mode.
 *
 * STATUS 2026-10-04: ArtStation's anonymous JSON feed is effectively retired.
 * Live probes returned:
 *   - `GET https://www.artstation.com/api/v2/feeds/projects.json` → 404
 *   - `GET https://artstation.com/api/v2/feeds/projects.json` (no www) → 403 bot wall
 *   - `POST .../api/v2/search/projects/projects.json` (CSRF flow) → 412 Invalid CSRF Token
 *   - `GET .../api/v2/search/projects/projects.json?query=…` → 500
 *   - `https://www.artstation.com/explore?sorting=latest` → SPA shell, no `hash_id`
 *
 * A session/CSRF token is required for anonymous access, so this source is
 * considered dormant: every transport failure is normalized into a
 * SourceException and the SourceManager serving the UI degrades to the cached
 * payload (chip "caché · hace Xh"). Reconnect this adapter when ArtStation
 * reopens a public JSON endpoint. The brief's canonical endpoints
 * (`feeds/projects.json` with and without `query`) are kept for that day.
 *
 * The feed envelope is not guaranteed: `mapToPage()` accepts either a
 * top-level list or a `{ "data": [...] }` object. ArtStation exposes no
 * maturity parameter, so `maturity` stays null.
 */
class ArtStationSource extends AbstractApiSource
{
    private const FEED_URL = 'https://www.artstation.com/api/v2/feeds/projects.json';

    private const PAGE_SIZE = 24;

    public function key(): string
    {
        return 'artstation';
    }

    public function label(): string
    {
        return 'ArtStation';
    }

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: true,
            supportsExplore: true,
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

        $payload = $this->getJson(self::FEED_URL, [
            'page' => $page,
            'sorting' => 'latest',
            'query' => $query,
        ]);

        return $this->mapToPage($payload, $page);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        $payload = $this->getJson(self::FEED_URL, [
            'page' => $page,
            'sorting' => 'latest',
        ]);

        return $this->mapToPage($payload, $page);
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>  $payload
     */
    private function mapToPage(array $payload, int $page): Page
    {
        if (array_is_list($payload)) {
            $raw = $payload;
        } else {
            $raw = is_array($payload['data'] ?? null) ? $payload['data'] : [];
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

        $total = is_numeric($payload['total_count'] ?? null) ? (int) $payload['total_count'] : null;

        if ($total !== null) {
            $perPage = $items === [] ? self::PAGE_SIZE : count($items);
            $hasMore = ($page * $perPage) < $total;
        } else {
            $hasMore = count($items) === self::PAGE_SIZE;
        }

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

        $hash = $this->stringValue($entry['hash_id'] ?? null);
        $pageUrl = $this->stringValue($entry['permalink'] ?? null);

        if ($pageUrl === null && $hash !== null) {
            $pageUrl = 'https://www.artstation.com/artwork/'.$hash;
        }

        $cover = is_array($entry['cover'] ?? null) ? $entry['cover'] : [];
        $assets = is_array($entry['assets'] ?? null) ? $entry['assets'] : [];
        $asset = is_array($assets[0] ?? null) ? $assets[0] : [];
        $user = is_array($entry['user'] ?? null) ? $entry['user'] : [];

        $imageUrl = $this->stringValue($cover['image_url'] ?? null)
            ?? $this->stringValue($cover['medium_image_url'] ?? null)
            ?? $this->stringValue($cover['small_image_url'] ?? null)
            ?? $this->stringValue($asset['image_url'] ?? null);

        if ($pageUrl === null || $imageUrl === null) {
            return null;
        }

        $username = $this->stringValue($user['username'] ?? null);

        return [
            'sourceId' => $this->identifier($entry['id'] ?? null, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $this->stringValue($entry['title'] ?? null),
            'author' => $this->stringValue($user['full_name'] ?? null) ?? $username,
            'authorUrl' => $this->stringValue($user['permalink'] ?? null),
            'thumbnailUrl' => $this->stringValue($cover['small_image_url'] ?? null)
                ?? $this->stringValue($cover['medium_image_url'] ?? null),
            'width' => $cover['width'] ?? $asset['width'] ?? null,
            'height' => $cover['height'] ?? $asset['height'] ?? null,
            'tags' => $this->tagList($entry['tags'] ?? null),
        ];
    }
}
