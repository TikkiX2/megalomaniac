<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * Tumblr tagged feed.
 *
 * Tumblr has no dedicated explore endpoint, so explore() falls back to the
 * tagged feed with the curated `inspiration` tag. The API returns posts under
 * `response[]`; only photo posts carrying an `original_size.url` are kept.
 *
 * `/v2/tagged` is cursor-based (`_links.next` / `before`), while the Page DTO
 * only carries a numeric next page. The adapter therefore never advertises
 * pagination (see mapToPage); real cursor support is a future candidate.
 *
 * No maturity parameter exists, hence `hasMaturityLevels: false`.
 */
class TumblrSource extends AbstractApiSource
{
    private const API_URL = 'https://api.tumblr.com/v2/tagged';

    /**
     * Tumblr caps the tagged feed at 20 results per request.
     */
    private const PAGE_SIZE = 20;

    private const EXPLORE_TAG = 'inspiration';

    public function key(): string
    {
        return 'tumblr';
    }

    public function label(): string
    {
        return 'Tumblr';
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

        $payload = $this->getJson(self::API_URL, [
            'tag' => $query,
            'api_key' => $this->configuredKey() ?? '',
            'limit' => self::PAGE_SIZE,
        ]);

        return $this->mapToPage($payload);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        return $this->search(self::EXPLORE_TAG, $page, $queryOptions);
    }

    /**
     * Tumblr's tagged feed is cursor-based (`_links.next` / `before`), but the
     * Page DTO only supports a numeric next page and the tagged endpoint has no
     * numeric page/offset parameter. Advertising `_links.next` as a page would
     * make the UI re-issue the identical request forever, so this adapter always
     * reports `hasMore=false, nextPage=null`. Real cursor paging is a pending
     * future candidate.
     *
     * @param  array<string, mixed>  $payload
     */
    private function mapToPage(array $payload): Page
    {
        $raw = is_array($payload['response'] ?? null) ? $payload['response'] : [];
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

        $photos = is_array($entry['photos'] ?? null) ? $entry['photos'] : [];
        $photo = $photos[0] ?? null;

        if (! is_array($photo)) {
            return null;
        }

        $original = is_array($photo['original_size'] ?? null) ? $photo['original_size'] : [];
        $imageUrl = $this->stringValue($original['url'] ?? null);
        $pageUrl = $this->stringValue($entry['post_url'] ?? null);
        $id = $entry['id_string'] ?? $entry['id'] ?? null;

        if ($imageUrl === null || $pageUrl === null || ! is_scalar($id) || trim((string) $id) === '') {
            return null;
        }

        $blog = $this->stringValue($entry['blog_name'] ?? null);

        return [
            'sourceId' => $this->identifier($id, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $this->stringValue($entry['summary'] ?? null)
                ?? $this->stringValue($entry['caption'] ?? null),
            'author' => $blog,
            'authorUrl' => $blog !== null ? 'https://'.$blog.'.tumblr.com' : null,
            'thumbnailUrl' => $this->thumbnail($photo),
            'width' => $original['width'] ?? null,
            'height' => $original['height'] ?? null,
            'tags' => $this->tagList($entry['tags'] ?? null),
            'maturity' => null,
        ];
    }

    /**
     * Pick the 400px rendition when present, otherwise the first alt size.
     *
     * @param  array<string, mixed>  $photo
     */
    private function thumbnail(array $photo): ?string
    {
        $sizes = is_array($photo['alt_sizes'] ?? null) ? $photo['alt_sizes'] : [];
        $chosen = null;

        foreach ($sizes as $size) {
            if (! is_array($size)) {
                continue;
            }

            if ($chosen === null && $this->stringValue($size['url'] ?? null) !== null) {
                $chosen = $size;
            }

            if ((int) ($size['width'] ?? 0) === 400) {
                $chosen = $size;

                break;
            }
        }

        return is_array($chosen) ? $this->stringValue($chosen['url'] ?? null) : null;
    }
}
