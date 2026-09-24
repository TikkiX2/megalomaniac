<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('regenerate drops the last exchange and streams a new reply', function () {
    MegalomaniacAgent::fake(['Respuesta nueva']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
    ChatMessage::factory()->create(['conversation_id' => $thread->id, 'content' => 'Pregunta original']);
    ChatMessage::factory()->assistant()->create(['conversation_id' => $thread->id, 'content' => 'Respuesta vieja']);

    $response = $this->actingAs($user)->post(route('ai.chat.regenerate', $thread));

    $response->assertOk();

    $content = $response->streamedContent();

    $deltaText = collect(explode("\n", $content))
        ->filter(fn (string $line): bool => str_starts_with($line, 'data: {"'))
        ->map(fn (string $line): array => json_decode(substr($line, 6), true))
        ->filter(fn (array $event): bool => ($event['type'] ?? null) === 'text_delta')
        ->pluck('delta')
        ->implode('');

    expect($content)->toContain('"type":"text_delta"');
    expect($deltaText)->toBe('Respuesta nueva');

    $messages = $thread->messages()->orderBy('id')->get();

    expect($messages)->toHaveCount(2);
    expect($messages[0]->content)->toBe('Pregunta original');
    expect($messages[1]->content)->toBe('Respuesta nueva');
});

test('regenerate without exchange returns 422', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->postJson(route('ai.chat.regenerate', $thread))
        ->assertStatus(422);
});
