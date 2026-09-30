<?php

use App\Ai\Enums\AiScope;
use App\Ai\Support\AiHealthService;
use App\Ai\Support\AiScopeResolver;
use App\Models\AiProvider;
use App\Models\User;
use App\Models\UserAiScope;

test('first non-empty chain wins and never merges', function () {
    $user = User::factory()->create();
    [$a, $b, $c] = AiProvider::factory()->count(3)->for($user)->create()->all();
    UserAiScope::factory()->for($user)->create(['scope' => 'global', 'provider_chain' => [$c->id]]);
    UserAiScope::factory()->for($user)->create(['scope' => 'module:gym', 'provider_chain' => [$a->id, $b->id]]);

    $resolver = app(AiScopeResolver::class);
    $resolution = $resolver->resolve($user, AiScope::SurfaceChat, 'gym');

    expect($resolution->chain->pluck('id')->all())->toBe([$a->id, $b->id]); // no $c
    expect($resolver->resolve($user, AiScope::SurfaceChat, 'finance')->chain->pluck('id')->all())->toBe([$c->id]);
});

test('surface beats module even when both are set', function () {
    $user = User::factory()->create();
    [$a, $b] = AiProvider::factory()->count(2)->for($user)->create()->all();
    UserAiScope::factory()->for($user)->create(['scope' => 'surface:chat', 'provider_chain' => [$b->id]]);
    UserAiScope::factory()->for($user)->create(['scope' => 'module:gym', 'provider_chain' => [$a->id]]);

    $resolution = app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceChat, 'gym');

    expect($resolution->chain->pluck('id')->all())->toBe([$b->id]);
});

test('implicit chain orders enabled providers by sort_order and skips disabled', function () {
    $user = User::factory()->create();
    $second = AiProvider::factory()->for($user)->create(['sort_order' => 2, 'enabled' => true]);
    $first = AiProvider::factory()->for($user)->create(['sort_order' => 1, 'enabled' => true]);
    AiProvider::factory()->for($user)->create(['sort_order' => 0, 'enabled' => false]);

    $resolution = app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceAgents);

    expect($resolution->chain->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('falls back to the soonest probe when every provider is broken', function () {
    $user = User::factory()->create();
    $far = AiProvider::factory()->for($user)->create(['sort_order' => 1]);
    $near = AiProvider::factory()->for($user)->create(['sort_order' => 2]);
    $health = new AiHealthService;
    foreach (range(1, 4) as $i) {
        $health->recordFailure($far, 'x');
    }   // broken ~16 min
    foreach (range(1, 3) as $i) {
        $health->recordFailure($near, 'x');
    }  // broken 8 min

    $resolution = app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceChat);

    expect($resolution->primary()->id)->toBe($near->id);
});

test('a disabled provider never enters the chain, not even half-open', function () {
    $user = User::factory()->create();
    $disabled = AiProvider::factory()->for($user)->create(['sort_order' => 1, 'enabled' => false]);
    $broken = AiProvider::factory()->for($user)->create(['sort_order' => 2]);
    UserAiScope::factory()->for($user)->create([
        'scope' => 'global',
        'provider_chain' => [$disabled->id, $broken->id],
    ]);

    $health = new AiHealthService;
    foreach (range(1, 3) as $i) {
        $health->recordFailure($broken, 'x');
    }

    $resolution = app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceChat);

    expect($resolution->chain->pluck('id')->all())->toBe([$broken->id])
        ->and($resolution->chain->pluck('id')->all())->not->toContain($disabled->id);
});

test('an empty enabled set resolves to an empty chain instead of probing', function () {
    $user = User::factory()->create();
    $disabled = AiProvider::factory()->for($user)->create(['enabled' => false]);
    UserAiScope::factory()->for($user)->create([
        'scope' => 'global',
        'provider_chain' => [$disabled->id],
    ]);

    $resolution = app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceChat);

    expect($resolution->isEmpty())->toBeTrue()
        ->and($resolution->primary())->toBeNull();
});

test('attaches the composed prompt block to the resolution', function () {
    $user = User::factory()->create();
    AiProvider::factory()->for($user)->create();
    UserAiScope::factory()->for($user)->create(['scope' => 'global', 'prompt' => 'Global layer.']);

    $resolution = app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceChat);

    expect($resolution->promptBlock)->toContain('## Personalización global');
});
