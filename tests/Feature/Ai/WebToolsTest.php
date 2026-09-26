<?php

use App\Ai\Tools\ToolCatalog;
use App\Ai\Tools\ToolRouter;
use App\Ai\Tools\WebFetchTool;
use App\Ai\Tools\WebSearchTool;
use App\Models\User;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    config()->set('services.tavily.url', 'https://tavily.test');
    config()->set('services.tavily.key', null);
    Http::preventStrayRequests();
});

function webToolUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

function webToolKeyUser(): User
{
    return webToolUser(['tavily_api_key' => 'tvly-user']);
}

it('searches through the tool and carries the citation contract', function () {
    Http::fake(['tavily.test/search' => Http::response([
        'results' => [
            ['title' => 'Laravel', 'url' => 'https://laravel.test', 'content' => 'Framework'],
        ],
    ], 200)]);

    $tool = new WebSearchTool(webToolKeyUser());

    $payload = json_decode((string) $tool->handle(new Request(['query' => 'laravel'])), true);

    expect($payload['query'])->toBe('laravel')
        ->and($payload['results'][0]['n'])->toBe(1)
        ->and($payload['results'][0]['title'])->toBe('Laravel')
        ->and($payload['results'][0]['url'])->toBe('https://laravel.test')
        ->and((string) $tool->description())->toContain('numerados')
        ->and((string) $tool->description())->toContain('[n]')
        ->and((string) $tool->description())->toContain('nunca inventes URLs');
});

it('exposes the search schema', function () {
    $schema = (new WebSearchTool(webToolUser()))->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['query', 'max_results', 'topic', 'time_range'])
        ->and($schema['query']->toArray()['type'])->toBe('string')
        ->and($schema['max_results']->toArray())
        ->toMatchArray(['type' => 'integer', 'default' => 6])
        ->and($schema['topic']->toArray()['enum'])->toBe(['general', 'news', 'finance'])
        ->and($schema['time_range']->toArray()['enum'])->toBe(['day', 'week', 'month', 'year']);
});

it('returns a configuration error when the user has no tavily key', function () {
    $payload = json_decode((string) (new WebSearchTool(webToolUser()))->handle(
        new Request(['query' => 'laravel'])
    ), true);

    expect($payload['error'])->toContain('Tavily');
    Http::assertNothingSent();
});

it('rejects an empty search query without calling tavily', function () {
    $payload = json_decode((string) (new WebSearchTool(webToolKeyUser()))->handle(new Request([])), true);

    expect($payload['error'])->toContain('consulta');
    Http::assertNothingSent();
});

it('returns tavily failures as tool results instead of throwing', function () {
    Http::fake(['tavily.test/search' => Http::response(null, 401)]);

    $payload = json_decode((string) (new WebSearchTool(webToolKeyUser()))->handle(
        new Request(['query' => 'laravel'])
    ), true);

    expect($payload)->toBe(['error' => 'Tu key de Tavily no es válida. Revísala en Ajustes → IA.']);
});

it('fetches only valid http(s) urls', function () {
    Http::fake(['tavily.test/extract' => Http::response([
        'results' => [
            ['url' => 'https://laravel.test', 'raw_content' => 'Contenido'],
        ],
        'failed_results' => [],
    ], 200)]);

    $tool = new WebFetchTool(webToolKeyUser());

    $payload = json_decode((string) $tool->handle(new Request([
        'urls' => ['ftp://laravel.test', 'no-es-url', 'https://laravel.test'],
        'query' => 'docs',
    ])), true);

    expect($payload['pages'])->toHaveCount(1)
        ->and($payload['pages'][0]['url'])->toBe('https://laravel.test')
        ->and($payload['pages'][0]['content'])->toBe('Contenido')
        ->and($payload['failed'])->toBe([]);

    Http::assertSent(fn ($request) => $request->url() === 'https://tavily.test/extract'
        && $request['urls'] === ['https://laravel.test']
        && $request['query'] === 'docs');
});

it('fails without http calls when no url is valid', function () {
    $payload = json_decode((string) (new WebFetchTool(webToolKeyUser()))->handle(
        new Request(['urls' => ['ftp://x.test', 'javascript:alert(1)', 'solo texto']])
    ), true);

    expect($payload['error'])->toContain('URL');
    Http::assertNothingSent();
});

it('returns a configuration error from the fetch tool when no key is available', function () {
    $payload = json_decode((string) (new WebFetchTool(webToolUser()))->handle(
        new Request(['urls' => ['https://laravel.test']])
    ), true);

    expect($payload['error'])->toContain('Tavily');
    Http::assertNothingSent();
});

it('exposes the fetch schema', function () {
    $schema = (new WebFetchTool(webToolUser()))->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['urls', 'query'])
        ->and($schema['urls']->toArray())
        ->toMatchArray(['type' => 'array', 'minItems' => 1, 'maxItems' => 5])
        ->and($schema['urls']->toArray()['items']['type'])->toBe('string')
        ->and($schema['query']->toArray()['type'])->toBe('string');
});

it('registers the web group with both tools', function () {
    expect(ToolCatalog::groups()['web'])->toBe([
        'label' => 'Web',
        'tools' => [WebSearchTool::class, WebFetchTool::class],
    ])
        ->and(ToolCatalog::isValidGroup('web'))->toBeTrue();

    $tools = collect(ToolCatalog::toolsFor(webToolUser(), ['web']))
        ->map(fn ($tool): string => $tool::class)
        ->all();

    expect($tools)->toBe([WebSearchTool::class, WebFetchTool::class]);
});

it('routes web intent to the web group without making it a fallback', function () {
    expect(ToolRouter::route('Busca en internet noticias de última hora'))->toContain('web')
        ->and(ToolRouter::route('¿Cuál es la actualidad de Laravel?'))->toContain('web')
        ->and(ToolRouter::route('Hola, ¿cómo estás?'))->not->toContain('web');
});
