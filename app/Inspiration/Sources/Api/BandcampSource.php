<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;
use Illuminate\Support\Facades\Log;

/**
 * Bandcamp discover feed (tier 3, opt-in).
 *
 * Bandcamp's documented API is gated behind label accounts, so this adapter
 * uses the internal web endpoint `POST /api/discover/1/get_discover_items`,
 * reverse-engineered by the community (michaelherger/Bandcamp-API and the
 * `bandcamp` Python clients). The request carries a minimal JSON body and the
 * response is expected to expose `discover_items[]`, where each entry has
 * `art_id` (cover art), `tralbum_url` (canonical page), `band_name` (artist)
 * and either `album_name` or `item_title` (release title).
 *
 * The shape is undocumented and may change without notice, so entries are
 * validated individually and a payload without a usable `discover_items` list
 * degrades to an empty page instead of throwing. The endpoint exposes no term
 * search, so search() delegates to the discover feed (with a warning) and
 * capabilities advertise supportsSearch=false; explore is the native feed.
 */
class BandcampSource extends AbstractApiSource
{
    private const DISCOVER_URL = 'https://bandcamp.com/api/discover/1/get_discover_items';

    private const COVER_URL = 'https://f4.bcbits.com/img/a%s_10.jpg';

    private const PAGE_SIZE = 24;

    public function key(): string
    {
        return 'bandcamp';
    }

    public function label(): string
    {
        return 'Bandcamp';
    }

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: false,
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

        Log::warning('inspiration: source has no native search, delegating to explore', [
            'source' => $this->key(),
            'query' => $query,
        ]);

        return $this->explore($page, $queryOptions);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        $payload = $this->postJson(self::DISCOVER_URL, [
            'category' => 'music',
            'genre' => 'all',
            'region' => 'all',
            'query' => '',
            'count' => self::PAGE_SIZE,
        ]);

        return $this->mapToPage($payload);
    }

    protected function timeout(): int
    {
        return (int) config('inspiration.timeouts.tier3', 10);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapToPage(array $payload): Page
    {
        $raw = is_array($payload['discover_items'] ?? null) ? $payload['discover_items'] : [];
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

        // Discover returns a single fixed page; this shape exposes no cursor.
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

        $artId = $entry['art_id'] ?? null;
        $pageUrl = $this->absoluteUrl($this->stringValue($entry['tralbum_url'] ?? null));

        if (! is_scalar($artId) || trim((string) $artId) === '' || $pageUrl === null) {
            return null;
        }

        $cover = sprintf(self::COVER_URL, $artId);
        $author = $this->stringValue($entry['band_name'] ?? null);

        return [
            'sourceId' => $this->identifier($entry['tralbum_id'] ?? null, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $cover,
            'title' => $this->stringValue($entry['album_name'] ?? null)
                ?? $this->stringValue($entry['item_title'] ?? null),
            'author' => $author,
            'authorUrl' => $this->stringValue($entry['band_url'] ?? null),
            'thumbnailUrl' => $cover,
            'tags' => $this->tagList($entry['tags'] ?? null),
            'maturity' => null,
        ];
    }

    /**
     * The discover shape serves canonical URLs; tolerate a relative path in
     * case a future revision drops the scheme.
     */
    private function absoluteUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return 'https://bandcamp.com/'.ltrim($url, '/');
    }
}
