<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\Exceptions\SourceException;

/**
 * The Metropolitan Museum of Art collection API.
 *
 * Met is a two-call source: the search endpoint returns a flat `objectIDs`
 * list, and every object still needs an individual `GET /objects/{id}`. That
 * list is the adapter's *pending id queue*: each search page consumes a batch
 * of at most 20 ids, ids beyond the batch stay pending and surface as
 * `hasMore=true`, and non-integer ids are dropped before any request. The
 * queue is derived from a stateless offset (`page`), so retrying a page is
 * idempotent and single missing/errored objects are skipped best-effort
 * instead of failing the whole page.
 *
 * Objects without `primaryImageSmall` cannot be rendered and are discarded.
 * There is no anonymous explore feed, so explore() searches a curated term.
 */
class MetMuseumSource extends AbstractApiSource
{
    private const SEARCH_URL = 'https://collectionapi.metmuseum.org/public/collection/v1/search';

    private const OBJECT_URL = 'https://collectionapi.metmuseum.org/public/collection/v1/objects/';

    private const FALLBACK_URL = 'https://www.metmuseum.org/art/collection/search/';

    /**
     * Object-detail requests allowed per search page.
     */
    private const PAGE_SIZE = 20;

    private const EXPLORE_TERM = 'portrait';

    public function key(): string
    {
        return 'met';
    }

    public function label(): string
    {
        return 'The Met';
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
            'hasImages' => 'true',
        ]);

        $ids = array_values(array_filter(
            is_array($payload['objectIDs'] ?? null) ? $payload['objectIDs'] : [],
            static fn (mixed $id): bool => is_int($id),
        ));

        $offset = max(0, $page - 1) * self::PAGE_SIZE;
        $batch = array_slice($ids, $offset, self::PAGE_SIZE);
        $hasMore = count($ids) > $offset + count($batch);

        $items = [];

        foreach ($batch as $id) {
            $object = $this->object((int) $id);

            if ($object === null) {
                continue;
            }

            $normalized = $this->normalizeRaw($object, (int) $id);

            if ($normalized === null) {
                continue;
            }

            $item = InspirationItem::fromSource($this->key(), $normalized);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return Page::fromItems($items, $hasMore, $hasMore ? $page + 1 : null);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        return $this->search(self::EXPLORE_TERM, $page, $queryOptions);
    }

    /**
     * Resolve a single object, tolerating per-object transport failures.
     *
     * @return array<string, mixed>|null
     */
    private function object(int $id): ?array
    {
        try {
            return $this->getJson(self::OBJECT_URL.$id);
        } catch (SourceException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>|null
     */
    private function normalizeRaw(array $object, int $requestedId): ?array
    {
        $imageUrl = $this->stringValue($object['primaryImageSmall'] ?? null);

        if ($imageUrl === null) {
            return null;
        }

        $pageUrl = $this->stringValue($object['objectURL'] ?? null)
            ?? self::FALLBACK_URL.$requestedId;

        return [
            'sourceId' => $this->identifier($object['objectID'] ?? $requestedId, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $this->stringValue($object['title'] ?? null),
            'author' => $this->stringValue($object['artistDisplayName'] ?? null),
            'authorUrl' => $this->stringValue($object['artistULAN_URL'] ?? null),
            'thumbnailUrl' => $this->stringValue($object['primaryImageSmall'] ?? null),
            'width' => null,
            'height' => null,
            'tags' => $this->metTags($object['tags'] ?? null),
            'maturity' => null,
        ];
    }

    /**
     * Met tags arrive as `[{ "term": "Portraits" }]`.
     *
     * @return array<int, string>
     */
    private function metTags(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $tags = [];

        foreach ($value as $tag) {
            if (is_array($tag) && is_string($tag['term'] ?? null) && trim($tag['term']) !== '') {
                $tags[] = trim($tag['term']);

                continue;
            }

            if (is_string($tag) && trim($tag) !== '') {
                $tags[] = trim($tag);
            }
        }

        return array_values(array_unique($tags));
    }
}
