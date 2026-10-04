<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\Exceptions\SourceException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * DeviantArt official API.
 *
 * Uses app-level OAuth2 client-credentials: the token is fetched once and
 * cached for 24h under its own key. Maturity is fixed to safe for now
 * (`mature_content=false`); the global mapping lands in Task 13.
 */
class DeviantArtSource extends AbstractApiSource
{
    private const SEARCH_URL = 'https://www.deviantart.com/api/v1/oauth2/search/art';

    private const BROWSE_URL = 'https://www.deviantart.com/api/v1/oauth2/browse/popular';

    private const TOKEN_URL = 'https://www.deviantart.com/oauth2/token';

    private const TOKEN_CACHE_KEY = 'inspiration:deviantart:token';

    private const TOKEN_TTL = 86400;

    private const PAGE_SIZE = 24;

    public function key(): string
    {
        return 'deviantart';
    }

    public function label(): string
    {
        return 'DeviantArt';
    }

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: true,
            supportsExplore: true,
            needsKey: false,
            hasMaturityLevels: true,
            maxPageSize: self::PAGE_SIZE,
        );
    }

    public function isConfigured(): bool
    {
        return filled(config('inspiration.deviantart.client_id'))
            && filled(config('inspiration.deviantart.client_secret'));
    }

    public function search(string $query, int $page, SourceQuery $queryOptions): Page
    {
        $query = trim($query);

        if ($query === '') {
            return Page::fromItems([], false, null);
        }

        $payload = $this->getJson(self::SEARCH_URL, [
            'q' => $query,
            'limit' => self::PAGE_SIZE,
            'offset' => $this->offset($page),
            'mature_content' => false,
        ]);

        return $this->mapToPage($payload, $page);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        $payload = $this->getJson(self::BROWSE_URL, [
            'limit' => self::PAGE_SIZE,
            'offset' => $this->offset($page),
            'mature_content' => false,
        ]);

        return $this->mapToPage($payload, $page);
    }

    protected function authorize(PendingRequest $request): PendingRequest
    {
        return $request->withToken($this->token());
    }

    private function offset(int $page): int
    {
        return max(0, ($page - 1) * self::PAGE_SIZE);
    }

    /**
     * @throws SourceException
     */
    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if (! $this->isConfigured()) {
            throw new SourceException('deviantart: missing client credentials');
        }

        try {
            $response = Http::timeout($this->timeout())
                ->asForm()
                ->acceptJson()
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'client_credentials',
                    'client_id' => (string) config('inspiration.deviantart.client_id'),
                    'client_secret' => (string) config('inspiration.deviantart.client_secret'),
                ]);
        } catch (Throwable $exception) {
            throw new SourceException('deviantart: token request failed ('.$exception->getMessage().')', previous: $exception);
        }

        if ($response->failed()) {
            throw new SourceException('deviantart: token HTTP '.$response->status());
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new SourceException('deviantart: token missing from response');
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, self::TOKEN_TTL);

        return $token;
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

        $hasMore = $this->boolValue($payload['has_more'] ?? false);

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

        $pageUrl = $this->stringValue($entry['url'] ?? null);
        $content = is_array($entry['content'] ?? null) ? $entry['content'] : [];
        $preview = is_array($entry['preview'] ?? null) ? $entry['preview'] : [];
        $thumbs = is_array($entry['thumbs'] ?? null) ? $entry['thumbs'] : [];
        $thumb = is_array($thumbs[0] ?? null) ? $thumbs[0] : [];
        $author = is_array($entry['author'] ?? null) ? $entry['author'] : [];

        $imageUrl = $this->stringValue($content['src'] ?? null)
            ?? $this->stringValue($preview['src'] ?? null)
            ?? $this->stringValue($thumb['src'] ?? null);

        if ($pageUrl === null || $imageUrl === null) {
            return null;
        }

        $username = $this->stringValue($author['username'] ?? null);

        return [
            'sourceId' => $this->identifier($entry['deviationid'] ?? null, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $this->stringValue($entry['title'] ?? null),
            'author' => $username,
            'authorUrl' => $username !== null ? 'https://www.deviantart.com/'.$username : null,
            'thumbnailUrl' => $this->stringValue($preview['src'] ?? null) ?? $this->stringValue($thumb['src'] ?? null),
            'width' => $content['width'] ?? null,
            'height' => $content['height'] ?? null,
            'tags' => $this->tagList($entry['tags'] ?? null),
            'maturity' => $this->boolValue($entry['is_mature'] ?? false) ? 'mature' : 'safe',
        ];
    }
}
