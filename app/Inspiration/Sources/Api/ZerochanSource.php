<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;
use Illuminate\Http\Client\PendingRequest;

/**
 * Zerochan public JSON endpoint.
 *
 * Search hits `https://www.zerochan.net/{query}?json=1`; explore uses the root
 * feed (`https://www.zerochan.net/?json=1`) because Zerochan exposes no
 * dedicated anonymous explore API but the home listing paginates the same
 * `items[]` shape as a tag page.
 *
 * Zerochan rejects generic clients, so the adapter sends a User-Agent taken
 * from the credential bag (`user_agent`, wired from the per-user Zerochan UA in
 * settings) and falls back to a neutral identifier. The endpoint has no
 * maturity parameter, so item maturity stays null.
 */
class ZerochanSource extends AbstractApiSource
{
    private const BASE_URL = 'https://www.zerochan.net/';

    private const PAGE_SIZE = 24;

    private const DEFAULT_USER_AGENT = 'MegalomaniacInspiration/1.0';

    public function key(): string
    {
        return 'zerochan';
    }

    public function label(): string
    {
        return 'Zerochan';
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

        return $this->fetchPage(self::BASE_URL.rawurlencode($query), $page);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        return $this->fetchPage(self::BASE_URL, $page);
    }

    protected function authorize(PendingRequest $request): PendingRequest
    {
        $userAgent = $this->stringValue($this->credentials['user_agent'] ?? null)
            ?? self::DEFAULT_USER_AGENT;

        return $request->withUserAgent($userAgent);
    }

    private function fetchPage(string $url, int $page): Page
    {
        $payload = $this->getJson($url, [
            'json' => 1,
            'l' => self::PAGE_SIZE,
            'p' => max(1, $page),
        ]);

        return $this->mapToPage($payload, $page);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapToPage(array $payload, int $page): Page
    {
        $raw = is_array($payload['items'] ?? null) ? $payload['items'] : [];
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

        $hasMore = count($raw) === self::PAGE_SIZE;

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

        $id = $entry['id'] ?? null;
        $imageUrl = $this->stringValue($entry['full'] ?? null);

        if (! is_scalar($id) || trim((string) $id) === '' || $imageUrl === null) {
            return null;
        }

        $pageUrl = self::BASE_URL.$id;

        return [
            'sourceId' => $this->identifier($id, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => null,
            'author' => $this->stringValue($entry['author'] ?? null),
            'authorUrl' => null,
            'thumbnailUrl' => $this->stringValue($entry['thumb'] ?? null),
            'width' => $entry['width'] ?? null,
            'height' => $entry['height'] ?? null,
            'tags' => $this->zerochanTags($entry['tags'] ?? null),
            'maturity' => null,
        ];
    }

    /**
     * Zerochan returns tags either as a comma-separated string or as a list of
     * `{ "name": "tag" }`; normalize both.
     *
     * @return array<int, string>
     */
    private function zerochanTags(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        return $this->tagList($value);
    }
}
