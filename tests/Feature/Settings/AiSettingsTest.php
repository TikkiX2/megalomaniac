<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('ai settings page never exposes the provider key', function () {
    $user = User::factory()->withAiProvider()->create(['ai_provider_key' => 'sk-secreto']);

    $this->actingAs($user)
        ->get(route('ai-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai')
            ->where('ai.has_provider_key', true)
            ->missing('ai.ai_provider_key')
        );
});

test('empty provider key keeps the stored key', function () {
    $user = User::factory()->withAiProvider()->create(['ai_provider_key' => 'sk-original']);

    $this->actingAs($user)
        ->put(route('ai-settings.update'), [
            'ai_provider_url' => 'https://api.example.com/v1',
            'ai_provider_key' => '',
            'ai_model' => 'modelo',
            'ai_enabled' => true,
        ])
        ->assertRedirect(route('ai-settings.edit'));

    expect($user->refresh()->ai_provider_key)->toBe('sk-original');
});

test('new provider key replaces the stored key and model cache is forgotten', function () {
    $user = User::factory()->withAiProvider()->create(['ai_provider_key' => 'sk-original']);

    Cache::put("ai.models.{$user->id}", ['viejo'], now()->addMinutes(5));

    $this->actingAs($user)
        ->put(route('ai-settings.update'), [
            'ai_provider_url' => 'https://api.example.com/v1',
            'ai_provider_key' => 'sk-nueva',
            'ai_model' => 'modelo',
            'ai_enabled' => true,
        ])
        ->assertRedirect(route('ai-settings.edit'));

    expect($user->refresh()->ai_provider_key)->toBe('sk-nueva');
    expect(Cache::has("ai.models.{$user->id}"))->toBeFalse();
});
