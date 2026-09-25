<?php

use App\Ai\Agents\TaskDescriptionAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a description as yoopta blocks', function () {
    TaskDescriptionAgent::fake(["# Objetivo\nImplementar el login."]);

    $user = User::factory()->create(['ai_enabled' => true]);

    $this->actingAs($user)
        ->postJson('/ai/generate-task-description', [
            'prompt' => 'Login con email y password',
            'title' => 'Implementar login',
        ])
        ->assertOk()
        ->assertJsonPath('description.0.type', 'heading')
        ->assertJsonPath('description.0.children.0.text', 'Objetivo')
        ->assertJsonPath('description.1.type', 'paragraph')
        ->assertJsonPath('message', null);
});

it('uses the user provider when configured', function () {
    TaskDescriptionAgent::fake(['Descripción generada.']);

    $user = User::factory()->create([
        'ai_enabled' => true,
        'ai_provider_url' => 'https://api.deepseek.com/v1',
        'ai_provider_key' => 'sk-test',
        'ai_model' => 'deepseek-chat',
    ]);

    $this->actingAs($user)
        ->postJson('/ai/generate-task-description', ['prompt' => 'algo'])
        ->assertOk()
        ->assertJsonPath('description.0.children.0.text', 'Descripción generada.');

    expect(config('ai.providers.user.url'))->toBe('https://api.deepseek.com/v1')
        ->and(config('ai.providers.user.key'))->toBe('sk-test');
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

    $user = User::factory()->create(['ai_enabled' => true]);

    $this->actingAs($user)
        ->postJson('/ai/generate-task-description', ['prompt' => 'algo'])
        ->assertOk()
        ->assertJsonPath('description', null);
});
