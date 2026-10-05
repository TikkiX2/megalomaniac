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
 * Pixiv App API (tier 3, opt-in).
 *
 * Authentication is a two-step flow: the per-user refresh token is exchanged
 * for a short-lived access token at the OAuth endpoint, and only the access
 * token travels in the `Authorization: Bearer` header of every search. The
 * exchange uses Pixiv's well-known public App API client credentials and the
 * resulting access token is cached for one hour.
 *
 * Maturity is a token-level concern on Pixiv (the account controls whether R-18
 * works are visible), so the adapter only adds the `filter=for_ios` parameter
 * for the `safe` token and omits it for `allowed`. Note the filter is not a
 * maturity gate by itself; it is Pixiv's iOS-client content filter. The
 * documented R-18 boundary is enforced account side, which the global toggle
 * cannot override per request.
 *
 * Animation posts (`type=ugoira`) are zip archives rather than static images,
 * so they are skipped. Explore has no anonymous recommendation feed suitable
 * for a generic grid, so it reuses the tag search with a curated term.
 */
class PixivSource extends AbstractApiSource
{
    private const SEARCH_URL = 'https://app-api.pixiv.net/v1/search/illust';

    private const TOKEN_URL = 'https://oauth.secure.pixiv.net/auth/token';

    /**
     * Public App API client credentials shipped with the official Android app.
     */
    private const CLIENT_ID = 'MOBrBDS8blbauoSck0ZfDbtuzpyT';

    private const CLIENT_SECRET = 'lsACyCD94FhDUtGTZV3nOURqG2z5MDkOkbfm4GwG';

    private const TOKEN_CACHE_KEY = 'inspiration:pixiv:token';

    private const TOKEN_TTL = 3600;

    private const PAGE_SIZE = 24;

    private const EXPLORE_TERM = 'portrait';

    private const USER_AGENT = 'PixivAndroidApp/5.0.234 (Android 11; Pixel 5)';

    /**
     * The App API rejects generic clients, so every search carries the same
     * Android app fingerprint as the OAuth exchange.
     *
     * @var array<string, string>
     */
    private const APP_HEADERS = [
        'App-OS' => 'android',
        'App-OS-Version' => '11',
        'App-Version' => '5.0.234',
    ];

    public function key(): string
    {
        return 'pixiv';
    }

    public function label(): string
    {
        return 'Pixiv';
    }

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: true,
            supportsExplore: true,
            needsKey: true,
            hasMaturityLevels: true,
            maxPageSize: self::PAGE_SIZE,
        );
    }

    public function isConfigured(): bool
    {
        return $this->refreshToken() !== null;
    }

    public function search(string $query, int $page, SourceQuery $queryOptions): Page
    {
        $query = trim($query);

        if ($query === '') {
            return Page::fromItems([], false, null);
        }

        $params = [
            'word' => $query,
            'search_target' => 'partial_match_for_tags',
            'offset' => max(0, ($page - 1) * self::PAGE_SIZE),
        ];

        if ($queryOptions->maturity !== 'allowed') {
            $params['filter'] = 'for_ios';
        }

        $payload = $this->getJson(self::SEARCH_URL, $params);

        return $this->mapToPage($payload, $page);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        // No anonymous recommend feed maps cleanly onto a generic grid, so the
        // explore path reuses the tag search with a curated term.
        return $this->search(self::EXPLORE_TERM, $page, $queryOptions);
    }

    protected function timeout(): int
    {
        return (int) config('inspiration.timeouts.tier3', 10);
    }

    protected function authorize(PendingRequest $request): PendingRequest
    {
        return $request
            ->withToken($this->accessToken())
            ->withUserAgent(self::USER_AGENT)
            ->withHeaders(self::APP_HEADERS);
    }

    private function refreshToken(): ?string
    {
        return $this->stringValue($this->credentials['refresh_token'] ?? null);
    }

    /**
     * Resolve an access token, exchanging (and caching) the refresh token on a
     * miss. Every failure surfaces as a SourceException so the manager can
     * degrade instead of leaking a raw OAuth error.
     *
     * @throws SourceException
     */
    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $refreshToken = $this->refreshToken();

        if ($refreshToken === null) {
            throw new SourceException('pixiv: falta el refresh token');
        }

        try {
            $response = Http::timeout($this->timeout())
                ->withUserAgent(self::USER_AGENT)
                ->asForm()
                ->acceptJson()
                ->post(self::TOKEN_URL, [
                    'client_id' => self::CLIENT_ID,
                    'client_secret' => self::CLIENT_SECRET,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'get_secure_url' => 1,
                ]);
        } catch (Throwable $exception) {
            throw new SourceException('pixiv: token request failed ('.$exception->getMessage().')', previous: $exception);
        }

        if ($response->failed()) {
            throw new SourceException('pixiv: token inválido o expirado — revisá tu refresh token');
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new SourceException('pixiv: token inválido o expirado — revisá tu refresh token');
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, self::TOKEN_TTL);

        return $token;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapToPage(array $payload, int $page): Page
    {
        $raw = is_array($payload['illusts'] ?? null) ? $payload['illusts'] : [];
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

        // Ugoira posts are zip archives of frames, not static image URLs.
        if (($entry['type'] ?? null) === 'ugoira') {
            return null;
        }

        $id = $entry['id'] ?? null;
        $images = is_array($entry['image_urls'] ?? null) ? $entry['image_urls'] : [];
        $imageUrl = $this->stringValue($images['large'] ?? null);

        if (! is_scalar($id) || trim((string) $id) === '' || $imageUrl === null) {
            return null;
        }

        $pageUrl = 'https://www.pixiv.net/en/artworks/'.$id;
        $user = is_array($entry['user'] ?? null) ? $entry['user'] : [];
        $username = $this->stringValue($user['name'] ?? null);
        $userId = $user['id'] ?? null;

        return [
            'sourceId' => $this->identifier($id, $pageUrl),
            'pageUrl' => $pageUrl,
            'imageUrl' => $imageUrl,
            'title' => $this->stringValue($entry['title'] ?? null),
            'author' => $username,
            'authorUrl' => is_scalar($userId) && trim((string) $userId) !== ''
                ? 'https://www.pixiv.net/en/users/'.$userId
                : null,
            'thumbnailUrl' => $this->stringValue($images['medium'] ?? null)
                ?? $this->stringValue($images['square_medium'] ?? null),
            'width' => $entry['width'] ?? null,
            'height' => $entry['height'] ?? null,
            'tags' => $this->tagList($entry['tags'] ?? null),
            'maturity' => $this->restrictLevel($entry['x_restrict'] ?? null) > 0 ? 'mature' : 'safe',
        ];
    }

    /**
     * Pixiv reports R-18 as `x_restrict` (0 = all-ages, 1/2 = restricted).
     */
    private function restrictLevel(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
