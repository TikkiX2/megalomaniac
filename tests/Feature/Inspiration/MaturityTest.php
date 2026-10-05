<?php

declare(strict_types=1);

use App\Inspiration\InspirationSettings;
use App\Inspiration\SourceManager;
use App\Inspiration\SourceMaturity;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Raw fixture body for the maturity assertions.
 */
function maturityFixture(string $path): string
{
    $contents = file_get_contents(base_path('tests/Fixtures/Inspiration/'.$path));

    if ($contents === false) {
        throw new RuntimeException("Missing inspiration fixture [{$path}].");
    }

    return $contents;
}

/**
 * Stub every host the given adapter talks to for a single happy request.
 */
function fakeMaturityHttp(string $key): void
{
    $body = maturityFixture($key.'/search.json');

    Http::fake(match ($key) {
        'wallhaven' => [
            '*wallhaven.cc/api/v1/search*' => Http::response($body),
        ],
        'deviantart' => [
            '*oauth2/token*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            '*api/v1/oauth2/search/art*' => Http::response($body),
        ],
        'gelbooru' => [
            '*gelbooru.com/index.php*' => Http::response($body),
        ],
        'giphy' => [
            '*api.giphy.com/v1/gifs/search*' => Http::response($body),
        ],
        'pixabay' => [
            '*pixabay.com/api*' => Http::response($body),
        ],
        'flickr' => [
            '*api.flickr.com/services/rest*' => Http::response($body),
        ],
        'pixiv' => [
            '*oauth.secure.pixiv.net/auth/token*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            '*app-api.pixiv.net/v1/search/illust*' => Http::response($body),
        ],
        'unsplash' => [
            '*api.unsplash.com/search/photos*' => Http::response($body),
        ],
        default => throw new InvalidArgumentException("Unknown maturity source [{$key}]."),
    });
}

/**
 * The canonical credential bag for a source, keyed by the config field name.
 *
 * @return array<string, array<string, string>>
 */
function maturityCredentials(string $key): array
{
    /** @var array<string, array<int, string>> $fields */
    $fields = config('inspiration.credential_fields', []);
    $field = $fields[$key][0] ?? 'key';

    return [$key => [$field => 'test-'.$field]];
}

/**
 * A user with the source enabled, credentials hydrated and the maturity toggle
 * set to the requested value.
 */
function maturityUser(string $key, bool $allowed): User
{
    $user = User::factory()->create();

    app(InspirationSettings::class)->update($user, [
        'enabled_sources' => [$key],
        'keys' => maturityCredentials($key),
        'maturity' => $allowed,
    ]);

    return $user;
}

beforeEach(function (): void {
    config([
        'inspiration.deviantart.client_id' => 'test-client-id',
        'inspiration.deviantart.client_secret' => 'test-client-secret',
    ]);
});

/**
 * OFF/ON expectations for every source that maps maturity onto a query param.
 */
dataset('maturityParameterCases', [
    'wallhaven safe' => ['wallhaven', false, 'purity', '100'],
    'wallhaven allowed' => ['wallhaven', true, 'purity', '110'],
    'deviantart safe' => ['deviantart', false, 'mature_content', 'false'],
    'deviantart allowed' => ['deviantart', true, 'mature_content', 'true'],
    'gelbooru safe' => ['gelbooru', false, 'tags', 'portrait -rating:explicit'],
    'gelbooru allowed' => ['gelbooru', true, 'tags', 'portrait'],
    'giphy safe' => ['giphy', false, 'rating', 'pg'],
    'giphy allowed' => ['giphy', true, 'rating', 'r'],
    'pixabay safe' => ['pixabay', false, 'safesearch', '1'],
    'pixabay allowed' => ['pixabay', true, 'safesearch', '0'],
    'flickr safe' => ['flickr', false, 'safe_search', '1'],
    'flickr allowed' => ['flickr', true, 'safe_search', '3'],
]);

it('maps the global maturity toggle into the :key request', function (string $key, bool $allowed, string $param, string $expected): void {
    fakeMaturityHttp($key);
    $user = maturityUser($key, $allowed);

    app(SourceManager::class)->search($user, $key, 'portrait', 1);

    Http::assertSent(function (Request $request) use ($param, $expected): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query[$param] ?? null) === $expected;
    });
})->with('maturityParameterCases');

it('sends no maturity parameter for a source without maturity support', function (bool $allowed): void {
    fakeMaturityHttp('unsplash');
    $user = maturityUser('unsplash', $allowed);

    app(SourceManager::class)->search($user, 'unsplash', 'portrait', 1);

    Http::assertSent(function (Request $request): bool {
        $url = $request->url();

        return str_contains($url, 'api.unsplash.com')
            && ! str_contains($url, 'purity')
            && ! str_contains($url, 'mature_content')
            && ! str_contains($url, 'safesearch')
            && ! str_contains($url, 'safe_search')
            && ! str_contains($url, 'rating')
            && ! str_contains($url, 'maturity');
    });
})->with([false, true]);

it('keeps wallhaven at sfw purity when allowed without a user key', function (): void {
    fakeMaturityHttp('wallhaven');

    $user = User::factory()->create();

    app(InspirationSettings::class)->update($user, [
        'enabled_sources' => ['wallhaven'],
        'keys' => ['wallhaven' => ['key' => '']],
        'maturity' => true,
    ]);

    app(SourceManager::class)->search($user, 'wallhaven', 'portrait', 1);

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['purity'] ?? null) === '100';
    });
});

it('resolves the pixiv maturity token ahead of its adapter', function (): void {
    expect(SourceMaturity::forSource('pixiv', false)->maturity)->toBe('safe')
        ->and(SourceMaturity::forSource('pixiv', true)->maturity)->toBe('allowed');
});

it('adds the pixiv for_ios filter only when maturity is safe', function (bool $allowed): void {
    fakeMaturityHttp('pixiv');
    $user = maturityUser('pixiv', $allowed);

    app(SourceManager::class)->search($user, 'pixiv', 'portrait', 1);

    Http::assertSent(function (Request $request) use ($allowed): bool {
        if (! str_contains($request->url(), 'app-api.pixiv.net/v1/search/illust')) {
            return false;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        // Documented decision: Pixiv's R-18 boundary is account-scoped, so the
        // global toggle only adds Pixiv's iOS filter on the safe token and
        // omits it entirely when mature content is allowed.
        return $allowed
            ? ! array_key_exists('filter', $query)
            : ($query['filter'] ?? null) === 'for_ios';
    });
})->with([false, true]);
