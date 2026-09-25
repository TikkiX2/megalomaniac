<?php

use App\Ai\Support\AiProviderResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns user provider when url and key are configured', function () {
    $user = User::factory()->create([
        'ai_enabled' => true,
        'ai_provider_url' => 'https://api.deepseek.com/v1',
        'ai_provider_key' => 'sk-test',
        'ai_model' => 'deepseek-chat',
    ]);

    expect(AiProviderResolver::for($user))->toBe(['user', 'deepseek-chat']);
});

it('falls back to default model when ai_model is empty', function () {
    $user = User::factory()->create([
        'ai_enabled' => true,
        'ai_provider_url' => 'https://api.deepseek.com/v1',
        'ai_provider_key' => 'sk-test',
        'ai_model' => '',
    ]);

    expect(AiProviderResolver::for($user))->toBe(['user', 'gpt-4o-mini']);
});

it('returns nulls when user provider is not configured', function () {
    $user = User::factory()->create(['ai_enabled' => true]);

    expect(AiProviderResolver::for($user))->toBe([null, null]);
});

it('returns nulls when ai is disabled', function () {
    $user = User::factory()->create([
        'ai_enabled' => false,
        'ai_provider_url' => 'https://api.deepseek.com/v1',
        'ai_provider_key' => 'sk-test',
    ]);

    expect(AiProviderResolver::for($user))->toBe([null, null]);
});

it('writes the runtime provider config when resolving', function () {
    $user = User::factory()->withAiProvider('deepseek-chat')->create();

    [$provider, $model] = AiProviderResolver::for($user);

    expect($provider)->toBe('user')
        ->and($model)->toBe('deepseek-chat')
        ->and(config('ai.providers.user.url'))->toBe('https://api.example.com/v1')
        ->and(config('ai.providers.user.key'))->toBe('sk-test')
        ->and(config('ai.providers.user.headers')['User-Agent'])->toBe('megalomaniac-pro/1.0')
        ->and(config('ai.providers.user.headers'))->not->toHaveKey('x-opencode-session');
});

it('adds the opencode session header when a session id is given', function () {
    $user = User::factory()->withAiProvider()->create([
        'ai_provider_url' => 'https://opencode.ai/zen/go/v1',
    ]);

    AiProviderResolver::for($user, 'session-123');

    expect(config('ai.providers.user.headers')['x-opencode-session'])->toBe('session-123');
});

it('does not touch the runtime provider config when falling back to the default', function () {
    $user = User::factory()->create(['ai_enabled' => true]);

    AiProviderResolver::for($user);

    expect(config('ai.providers.user.url'))->toBeNull()
        ->and(config('ai.providers.user.key'))->toBeNull();
});
