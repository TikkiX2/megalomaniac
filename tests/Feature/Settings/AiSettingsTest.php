<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

test('embeddings model is saved and exposed in the settings page', function () {
    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)
        ->put(route('ai-settings.update'), [
            'ai_provider_url' => 'https://api.example.com/v1',
            'ai_model' => 'modelo',
            'ai_embeddings_model' => 'text-embedding-3-small',
            'ai_enabled' => true,
        ])
        ->assertRedirect(route('ai-settings.edit'));

    expect($user->refresh()->ai_embeddings_model)->toBe('text-embedding-3-small');

    $this->actingAs($user)
        ->get(route('ai-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai')
            ->where('ai.ai_embeddings_model', 'text-embedding-3-small')
        );
});

test('ai settings page exposes tavily key presence without the key', function () {
    $user = User::factory()->create(['tavily_api_key' => 'tvly-secreto']);

    $this->actingAs($user)
        ->get(route('ai-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai')
            ->where('ai.has_tavily_key', true)
            ->missing('ai.tavily_api_key')
        );
});

test('ai settings page reports no tavily key when none is stored', function () {
    $user = User::factory()->create(['tavily_api_key' => null]);

    $this->actingAs($user)
        ->get(route('ai-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai')
            ->where('ai.has_tavily_key', false)
        );
});

test('tavily key is stored encrypted and round-trips', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('ai-settings.update'), [
            'ai_enabled' => true,
            'tavily_api_key' => 'tvly-secreto-123',
        ])
        ->assertRedirect(route('ai-settings.edit'));

    $raw = DB::table('users')->where('id', $user->id)->value('tavily_api_key');

    expect($raw)->not->toBe('tvly-secreto-123')
        ->and(Str::startsWith((string) $raw, 'eyJ'))->toBeTrue()
        ->and($user->refresh()->tavily_api_key)->toBe('tvly-secreto-123');
});

test('blank tavily key keeps the stored key', function () {
    $user = User::factory()->create(['tavily_api_key' => 'tvly-original']);

    $this->actingAs($user)
        ->put(route('ai-settings.update'), [
            'ai_enabled' => true,
            'tavily_api_key' => '',
        ])
        ->assertRedirect(route('ai-settings.edit'));

    expect($user->refresh()->tavily_api_key)->toBe('tvly-original');
});

test('sources page exposes tavily key presence through the shared ai state', function () {
    $this->withoutVite();

    $user = User::factory()->withAiProvider()->create(['tavily_api_key' => 'tvly-secreto']);

    $this->actingAs($user)
        ->get(route('ai.sources.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/sources', false)
            ->where('ai.has_tavily_key', true)
            ->missing('ai.tavily_api_key')
        );
});
