<?php

use App\Ai\Web\TavilyClient;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;

beforeEach(function () {
    config()->set('services.tavily.url', 'https://tavily.test');
    config()->set('services.tavily.key', null);
    Http::preventStrayRequests();
});

function tavilyUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

it('resolves the client from the user key, the server key or nothing', function () {
    $user = tavilyUser();

    expect(TavilyClient::for($user))->toBeNull();

    config()->set('services.tavily.key', 'tvly-server');

    expect(TavilyClient::for($user))->not->toBeNull();

    Http::fake(['tavily.test/*' => Http::response(['results' => []], 200)]);

    $withUserKey = TavilyClient::for(tavilyUser(['tavily_api_key' => 'tvly-user']));

    expect($withUserKey)->not->toBeNull();

    $withUserKey->search('hola');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer tvly-user'));
});

it('stores the user key encrypted and keeps it out of serialization', function () {
    $user = tavilyUser(['tavily_api_key' => 'tvly-secret']);

    expect($user->fresh()->tavily_api_key)->toBe('tvly-secret')
        ->and($user->fresh()->toArray())->not->toHaveKey('tavily_api_key');
});

it('parses and numbers search results with caps and truncation', function () {
    Http::fake(['tavily.test/search' => Http::response([
        'results' => [
            [
                'title' => 'Laravel 13',
                'url' => 'https://laravel.test/13',
                'content' => 'Notas de la release',
                'score' => 0.94,
                'favicon' => 'https://laravel.test/favicon.ico',
                'published_date' => '2026-09-20',
            ],
            [
                'title' => 'PHP 8.5',
                'url' => 'https://php.test',
                'content' => str_repeat('x', 5000),
            ],
        ],
        'response_time' => 1.1,
    ], 200)]);

    $client = TavilyClient::for(tavilyUser(['tavily_api_key' => 'tvly-user']));

    $result = $client->search('laravel 13', ['max_results' => 50]);

    expect($result['query'])->toBe('laravel 13')
        ->and($result['results'])->toHaveCount(2)
        ->and(array_column($result['results'], 'n'))->toBe([1, 2])
        ->and($result['results'][0]['title'])->toBe('Laravel 13')
        ->and($result['results'][0]['url'])->toBe('https://laravel.test/13')
        ->and($result['results'][0]['published_date'])->toBe('2026-09-20')
        ->and($result['results'][0]['favicon'])->toBe('https://laravel.test/favicon.ico')
        ->and($result['results'][1]['favicon'])->toBeNull()
        ->and(mb_strlen($result['results'][1]['content']))->toBe(4000);

    Http::assertSent(fn ($request) => $request->url() === 'https://tavily.test/search'
        && $request['query'] === 'laravel 13'
        && $request['search_depth'] === 'basic'
        && $request['max_results'] === 8
        && $request['include_favicon'] === true);
});

it('never requests fewer than one search result', function () {
    Http::fake(['tavily.test/search' => Http::response(['results' => []], 200)]);

    TavilyClient::for(tavilyUser(['tavily_api_key' => 'tvly-user']))->search('x', ['max_results' => 0]);

    Http::assertSent(fn ($request) => $request['max_results'] === 1);
});

it('maps search http errors to actionable messages without throwing', function (int $status, string $message) {
    Http::fake(['tavily.test/search' => Http::response(['detail' => 'nope'], $status)]);

    $result = TavilyClient::for(tavilyUser(['tavily_api_key' => 'tvly-user']))->search('x');

    expect($result)->toBe(['error' => $message]);
})->with([
    'invalid key' => [401, 'Tu key de Tavily no es válida. Revísala en Ajustes → IA.'],
    'rate limited' => [429, 'Demasiadas peticiones a Tavily. Espera unos segundos e inténtalo de nuevo.'],
    'plan limit' => [432, 'Tu plan de Tavily alcanzó su límite. Revisa tu cuenta en tavily.com.'],
    'paygo limit' => [433, 'Tu plan de Tavily alcanzó su límite. Revisa tu cuenta en tavily.com.'],
    'server error' => [500, 'No se pudo contactar con Tavily (HTTP 500). Inténtalo de nuevo más tarde.'],
]);

it('returns an error when tavily is unreachable', function () {
    Http::fake(['tavily.test/*' => fn () => throw new ConnectionException('timed out')]);

    $result = TavilyClient::for(tavilyUser(['tavily_api_key' => 'tvly-user']))->search('x');

    expect($result['error'])->toContain('No se pudo conectar con Tavily');
});

it('extracts pages, reports failures and truncates content', function () {
    Http::fake(['tavily.test/extract' => Http::response([
        'results' => [
            [
                'url' => 'https://laravel.test',
                'raw_content' => str_repeat('a', 20000),
                'favicon' => 'https://laravel.test/favicon.ico',
            ],
        ],
        'failed_results' => [
            ['url' => 'https://php.test', 'error' => 'No content could be extracted'],
        ],
    ], 200)]);

    $client = TavilyClient::for(tavilyUser(['tavily_api_key' => 'tvly-user']));

    $result = $client->extract(['https://laravel.test', 'https://php.test'], 'laravel');

    expect($result['pages'])->toHaveCount(1)
        ->and($result['pages'][0]['url'])->toBe('https://laravel.test')
        ->and($result['pages'][0]['favicon'])->toBe('https://laravel.test/favicon.ico')
        ->and(mb_strlen($result['pages'][0]['content']))->toBe(15000)
        ->and($result['failed'])->toBe([['url' => 'https://php.test', 'error' => 'No content could be extracted']]);

    Http::assertSent(fn ($request) => $request->url() === 'https://tavily.test/extract'
        && $request['urls'] === ['https://laravel.test', 'https://php.test']
        && $request['query'] === 'laravel'
        && $request['format'] === 'markdown'
        && $request['extract_depth'] === 'basic'
        && $request['timeout'] === 20);
});

it('caps extract urls at five and omits a null query', function () {
    Http::fake(['tavily.test/extract' => Http::response(['results' => [], 'failed_results' => []], 200)]);

    $urls = array_map(fn (int $i): string => "https://site{$i}.test", range(1, 7));

    TavilyClient::for(tavilyUser(['tavily_api_key' => 'tvly-user']))->extract($urls);

    Http::assertSent(fn ($request) => count($request['urls']) === 5
        && $request['urls'] === array_slice($urls, 0, 5)
        && ! array_key_exists('query', $request->data()));
});

it('maps extract http errors to actionable messages without throwing', function () {
    Http::fake(['tavily.test/extract' => Http::response(null, 401)]);

    $result = TavilyClient::for(tavilyUser(['tavily_api_key' => 'tvly-user']))->extract(['https://a.test']);

    expect($result)->toBe(['error' => 'Tu key de Tavily no es válida. Revísala en Ajustes → IA.']);
});
