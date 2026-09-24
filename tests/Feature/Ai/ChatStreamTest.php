<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sending a message creates a thread and streams the reply', function () {
    MegalomaniacAgent::fake(['Hola mundo']);

    $user = User::factory()->withAiProvider()->create();

    $response = $this->actingAs($user)->post(route('ai.chat.send'), ['message' => '¿Qué tal?']);

    $response->assertOk();
    $content = $response->streamedContent();

    $deltaText = collect(explode("\n", $content))
        ->filter(fn (string $line): bool => str_starts_with($line, 'data: {"'))
        ->map(fn (string $line): array => json_decode(substr($line, 6), true))
        ->filter(fn (array $event): bool => ($event['type'] ?? null) === 'text_delta')
        ->pluck('delta')
        ->implode('');

    expect($content)->toContain('"type":"thread"');
    expect($content)->toContain('"type":"text_delta"');
    expect($deltaText)->toBe('Hola mundo');
    expect($content)->toContain('[DONE]');

    $thread = ChatThread::query()->forUser($user)->first();

    expect($thread)->not->toBeNull();
    expect($thread->title)->toBe('¿Qué tal?');
    expect($thread->agent)->toBe('megalomaniac');
    expect($thread->messages()->count())->toBe(2);
});

test('sending to an existing thread continues it and stores the model override', function () {
    MegalomaniacAgent::fake(['Seguimos']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $response = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'Continúa',
        'thread_id' => $thread->id,
        'model' => 'model-elegido',
    ]);

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain('"threadId":"'.$thread->id.'"');
    expect($thread->refresh()->model)->toBe('model-elegido');
    expect($thread->messages()->count())->toBe(2);
    expect($thread->fresh()->title)->toBe($thread->title);
});

test('sending to another users thread returns 404', function () {
    MegalomaniacAgent::fake(['No debería']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create();

    $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => 'Hola', 'thread_id' => $thread->id])
        ->assertNotFound();

    MegalomaniacAgent::assertNeverPrompted();
});

test('sending without configured provider returns 422 json', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), ['message' => 'Hola'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Configura tu proveedor de IA en Settings → IA.']);

    expect(ChatThread::query()->count())->toBe(0);
});

test('message is required and limited', function () {
    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), ['message' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), ['message' => str_repeat('a', 4001)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');
});
