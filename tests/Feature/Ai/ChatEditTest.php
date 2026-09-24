<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('editing a user message truncates from it and streams the new reply', function () {
    MegalomaniacAgent::fake(['Respuesta editada']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
    $first = ChatMessage::factory()->create(['conversation_id' => $thread->id, 'content' => 'Primera']);
    ChatMessage::factory()->assistant()->create(['conversation_id' => $thread->id, 'content' => 'Vieja']);
    $edited = ChatMessage::factory()->create(['conversation_id' => $thread->id, 'content' => 'Segunda']);

    $response = $this->actingAs($user)->post(route('ai.chat.edit', $thread), [
        'message_id' => $edited->id,
        'content' => 'Segunda corregida',
    ]);

    $response->assertOk();

    $content = $response->streamedContent();

    $deltaText = collect(explode("\n", $content))
        ->filter(fn (string $line): bool => str_starts_with($line, 'data: {"'))
        ->map(fn (string $line): array => json_decode(substr($line, 6), true))
        ->filter(fn (array $event): bool => ($event['type'] ?? null) === 'text_delta')
        ->pluck('delta')
        ->implode('');

    expect($content)->toContain('"type":"text_delta"');
    expect($deltaText)->toBe('Respuesta editada');

    $messages = $thread->messages()->orderBy('id')->get()->pluck('content')->all();

    expect($messages)->toBe(['Primera', 'Vieja', 'Segunda corregida', 'Respuesta editada']);
});

test('editing an assistant message returns 422', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
    $assistant = ChatMessage::factory()->assistant()->create(['conversation_id' => $thread->id]);

    $this->actingAs($user)
        ->postJson(route('ai.chat.edit', $thread), ['message_id' => $assistant->id, 'content' => 'x'])
        ->assertStatus(422);
});

test('editing another users thread returns 403', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create();
    $message = ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    $this->actingAs($user)
        ->postJson(route('ai.chat.edit', $thread), ['message_id' => $message->id, 'content' => 'x'])
        ->assertForbidden();
});
