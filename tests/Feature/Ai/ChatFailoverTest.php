<?php

use App\Models\AiProvider;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function backupSse(string $content = 'Hola desde el backup'): string
{
    return implode("\n\n", [
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['content' => $content], 'finish_reason' => 'stop']]]),
        'data: [DONE]',
    ])."\n\n";
}

test('chat falls back to the backup provider before any content reaches the browser', function () {
    $user = User::factory()->withAiProvider()->create();          // provider A (api.example.com)
    AiProvider::factory()->for($user)->create([
        'name' => 'Backup',
        'url' => 'https://backup.example.com/v1',
        'sort_order' => 1,
    ]);

    Http::fake([
        'api.example.com/*' => Http::response(['error' => ['message' => 'Unauthorized']], 401),
        'backup.example.com/*' => Http::response(backupSse(), 200, ['Content-Type' => 'text/event-stream']),
    ]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'Hola'])->streamedContent();

    expect($content)
        ->toContain('"type":"thread"')
        ->toContain('Hola desde el backup')
        ->not->toContain('"type":"error"')
        ->and(Http::recorded())->toHaveCount(2);

    // The failed attempt never stored a message: the turn is stored once.
    expect(ChatThread::query()->sole()->messages()->count())->toBe(2);
});

test('single provider chain keeps the exact error copy', function () {
    $user = User::factory()->withAiProvider()->create();
    Http::fake(['*' => Http::response(['error' => ['message' => 'Unauthorized']], 401)]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'Hola'])->streamedContent();

    expect($content)
        ->toContain('API key')
        ->not->toContain('proveedores')
        ->toContain('[DONE]');

    expect(Http::recorded())->toHaveCount(1);
});

test('all providers failed surfaces an honest message and reports per-provider detail', function () {
    $user = User::factory()->withAiProvider()->create();
    AiProvider::factory()->for($user)->create([
        'name' => 'Backup',
        'url' => 'https://backup.example.com/v1',
        'sort_order' => 1,
    ]);
    Http::fake(['*' => Http::response(['error' => ['message' => 'Unauthorized']], 401)]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'Hola'])->streamedContent();

    expect($content)
        ->toContain('"type":"error"')
        ->toContain('API key')            // el copy sale del original (previous)
        ->toContain('Fallaron 2 proveedores.')
        ->and(Http::recorded())->toHaveCount(2);
});

test('a successful turn announces which provider answered and records it on the message', function () {
    $user = User::factory()->withAiProvider()->create();
    AiProvider::factory()->for($user)->create([
        'name' => 'Backup',
        'url' => 'https://backup.example.com/v1',
        'sort_order' => 1,
    ]);

    Http::fake([
        'api.example.com/*' => Http::response(['error' => ['message' => 'Unauthorized']], 401),
        'backup.example.com/*' => Http::response(backupSse(), 200, ['Content-Type' => 'text/event-stream']),
    ]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'Hola'])->streamedContent();

    expect($content)->toContain('"type":"meta"')->toContain('"provider":"Backup"')->toContain('"fallback":true');

    $assistant = ChatThread::query()->sole()->messages()->where('role', 'assistant')->sole();

    expect($assistant->meta['ai'])->toBe([
        'provider' => 'Backup',
        'model' => 'test-model',
        'fallback' => true,
    ]);
});

// Regresión de QA (Task 15): el chip «Fallback» del `ProviderBadge` se pintaba
// sólo con el evento SSE en vivo. Al recargar, `thread.tsx` lee
// `message.meta.ai`, pero `ChatMessageResource` no serializaba `meta`: el badge
// desaparecía y el usuario perdía la trazabilidad de qué proveedor respondió.
test('the thread page exposes the persisted turn meta so the badge survives a reload', function () {
    $user = User::factory()->withAiProvider()->create();
    AiProvider::factory()->for($user)->create([
        'name' => 'Backup',
        'url' => 'https://backup.example.com/v1',
        'sort_order' => 1,
    ]);

    Http::fake([
        'api.example.com/*' => Http::response(['error' => ['message' => 'Unauthorized']], 401),
        'backup.example.com/*' => Http::response(backupSse(), 200, ['Content-Type' => 'text/event-stream']),
    ]);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'Hola'])->streamedContent();

    $thread = ChatThread::query()->sole();

    $this->actingAs($user)->get(route('ai.chat.show', $thread))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ai/thread')
            ->where('messages.1.meta.ai.provider', 'Backup')
            ->where('messages.1.meta.ai.model', 'test-model')
            ->where('messages.1.meta.ai.fallback', true));
});
