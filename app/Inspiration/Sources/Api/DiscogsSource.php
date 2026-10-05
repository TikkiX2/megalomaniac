<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;
use Illuminate\Http\Client\PendingRequest;

/**
 * Discogs database search.
 *
 * The user credential travels as a `Discogs token=…` Authorization header
 * (Discogs' documented personal-access-token scheme), never as a query string.
 * Discogs has no anonymous explore feed, so explore() delegates to search with
 * a curated term. There is no maturity parameter.
 *
 * `hasMore` is derived from `pagination.items` (the total hit count) as the
 * brief specifies, falling back to `pagination.pages` when the item count is
 * absent.
 */
class DiscogsSource extends AbstractApiSource
{
    private const API_URL = 'https://api.discogs.com/database/search';

    private const PAGE_SIZE = 24;

    private const EXPLORE_TERM = 'portrait';

    public function key(): string
    {
        return 'discogs';
    }

    public function label(): string
    {
        return 'Discogs';
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
        return $this->configuredToken() !== null;
    }

    public function search(string $query, int $page, SourceQuery $queryOptions): Page
    {
        $query = trim($query);

        if ($query === '') {
            return Page::fromItems([], false, null);
        }

        $payload = $this->getJson(self::API_URL, [
            'q' => $query,
            'type' => 'release',
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
        $token = $this->configuredToken();

        return $token === null ? $request : $request->withHeaders([
            'Authorization' => 'Discogs token='.$token,
        ]);
    }

    /**
     * Discogs uses a `token` credential field rather than the generic `key`.
     */
    private function configuredToken(): ?string
    {
        return $this->stringValue($this->credentials['token'] ?? null);
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
        $pagination = is_array($payload['pagination'] ?? null) ? $payload['pagination'] : [];
        $hasMore = $this->hasMore($pagination, $current);

        return Page::fromItems($items, $hasMore, $hasMore ? $current + 1 : null);
    }

    /**
     * @param  array<string, mixed>  $pagination
     */
    private function hasMore(array $pagination, int $current): bool
    {
        $items = $pagination['items'] ?? null;

        if (is_numeric($items)) {
            return (int) $items > $current * self::PAGE_SIZE;
        }

        $pages = $pagination['pages'] ?? null;

        return is_numeric($pages) && (int) $pages > $current;
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
        $imageUrl = $this->stringValue($entry['cover_image'] ?? null);

        if (! is_scalar($id) || trim((string) $id) === '' || $imageUrl === null) {
            return null;
        }

        $pageUrl = 'https://www.discogs.com/release/'.$id;

        return [
            'sourceId' => $this->identifier($id, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $this->stringValue($entry['title'] ?? null),
            'author' => null,
            'authorUrl' => null,
            'thumbnailUrl' => $this->stringValue($entry['thumb'] ?? null),
            'width' => null,
            'height' => null,
            'tags' => $this->tagList(array_merge(
                (array) ($entry['genre'] ?? []),
                (array) ($entry['style'] ?? []),
            )),
            'maturity' => null,
        ];
    }
}
