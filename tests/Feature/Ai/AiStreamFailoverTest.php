<?php

use App\Ai\Support\AiAllProvidersFailedException;
use App\Ai\Support\AiHealthService;
use App\Ai\Support\AiProviderErrors;
use App\Ai\Support\AiResolution;
use App\Ai\Support\AiStreamFailover;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;

/**
 * A `StreamableAgentResponse` whose provider fails mid-iteration, consumed the
 * way `ChatController::streamResponse()` consumes it.
 *
 * laravel/ai is lazy: the provider error only surfaces while iterating, and
 * `hasYielded()` flips inside `getIterator()` right before each event is
 * handed over. So a generator that throws immediately leaves the flag `false`
 * (failure before the first chunk) and one that yields a delta before throwing
 * leaves it `true` (failure after the consumer already has content). Both are
 * produced by the real class, no reflection needed.
 *
 * @param  bool  $yielded  Whether the provider delivers one delta before failing.
 */
function streamFailingAtChunk(bool $yielded): StreamableAgentResponse
{
    $response = new StreamableAgentResponse('invocation-1', function () use ($yielded) {
        if ($yielded) {
            yield new TextDelta('evt-1', 'msg-1', 'hola', 1);
        }

        throw new RequestException(new Response(Http::psr7Response([], 502)));
    });

    try {
        foreach ($response as $event) {
            unset($event);
        }
    } catch (Throwable) {
        // The controller catches it here and asks the failover what to do.
    }

    return $response;
}

/** A healthy stream: the response the rebuild closure hands back. */
function streamRebuilt(): StreamableAgentResponse
{
    return new StreamableAgentResponse('invocation-2', fn () => yield new TextDelta('evt-2', 'msg-2', 'ok', 1));
}

test('retries before any event reached the consumer', function () {
    $user = User::factory()->create();
    [$a, $b] = AiProvider::factory()->count(2)->for($user)->create()->all();
    $resolution = new AiResolution(collect([$a, $b]), null);
    $rebuildTried = null;

    $failover = new AiStreamFailover(
        $a,
        $resolution,
        app(AiHealthService::class),
        app(AiProviderErrors::class),
        function (array $tried) use (&$rebuildTried) {
            $rebuildTried = $tried;

            return streamRebuilt();
        },
    );

    // Sanity check on the seam: no event was handed over before the failure.
    $failed = streamFailingAtChunk(false);
    expect($failed->hasYielded())->toBeFalse();

    $retry = $failover->retry(new RequestException(new Response(Http::psr7Response([], 429))), $failed);

    expect($retry)->toBeInstanceOf(StreamableAgentResponse::class)
        ->and($retry)->not->toBe($failed)
        ->and($rebuildTried)->toBe([$a->id])
        ->and($failover->usedFallback())->toBeTrue()
        ->and($failover->lastError())->toBeNull()
        // The chain is walked in order, never re-sorted.
        ->and($failover->current()->is($b))->toBeTrue()
        // Only the provider that failed feeds the breaker; the backup has not
        // answered yet, so it has no health row at all.
        ->and(app(AiHealthService::class)->statusFor($a)->consecutive_failures)->toBe(1)
        ->and(app(AiHealthService::class)->statusFor($b))->toBeNull();
});

test('mid-stream failure does not retry and surfaces the original error', function () {
    $user = User::factory()->create();
    [$a, $b] = AiProvider::factory()->count(2)->for($user)->create()->all();
    $resolution = new AiResolution(collect([$a, $b]), null);
    $rebuilt = false;

    $failover = new AiStreamFailover(
        $a,
        $resolution,
        app(AiHealthService::class),
        app(AiProviderErrors::class),
        function () use (&$rebuilt) {
            $rebuilt = true;

            return streamRebuilt();
        },
    );

    // The consumer already received 'hola', so retrying would duplicate it.
    $failed = streamFailingAtChunk(true);
    expect($failed->hasYielded())->toBeTrue();

    $error = new RequestException(new Response(Http::psr7Response([], 502)));

    expect($failover->retry($error, $failed))->toBeNull()
        ->and($failover->usedFallback())->toBeFalse()
        ->and($failover->lastError())->toBeNull()
        ->and($rebuilt)->toBeFalse()
        // Nothing changed, not even the current provider.
        ->and($failover->current()->is($a))->toBeTrue();
});

test('exhausted chain records lastError with per-provider detail', function () {
    $user = User::factory()->create();
    [$a] = AiProvider::factory()->count(1)->for($user)->create()->all();
    $resolution = new AiResolution(collect([$a]), null);
    $rebuilt = false;

    $failover = new AiStreamFailover(
        $a,
        $resolution,
        app(AiHealthService::class),
        app(AiProviderErrors::class),
        function () use (&$rebuilt) {
            $rebuilt = true;

            return streamRebuilt();
        },
    );

    $error = new RequestException(new Response(Http::psr7Response([], 401)));

    expect($failover->retry($error, streamFailingAtChunk(false)))->toBeNull()
        ->and($failover->usedFallback())->toBeFalse()
        ->and($rebuilt)->toBeFalse();

    $last = $failover->lastError();

    expect($last)->toBeInstanceOf(AiAllProvidersFailedException::class)
        ->and($last->getPrevious())->toBe($error)
        ->and($last->lastException())->toBe($error)
        ->and($last->errors())->toBe([$a->name => $error->getMessage()])
        // The copy the controller maps is the original message, unchanged.
        ->and($last->getMessage())->toBe($error->getMessage())
        ->and(app(AiHealthService::class)->statusFor($a)->consecutive_failures)->toBe(1);
});

test('non-fallbackable errors surface as-is without consuming the chain', function () {
    $user = User::factory()->create();
    [$a, $b] = AiProvider::factory()->count(2)->for($user)->create()->all();
    $resolution = new AiResolution(collect([$a, $b]), null);
    $rebuilt = false;

    $failover = new AiStreamFailover(
        $a,
        $resolution,
        app(AiHealthService::class),
        app(AiProviderErrors::class),
        function () use (&$rebuilt) {
            $rebuilt = true;

            return streamRebuilt();
        },
    );

    // A 404 on a wrong endpoint/model is not this provider's fault to hand off.
    $error = new RequestException(new Response(Http::psr7Response([], 404)));

    expect($failover->retry($error, streamFailingAtChunk(false)))->toBeNull()
        ->and($failover->usedFallback())->toBeFalse()
        ->and($failover->lastError())->toBeNull()
        ->and($rebuilt)->toBeFalse()
        ->and($failover->current()->is($a))->toBeTrue()
        // The chain is intact, so the caller can report it untouched.
        ->and($resolution->chain)->toHaveCount(2);
});
