<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * Art Institute of Chicago public API.
 *
 * Search results carry an `image_id`; both the thumbnail and the full-size URL
 * are derived from the same IIIF endpoint (`/full/843,/` and `/full/2000,/`).
 * Items without an `image_id` are discarded. AIC exposes no anonymous explore
 * feed, so explore() falls back to the search endpoint with a curated term.
 */
class ArtInstituteChicagoSource extends AbstractApiSource
{
    private const SEARCH_URL = 'https://api.artic.edu/api/v1/artworks/search';

    private const ARTWORK_URL = 'https://www.artic.edu/artworks/';

    private const IIIF_URL = 'https://www.artic.edu/iiif/2/';

    private const FIELDS = 'id,title,image_id,artist_title,date_display';

    private const PAGE_SIZE = 24;

    private const THUMB_WIDTH = 843;

    private const FULL_WIDTH = 2000;

    private const EXPLORE_TERM = 'portrait';

    public function key(): string
    {
        return 'aic';
    }

    public function label(): string
    {
        return 'Art Institute of Chicago';
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
            'fields' => self::FIELDS,
            'page' => max(1, $page),
            'limit' => self::PAGE_SIZE,
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

        $pagination = is_array($payload['pagination'] ?? null) ? $payload['pagination'] : [];
        $current = is_numeric($pagination['current_page'] ?? null) ? (int) $pagination['current_page'] : $page;
        $total = is_numeric($pagination['total_pages'] ?? null) ? (int) $pagination['total_pages'] : null;
        $hasMore = $total !== null && $current < $total;

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
        $imageId = $this->stringValue($entry['image_id'] ?? null);

        if (! is_scalar($id) || trim((string) $id) === '' || $imageId === null) {
            return null;
        }

        $pageUrl = self::ARTWORK_URL.$id;

        return [
            'sourceId' => $this->identifier($id, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $this->iiif($imageId, self::FULL_WIDTH),
            'title' => $this->stringValue($entry['title'] ?? null),
            'author' => $this->stringValue($entry['artist_title'] ?? null),
            'authorUrl' => null,
            'thumbnailUrl' => $this->iiif($imageId, self::THUMB_WIDTH),
            'width' => null,
            'height' => null,
            'tags' => [],
            'maturity' => null,
        ];
    }

    private function iiif(string $imageId, int $width): string
    {
        return self::IIIF_URL.$imageId.'/full/'.$width.',/0/default.jpg';
    }
}
