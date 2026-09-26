<?php

use App\Ai\Support\WebCitations;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.tavily.url', 'https://tavily.test');
    config()->set('services.tavily.key', null);
});

function citationsThread(User $user): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
}

function citationsToolCallSse(string $tool, string $arguments, string $id = 'call_1'): string
{
    return implode("\n\n", [
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['tool_calls' => [[
            'index' => 0, 'id' => $id, 'type' => 'function',
            'function' => ['name' => $tool, 'arguments' => $arguments],
        ]]], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => [], 'finish_reason' => 'tool_calls']]]),
        'data: [DONE]',
    ])."\n\n";
}

function citationsFinalSse(string $text = 'Listo'): string
{
    return 'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['content' => $text], 'finish_reason' => 'stop']]])."\n\ndata: [DONE]\n\n";
}

function citationsErrorSse(): string
{
    return implode("\n\n", [
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['content' => 'parcial'], 'finish_reason' => null]]]),
        'data: '.json_encode(['error' => ['code' => 'server_error', 'message' => 'boom']]),
        'data: [DONE]',
    ])."\n\n";
}

/**
 * @return array<int, array<string, mixed>>
 */
function citationsStreamEvents(string $content): array
{
    return collect(explode("\n", $content))
        ->filter(fn (string $line): bool => str_starts_with($line, 'data: {"'))
        ->map(fn (string $line): array => json_decode(substr($line, 6), true))
        ->values()
        ->all();
}

/**
 * @return array<int, array{title: ?string, url: string}>
 */
function citationsFrames(string $content): array
{
    return collect(citationsStreamEvents($content))
        ->filter(fn (array $event): bool => ($event['type'] ?? null) === 'citation')
        ->map(fn (array $event): array => $event['citation'])
        ->values()
        ->all();
}

test('web search tool results stream citation frames and persist deduped citations with snippets', function () {
    $user = User::factory()->withAiProvider()->create(['tavily_api_key' => 'tvly-user']);
    $thread = citationsThread($user);

    $long = str_repeat('a', 300);

    Http::fake([
        'api.example.com/*' => Http::sequence()
            ->push(citationsToolCallSse('WebSearchTool', '{"query":"laravel"}', 'call_1'), 200, ['Content-Type' => 'text/event-stream'])
            ->push(citationsToolCallSse('WebSearchTool', '{"query":"mas"}', 'call_2'), 200, ['Content-Type' => 'text/event-stream'])
            ->push(citationsFinalSse(), 200, ['Content-Type' => 'text/event-stream']),
        'tavily.test/search' => Http::sequence()
            ->push(['results' => [
                ['title' => 'Laravel', 'url' => 'https://laravel.test/a', 'content' => $long],
                ['title' => '', 'url' => 'https://otra.test/b', 'content' => 'Snippet corto'],
            ]], 200)
            ->push(['results' => [
                ['title' => 'Duplicada', 'url' => 'https://laravel.test/a', 'content' => 'duplicado'],
                ['title' => 'Tercera', 'url' => 'https://tercera.test/c', 'content' => 'Tercera fuente'],
            ]], 200),
    ]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'busca en internet sobre laravel',
        'thread_id' => $thread->id,
        'tools_policy' => ['mode' => 'manual', 'groups' => ['web']],
    ])->streamedContent();

    $frames = citationsFrames($content);

    expect(array_column($frames, 'url'))->toBe([
        'https://laravel.test/a',
        'https://otra.test/b',
        'https://tercera.test/c',
    ]);

    $events = citationsStreamEvents($content);
    $toolResultIndex = collect($events)->search(fn (array $event): bool => ($event['type'] ?? null) === 'tool_result');
    $firstCitationIndex = collect($events)->search(fn (array $event): bool => ($event['type'] ?? null) === 'citation');

    expect($toolResultIndex)->toBeInt()
        ->and($firstCitationIndex)->toBeInt()
        ->and($firstCitationIndex)->toBeGreaterThan($toolResultIndex);

    $assistant = $thread->messages()->where('role', 'assistant')->orderByDesc('id')->first();

    expect($assistant)->not->toBeNull();

    $citations = $assistant->meta['citations'];

    expect(array_column($citations, 'url'))->toBe([
        'https://laravel.test/a',
        'https://otra.test/b',
        'https://tercera.test/c',
    ])
        ->and($citations[0])->toBe([
            'url' => 'https://laravel.test/a',
            'title' => 'Laravel',
            'snippet' => mb_substr($long, 0, 160),
        ])
        ->and(mb_strlen($citations[0]['snippet']))->toBeLessThanOrEqual(160)
        ->and($citations[1]['title'])->toBe('otra.test');
});

test('a duplicate url across search calls emits a single frame and persists a single citation', function () {
    $user = User::factory()->withAiProvider()->create(['tavily_api_key' => 'tvly-user']);
    $thread = citationsThread($user);

    Http::fake([
        'api.example.com/*' => Http::sequence()
            ->push(citationsToolCallSse('WebSearchTool', '{"query":"uno"}', 'call_1'), 200, ['Content-Type' => 'text/event-stream'])
            ->push(citationsToolCallSse('WebSearchTool', '{"query":"dos"}', 'call_2'), 200, ['Content-Type' => 'text/event-stream'])
            ->push(citationsFinalSse(), 200, ['Content-Type' => 'text/event-stream']),
        'tavily.test/search' => Http::response(['results' => [
            ['title' => 'Repetida', 'url' => 'https://repetida.test/x', 'content' => 'misma fuente'],
        ]], 200),
    ]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'busca en internet dos veces',
        'thread_id' => $thread->id,
        'tools_policy' => ['mode' => 'manual', 'groups' => ['web']],
    ])->streamedContent();

    expect(citationsFrames($content))->toBe([
        ['title' => 'Repetida', 'url' => 'https://repetida.test/x'],
    ]);

    $assistant = $thread->messages()->where('role', 'assistant')->orderByDesc('id')->first();

    expect($assistant->meta['citations'])->toBe([
        ['url' => 'https://repetida.test/x', 'title' => 'Repetida', 'snippet' => 'misma fuente'],
    ]);
});

test('merging native provider citations keeps them first and dedupes web sources by url', function () {
    $merged = WebCitations::merge(
        [['url' => 'https://nativo.test', 'title' => 'Nativo', 'start_index' => 0, 'end_index' => 4]],
        [['url' => 'https://web.test', 'title' => 'Web', 'snippet' => 'snippet web']],
        [
            ['url' => 'https://nativo.test', 'title' => 'Repetida', 'snippet' => 'duplicada'],
            ['url' => 'https://prebusqueda.test', 'title' => null, 'snippet' => null],
        ],
    );

    expect($merged)->toBe([
        ['url' => 'https://nativo.test', 'title' => 'Nativo', 'snippet' => null],
        ['url' => 'https://web.test', 'title' => 'Web', 'snippet' => 'snippet web'],
        ['url' => 'https://prebusqueda.test', 'title' => null, 'snippet' => null],
    ]);
});

test('malformed and non-web tool results are ignored', function () {
    $event = ['type' => 'tool_result', 'successful' => true, 'tool_name' => 'WebSearchTool', 'result' => 'not-json'];

    expect(WebCitations::fromToolResult($event))->toBe([])
        ->and(WebCitations::fromToolResult([
            'type' => 'tool_result', 'successful' => true, 'tool_name' => 'WebSearchTool',
            'result' => json_encode(['error' => 'boom']),
        ]))->toBe([])
        ->and(WebCitations::fromToolResult([
            'type' => 'tool_result', 'successful' => true, 'tool_name' => 'ActionTool',
            'result' => json_encode(['results' => [['url' => 'https://x.test']]]),
        ]))->toBe([])
        ->and(WebCitations::fromToolResult([
            'type' => 'tool_result', 'successful' => false, 'tool_name' => 'WebSearchTool',
            'result' => json_encode(['results' => [['url' => 'https://x.test']]]),
        ]))->toBe([]);
});

test('fetch tool pages produce citations with a hostname title fallback', function () {
    $user = User::factory()->withAiProvider()->create(['tavily_api_key' => 'tvly-user']);
    $thread = citationsThread($user);

    $page = str_repeat('b', 200);

    Http::fake([
        'api.example.com/*' => Http::sequence()
            ->push(citationsToolCallSse('WebFetchTool', '{"urls":["https://docs.laravel.test/guide"]}'), 200, ['Content-Type' => 'text/event-stream'])
            ->push(citationsFinalSse(), 200, ['Content-Type' => 'text/event-stream']),
        'tavily.test/extract' => Http::response(['results' => [
            ['url' => 'https://docs.laravel.test/guide', 'raw_content' => $page],
        ]], 200),
    ]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'lee la documentación de laravel',
        'thread_id' => $thread->id,
        'tools_policy' => ['mode' => 'manual', 'groups' => ['web']],
    ])->streamedContent();

    expect(citationsFrames($content))->toBe([
        ['title' => 'docs.laravel.test', 'url' => 'https://docs.laravel.test/guide'],
    ]);

    $assistant = $thread->messages()->where('role', 'assistant')->orderByDesc('id')->first();

    $citations = $assistant->meta['citations'];

    expect($citations)->toHaveCount(1)
        ->and($citations[0]['title'])->toBe('docs.laravel.test')
        ->and($citations[0]['snippet'])->toBe(mb_substr($page, 0, 160))
        ->and(mb_strlen($citations[0]['snippet']))->toBeLessThanOrEqual(160);
});

test('a failed turn does not overwrite the citations of the previous assistant message', function () {
    $user = User::factory()->withAiProvider()->create(['tavily_api_key' => 'tvly-user']);
    $thread = citationsThread($user);

    $previous = ChatMessage::factory()->assistant()->create([
        'conversation_id' => $thread->id,
        'meta' => ['citations' => [['url' => 'https://previa.test', 'title' => 'Previa', 'snippet' => 'previa']]],
    ]);

    Http::fake([
        'api.example.com/*' => Http::sequence()
            ->push(citationsToolCallSse('WebSearchTool', '{"query":"laravel"}'), 200, ['Content-Type' => 'text/event-stream'])
            ->push(citationsErrorSse(), 200, ['Content-Type' => 'text/event-stream']),
        'tavily.test/search' => Http::response(['results' => [
            ['title' => 'Nueva', 'url' => 'https://nueva.test', 'content' => 'nueva fuente'],
        ]], 200),
    ]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'busca algo y falla',
        'thread_id' => $thread->id,
        'tools_policy' => ['mode' => 'manual', 'groups' => ['web']],
    ])->streamedContent();

    expect($content)->toContain('"type":"citation"')->toContain('"type":"error"');

    expect($previous->refresh()->meta['citations'])->toBe([
        ['url' => 'https://previa.test', 'title' => 'Previa', 'snippet' => 'previa'],
    ]);
    expect($thread->messages()->where('role', 'assistant')->count())->toBe(1);
});

test('pre-search sources from a forced web search are persisted as citations', function () {
    $user = User::factory()->withAiProvider()->create(['tavily_api_key' => 'tvly-user']);
    $thread = citationsThread($user);

    $source = str_repeat('c', 200);

    Http::fake([
        'api.example.com/*' => Http::response(citationsFinalSse(), 200, ['Content-Type' => 'text/event-stream']),
        'tavily.test/search' => Http::response(['results' => [
            ['title' => 'Laravel 13', 'url' => 'https://laravel.test/13', 'content' => $source],
        ]], 200),
    ]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Novedades de Laravel?',
        'thread_id' => $thread->id,
        'force_web' => true,
    ])->streamedContent();

    expect($content)->toContain('[DONE]');
    expect(citationsFrames($content))->toBe([]);

    $assistant = $thread->messages()->where('role', 'assistant')->orderByDesc('id')->first();

    $citations = $assistant->meta['citations'];

    expect($citations)->toHaveCount(1)
        ->and($citations[0]['url'])->toBe('https://laravel.test/13')
        ->and($citations[0]['title'])->toBe('Laravel 13')
        ->and($citations[0]['snippet'])->toBe(mb_substr($source, 0, 160))
        ->and(mb_strlen($citations[0]['snippet']))->toBeLessThanOrEqual(160);
});
