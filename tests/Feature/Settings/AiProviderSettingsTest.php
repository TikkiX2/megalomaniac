<?php

use App\Models\AiProvider;
use App\Models\AiProviderHealth;
use App\Models\User;
use App\Models\UserAiScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('edit exposes providers, health and effective chains scoped to the user', function () {
    $me = User::factory()->withAiProvider()->create();
    User::factory()->withAiProvider()->create();   // provider ajeno no debe aparecer

    $this->actingAs($me)->get(route('settings.ai.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai')
            ->has('providers', 1)
            ->where('providers.0.name', 'Principal')
            ->where('providers.0.has_key', true)
            ->count('scopes', 14)
            ->where('scopes.0.scope', 'global')
            ->where('scopes.0.chain', [])
            ->where('scopes.0.effective', [AiProvider::query()->where('user_id', $me->id)->value('id')])
            ->where('scopes.0.prompt_preview', null)
        );
});

test('creates, updates, tests and deletes a provider', function () {
    $user = User::factory()->create(['ai_enabled' => true]);

    $this->actingAs($user)->post(route('settings.ai.providers.store'), [
        'name' => 'Backup', 'protocol' => 'openai_compatible',
        'url' => 'https://backup.example.com/v1', 'key' => 'sk-b', 'model' => 'gpt-4o-mini',
    ])->assertRedirect();

    $provider = AiProvider::query()->where('user_id', $user->id)->where('name', 'Backup')->sole();

    Http::fake(['backup.example.com/*' => Http::response(['choices' => []], 200)]);
    $this->actingAs($user)->post(route('settings.ai.providers.test', $provider))
        ->assertOk()->assertJsonPath('ok', true);

    $this->actingAs($user)->patch(route('settings.ai.providers.update', $provider), ['model' => 'new-model', 'key' => ''])
        ->assertRedirect();
    expect($provider->fresh()->model)->toBe('new-model')->and($provider->fresh()->key)->toBe('sk-b'); // key vacía conserva

    $this->actingAs($user)->delete(route('settings.ai.providers.destroy', $provider))->assertRedirect();
    expect(AiProvider::query()->find($provider->id))->toBeNull();
});

test('cannot touch another users provider', function () {
    $a = User::factory()->create();
    $bProvider = AiProvider::factory()->create();   // de otro user

    $this->actingAs($a)->delete(route('settings.ai.providers.destroy', $bProvider->id))->assertNotFound();
    $this->actingAs($a)->post(route('settings.ai.providers.test', $bProvider->id))->assertNotFound();
    $this->actingAs($a)->patch(route('settings.ai.providers.update', $bProvider->id), ['model' => 'x'])->assertNotFound();
});

test('updates a scope chain and validates provider ownership', function () {
    $user = User::factory()->create();
    [$mine] = AiProvider::factory()->count(1)->for($user)->create()->all();
    $foreign = AiProvider::factory()->create();

    $this->actingAs($user)->patch(route('settings.ai.scopes.update', 'module:gym'), ['provider_chain' => [$mine->id, $foreign->id]])
        ->assertSessionHasErrors('provider_chain');   // solo ids propios

    $this->actingAs($user)->patch(route('settings.ai.scopes.update', 'module:gym'), ['provider_chain' => [$mine->id]])
        ->assertRedirect();

    expect(UserAiScope::query()->where('user_id', $user->id)->where('scope', 'module:gym')->sole()->provider_chain)
        ->toBe([$mine->id]);
});

test('a failing probe answers ok false with the error and feeds the breaker', function () {
    $user = User::factory()->create();
    [$provider] = AiProvider::factory()->count(1)->for($user)->create()->all();

    Http::fake(['api.example.com/*' => Http::response(['error' => ['message' => 'Invalid API key']], 401)]);

    $this->actingAs($user)
        ->post(route('settings.ai.providers.test', $provider))
        ->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('provider', $provider->name)
        ->assertJsonPath('error', fn ($error) => str_contains((string) $error, 'Invalid API key'));

    expect(AiProviderHealth::query()->where('provider_id', $provider->id)->sole()->consecutive_failures)->toBe(1);
});

// laravel/ai wraps transport failures (timeouts, DNS, refused connections) in
// a ProviderConnectionException, so the probe answers the honest copy instead
// of blowing up as a 500.
test('a timed out probe answers ok false instead of a server error', function () {
    $user = User::factory()->create();
    [$provider] = AiProvider::factory()->count(1)->for($user)->create()->all();

    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 15001 ms'));

    $this->actingAs($user)
        ->post(route('settings.ai.providers.test', $provider))
        ->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'Could not connect to AI provider [pm'.$provider->id.'].');

    expect(AiProviderHealth::query()->where('provider_id', $provider->id)->sole()->consecutive_failures)->toBe(1);
});

test('prompt preview returns the composed string for a scope', function () {
    $user = User::factory()->create();
    AiProvider::factory()->for($user)->create();
    UserAiScope::factory()->for($user)->create(['scope' => 'global', 'prompt' => 'Capa global.']);

    $this->actingAs($user)->post(route('settings.ai.prompts.preview'), ['scope' => 'surface:chat'])
        ->assertOk()
        ->assertJsonPath('preview', fn ($preview) => str_contains($preview, '## Personalización global')
            && str_contains($preview, 'Capa global.'));
});

test('prompt layer validates the 8000 character limit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('settings.ai.scopes.update', 'global'), ['prompt' => str_repeat('x', 8001)])
        ->assertSessionHasErrors('prompt');

    $this->actingAs($user)->patch(route('settings.ai.scopes.update', 'global'), ['prompt' => str_repeat('x', 8000)])
        ->assertRedirect();

    expect(UserAiScope::query()->where('user_id', $user->id)->where('scope', 'global')->sole()->prompt)
        ->toBe(str_repeat('x', 8000));
});
