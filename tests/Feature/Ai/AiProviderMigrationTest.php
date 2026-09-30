<?php

use App\Ai\Support\ByoProviderMigrator;
use App\Models\AiProvider;
use App\Models\User;
use App\Models\UserAiScope;

test('migrates the BYO provider into the registry', function () {
    $user = User::factory()->create([
        'ai_enabled' => true,
        'ai_provider_url' => 'https://byo.example.com/v1',
        'ai_provider_key' => 'sk-byo',
        'ai_model' => 'my-model',
        'ai_embeddings_model' => 'embed-model',
    ]);

    ByoProviderMigrator::migrate($user);

    $provider = AiProvider::query()->where('user_id', $user->id)->sole();
    expect($provider->name)->toBe('Principal')
        ->and($provider->url)->toBe('https://byo.example.com/v1')
        ->and($provider->model)->toBe('my-model')
        ->and($provider->embeddings_model)->toBe('embed-model')
        ->and($provider->key)->toBe('sk-byo');

    $scope = UserAiScope::query()->where('user_id', $user->id)->where('scope', 'global')->sole();
    expect($scope->provider_chain)->toBe([$provider->id]);
});

test('creates nothing for a user without BYO credentials', function () {
    $user = User::factory()->create();
    ByoProviderMigrator::migrate($user);

    expect(AiProvider::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('withAiProvider factory state also creates a registry provider', function () {
    $user = User::factory()->withAiProvider('qa-model')->create();

    $provider = AiProvider::query()->where('user_id', $user->id)->sole();
    expect($provider->model)->toBe('qa-model')
        ->and($provider->url)->toBe('https://api.example.com/v1')
        ->and($user->ai_enabled)->toBeTrue();
});
