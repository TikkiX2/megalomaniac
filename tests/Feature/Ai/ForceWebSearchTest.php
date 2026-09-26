<?php

use App\Ai\Services\ChatService;
use App\Models\ChatThread;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.tavily.url', 'https://tavily.test');
    config()->set('services.tavily.key', null);
});

function forceWebThread(User $user, ?string $mode = null): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'mode' => $mode,
    ]);
}

function forceWebSse(): PromiseInterface
{
    return Http::response(
        "data: {\"choices\":[{\"delta\":{\"content\":\"ok\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n",
        200,
        ['Content-Type' => 'text/event-stream'],
    );
}

function forceWebTavily(): PromiseInterface
{
    return Http::response([
        'results' => [
            [
                'title' => 'Laravel 13',
                'url' => 'https://laravel.test/13',
                'content' => 'Notas de la release de Laravel 13.',
            ],
        ],
    ], 200);
}

function forceWebProviderPayload(): array
{
    $payload = [];

    Http::assertSent(function (Request $request) use (&$payload): bool {
        if (! str_contains($request->url(), 'api.example.com')) {
            return false;
        }

        $payload = $request->data();

        return true;
    });

    return $payload;
}

function forceWebErrorMessages(string $content): array
{
    return collect(explode("\n", $content))
        ->filter(fn (string $line): bool => str_starts_with($line, 'data: {"'))
        ->map(fn (string $line): ?array => json_decode(substr($line, 6), true))
        ->filter(fn (?array $event): bool => ($event['type'] ?? null) === 'error')
        ->pluck('message')
        ->filter()
        ->values()
        ->all();
}

test('force web injects tavily results into the provider prompt but not the stored message', function () {
    $user = User::factory()->withAiProvider()->create(['tavily_api_key' => 'tvly-user']);
    $thread = forceWebThread($user, 'both');

    Http::fake([
        'api.example.com/*' => forceWebSse(),
        'tavily.test/*' => forceWebTavily(),
    ]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Novedades de Laravel?',
        'thread_id' => $thread->id,
        'force_web' => true,
    ])->streamedContent();

    expect($content)->toContain('[DONE]');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'tavily.test/search')
        && $request['query'] === '¿Novedades de Laravel?'
        && $request['max_results'] === 6);

    $providerBody = json_encode(forceWebProviderPayload());

    expect($providerBody)
        ->toContain('--- Resultados web (contexto) ---')
        ->toContain('[1] Laravel 13');

    $userMessage = $thread->messages()->where('role', 'user')->orderByDesc('id')->first();

    expect($userMessage->content)->toBe('¿Novedades de Laravel?')
        ->and($userMessage->content)->not->toContain('Resultados web');
});

test('force web does not search or inject when the thread mode is local', function () {
    $user = User::factory()->withAiProvider()->create(['tavily_api_key' => 'tvly-user']);
    $thread = forceWebThread($user, 'local');

    Http::fake([
        'api.example.com/*' => forceWebSse(),
        'tavily.test/*' => forceWebTavily(),
    ]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'busca algo',
        'thread_id' => $thread->id,
        'force_web' => true,
    ])->streamedContent();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'tavily.test'));

    expect(json_encode(forceWebProviderPayload()))->not->toContain('--- Resultados web (contexto) ---')
        ->and($content)->not->toContain('"recoverable":true')
        ->toContain('[DONE]');
});

test('force web without a tavily key warns on the stream and skips the search', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = forceWebThread($user, 'both');

    Http::fake([
        'api.example.com/*' => forceWebSse(),
        'tavily.test/*' => forceWebTavily(),
    ]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'busca algo',
        'thread_id' => $thread->id,
        'force_web' => true,
    ])->streamedContent();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'tavily.test'));

    expect($content)
        ->toContain('"recoverable":true')
        ->toContain('[DONE]')
        ->and(forceWebErrorMessages($content))
        ->toContain('Configura tu API key de Tavily en Settings → IA para buscar en la web.');
});

test('a tavily failure warns on the stream and the turn continues without web context', function () {
    $user = User::factory()->withAiProvider()->create(['tavily_api_key' => 'tvly-user']);
    $thread = forceWebThread($user, 'both');

    Http::fake([
        'api.example.com/*' => forceWebSse(),
        'tavily.test/*' => Http::response(['detail' => 'boom'], 500),
    ]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'busca algo',
        'thread_id' => $thread->id,
        'force_web' => true,
    ])->streamedContent();

    expect($content)
        ->toContain('"recoverable":true')
        ->toContain('HTTP 500')
        ->toContain('text_delta')
        ->toContain('[DONE]');

    expect(json_encode(forceWebProviderPayload()))->not->toContain('--- Resultados web (contexto) ---');
});

test('the service exposes the injected sources after a successful forced search', function () {
    $user = User::factory()->withAiProvider()->create(['tavily_api_key' => 'tvly-user']);
    $thread = forceWebThread($user, 'both');

    Http::fake([
        'api.example.com/*' => forceWebSse(),
        'tavily.test/*' => forceWebTavily(),
    ]);

    $service = app(ChatService::class);
    $this->app->instance(ChatService::class, $service);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Novedades de Laravel?',
        'thread_id' => $thread->id,
        'force_web' => true,
    ])->streamedContent();

    expect($service->lastWebSources)->toHaveCount(1)
        ->and($service->lastWebSources[0]['url'])->toBe('https://laravel.test/13')
        ->and($service->lastWebWarning)->toBeNull();
});
