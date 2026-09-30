<?php

use App\Ai\Support\AiHealthService;
use App\Models\AiProvider;

test('three consecutive failures open the circuit', function () {
    $provider = AiProvider::factory()->create();
    $health = new AiHealthService;

    $health->recordFailure($provider, '401');
    $health->recordFailure($provider, '401');
    expect($health->isBroken($provider))->toBeFalse();

    $health->recordFailure($provider, '401');
    expect($health->isBroken($provider))->toBeTrue()
        // Carbon 3 `diffInMinutes()` is signed: $base->diffInMinutes($other) === $other - $base.
        ->and(now()->diffInMinutes($health->statusFor($provider)->broken_until))->toBeGreaterThanOrEqual(7)
        ->toBeLessThanOrEqual(8); // 2^3 = 8 min
});

test('backoff is capped at 1440 minutes and success resets the circuit', function () {
    $provider = AiProvider::factory()->create();
    $health = new AiHealthService;

    foreach (range(1, 12) as $i) {
        $health->recordFailure($provider, 'fail '.$i);
    }
    // 2^12 = 4096 > 1440, so the cap kicks in.
    expect(now()->diffInMinutes($health->statusFor($provider)->broken_until))->toBeGreaterThanOrEqual(1439)
        ->toBeLessThanOrEqual(1440);

    $health->markSuccess($provider);
    expect($health->isBroken($provider))->toBeFalse()
        ->and($health->statusFor($provider)->consecutive_failures)->toBe(0);
});

test('health rows are created per provider and cached for the request', function () {
    $provider = AiProvider::factory()->create();
    $other = AiProvider::factory()->create();
    $health = new AiHealthService;

    // No constructor arguments: resolvable from the container too.
    expect(app(AiHealthService::class))->toBeInstanceOf(AiHealthService::class);

    // Nothing tracked yet: no row is written by a read.
    expect($health->statusFor($provider))->toBeNull()
        ->and($provider->health()->exists())->toBeFalse()
        ->and($health->statusFor($other))->toBeNull();

    $health->recordFailure($provider, 'boom');

    $row = $health->statusFor($provider);

    // Repeated reads hand back the very same cached instance.
    expect($health->statusFor($provider))->toBe($row)
        ->and($health->statusFor($other))->toBeNull()
        ->and($row->consecutive_failures)->toBe(1)
        ->and($row->last_error)->toBe('boom')
        ->and($row->provider_id)->toBe($provider->getKey())
        ->and($row->user_id)->toBe($provider->user_id);

    // A later failure mutates the cached row in place: the instance a caller
    // already holds stays current, and the database agrees with it.
    $health->recordFailure($provider, 'boom 2');

    expect($row->consecutive_failures)->toBe(2)
        ->and($row->last_error)->toBe('boom 2')
        ->and($row->fresh()->consecutive_failures)->toBe(2)
        ->and($health->statusFor($provider))->toBe($row);

    $health->markSuccess($provider);

    expect($health->statusFor($provider))->toBe($row)
        ->and($row->consecutive_failures)->toBe(0)
        ->and($row->broken_until)->toBeNull()
        ->and($row->fresh()->consecutive_failures)->toBe(0);
});

test('long errors are truncated to the 255 char column', function () {
    $provider = AiProvider::factory()->create();
    $health = new AiHealthService;

    $health->recordFailure($provider, str_repeat('e', 400));

    expect(strlen((string) $health->statusFor($provider)->last_error))->toBe(255);
});
