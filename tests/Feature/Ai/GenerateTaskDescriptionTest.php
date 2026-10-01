<?php

use App\Ai\Agents\TaskDescriptionAgent;
use App\Models\AiProvider;
use App\Models\User;
use App\Models\UserAiScope;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a description as yoopta blocks', function () {
    TaskDescriptionAgent::fake(["# Objetivo\nImplementar el login."]);

    $user = User::factory()->withAiProvider()->create();

    $response = $this->actingAs($user)->postJson('/ai/generate-task-description', [
        'prompt' => 'Login con email y password',
        'title' => 'Implementar login',
    ]);

    $response->assertOk()->assertJsonPath('message', null);

    $blocks = collect($response->json('description'))
        ->sortBy(fn (array $block): int => $block['meta']['order'] ?? 0)
        ->values();

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['type'])->toBe('HeadingOne')
        ->and($blocks[0]['value'][0]['type'])->toBe('heading-one')
        ->and($blocks[0]['value'][0]['children'][0]['text'])->toBe('Objetivo')
        ->and($blocks[1]['type'])->toBe('Paragraph')
        ->and($blocks[1]['value'][0]['children'][0]['text'])->toBe('Implementar el login.');
});

it('uses the provider of the resolved freelance scope', function () {
    TaskDescriptionAgent::fake(['Descripción generada.']);

    $user = User::factory()->withAiProvider()->create();
    $provider = AiProvider::factory()->for($user)->create([
        'name' => 'Freelance',
        'url' => 'https://api.deepseek.com/v1',
        'key' => 'sk-deepseek',
        'model' => 'deepseek-chat',
    ]);
    UserAiScope::factory()->for($user)->create([
        'scope' => 'module:freelance',
        'provider_chain' => [$provider->id],
    ]);

    $response = $this->actingAs($user)
        ->postJson('/ai/generate-task-description', ['prompt' => 'algo'])
        ->assertOk();

    $block = collect($response->json('description'))->values()->first();

    expect($block['type'])->toBe('Paragraph')
        ->and($block['value'][0]['children'][0]['text'])->toBe('Descripción generada.');

    // The scope's provider is wired under its own registry key, not `user`.
    expect(config('ai.providers.pm'.$provider->id.'.url'))->toBe('https://api.deepseek.com/v1')
        ->and(config('ai.providers.pm'.$provider->id.'.key'))->toBe('sk-deepseek');
});

it('returns message when ai is disabled', function () {
    $user = User::factory()->create(['ai_enabled' => false]);

    $this->actingAs($user)
        ->postJson('/ai/generate-task-description', ['prompt' => 'algo'])
        ->assertOk()
        ->assertJsonPath('description', null);
});

it('validates prompt', function () {
    $user = User::factory()->create(['ai_enabled' => true]);

    $this->actingAs($user)
        ->postJson('/ai/generate-task-description', [])
        ->assertUnprocessable();
});

it('returns message when the model output is empty', function () {
    TaskDescriptionAgent::fake(['   ']);

    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)
        ->postJson('/ai/generate-task-description', ['prompt' => 'algo'])
        ->assertOk()
        ->assertJsonPath('description', null);
});
