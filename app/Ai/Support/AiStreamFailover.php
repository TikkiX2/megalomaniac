<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Models\AiProvider;
use Closure;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Throwable;

/**
 * Decides whether a failed stream may be rebuilt on the next provider.
 *
 * laravel/ai streams lazily: the provider error only surfaces while
 * `ChatController::streamResponse()` iterates the response, so the failover
 * decision belongs here rather than at call time. A stream is only retried
 * while it is still invisible to the consumer — once
 * {@see StreamableAgentResponse::hasYielded()} is true the browser already
 * holds a partial answer and rebuilding would duplicate it, so the original
 * error surfaces untouched.
 *
 * Every decision is idempotent from the caller's point of view: `retry()`
 * returns `null` when it declines, and the reason is readable afterwards via
 * {@see self::lastError()} (chain exhausted) or {@see self::current()} /
 * {@see self::usedFallback()} (fell through to a backup).
 */
class AiStreamFailover
{
    /** Provider failures seen so far, keyed by provider name. */
    private array $errorsByProvider = [];

    /** Ids of the providers already burned through, in chain order. */
    private array $triedProviderIds = [];

    private ?AiAllProvidersFailedException $lastError = null;

    private bool $usedFallback = false;

    /**
     * @param  AiProvider  $current  The provider the failed attempt was built on.
     * @param  AiResolution  $resolution  The ordered, health-filtered chain. Its
     *                                    order is authoritative and is never re-sorted.
     * @param  AiHealthService  $health  Must be the container singleton, so the
     *                                   breaker state matches the one the resolver
     *                                   used to filter the chain.
     * @param  Closure(array<int, int>): StreamableAgentResponse  $rebuild  Re-runs
     *                                                                      `ChatService::streamTurn()` skipping the
     *                                                                      provider ids it is handed. A throw from here
     *                                                                      is an application bug and is left to propagate.
     */
    public function __construct(
        private AiProvider $current,
        private readonly AiResolution $resolution,
        private readonly AiHealthService $health,
        private readonly AiProviderErrors $errors,
        private readonly Closure $rebuild,
    ) {}

    /**
     * Decide what to do about a stream that failed during iteration.
     *
     * @return StreamableAgentResponse|null The rebuilt stream, or null when the
     *                                      caller should surface its own error.
     *
     * @throws AiAllProvidersFailedException Never thrown; the chain-exhausted case
     *                                       is exposed through {@see self::lastError()}
     *                                       because the caller already has the
     *                                       original error in hand and owns the copy.
     */
    public function retry(Throwable $e, StreamableAgentResponse $failed): ?StreamableAgentResponse
    {
        // Content already reached the consumer: never rebuild, or the browser
        // renders the backup's answer on top of the partial one it already has.
        if ($failed->hasYielded()) {
            return null;
        }

        $this->health->recordFailure($this->current, $e->getMessage());

        // Not this provider's fault to hand off (404, 418, app bugs): surface as-is.
        if (! $this->errors->isFallbackable($e)) {
            return null;
        }

        $this->errorsByProvider[$this->current->name] = $e->getMessage();
        $this->triedProviderIds[] = $this->current->getKey();

        $next = $this->nextProvider();

        if ($next === null) {
            $this->lastError = new AiAllProvidersFailedException($this->errorsByProvider, $e);

            return null;
        }

        $this->current = $next;
        $this->usedFallback = true;

        return ($this->rebuild)($this->triedProviderIds);
    }

    /** Set once the chain runs out, so the caller can build an honest message. */
    public function lastError(): ?AiAllProvidersFailedException
    {
        return $this->lastError;
    }

    /** True when at least one attempt moved on to a backup provider. */
    public function usedFallback(): bool
    {
        return $this->usedFallback;
    }

    /** The provider the next attempt runs on: the failed one, or its backup. */
    public function current(): AiProvider
    {
        return $this->current;
    }

    /**
     * The first provider after the current one in the chain.
     *
     * Walks the resolution's own ordering (primary then backups) instead of
     * re-sorting by `sort_order`, so a scope that pins an explicit chain is
     * honoured. A provider the chain no longer lists — and one that appears
     * before the current attempt — yields null, ending the walk.
     */
    private function nextProvider(): ?AiProvider
    {
        $currentId = $this->current->getKey();
        $found = false;

        foreach ($this->resolution->chain as $provider) {
            if ($found) {
                return $provider;
            }

            if ($provider->getKey() === $currentId) {
                $found = true;
            }
        }

        return null;
    }
}
