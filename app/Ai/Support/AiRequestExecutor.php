<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Models\AiProvider;
use App\Models\User;
use RuntimeException;
use Throwable;

/**
 * Runs an eager (non-streaming) AI request across the resolved provider chain.
 *
 * Each attempt wires its provider into the runtime config, hands the caller's
 * closure the provider key + model, and records the outcome in the circuit
 * breaker. A fallbackable failure moves to the next provider; anything else
 * (404, 418, application bugs) is rethrown untouched so the caller keeps its
 * original error copy. When the chain runs out, every provider's message is
 * thrown together as {@see AiAllProvidersFailedException}.
 *
 * Both dependencies are optional so `new AiRequestExecutor` and
 * `app(AiRequestExecutor::class)` both work. The health service is never
 * constructed by hand: it is pulled from the container so its per-request
 * health-row cache stays coherent with the resolver that filtered the chain.
 */
class AiRequestExecutor
{
    public function __construct(
        private ?AiHealthService $health = null,
        private readonly AiProviderErrors $errors = new AiProviderErrors,
    ) {}

    /** The shared health service, resolved lazily from the container. */
    private function health(): AiHealthService
    {
        return $this->health ??= app(AiHealthService::class);
    }

    /**
     * @param  User  $user  Kept for API symmetry with `AiScopeResolver` and
     *                      `AiProviderConfigurator::wire()`; the chain already
     *                      belongs to this user, so it is not read here.
     * @param  callable(string $providerKey, string $model, AiProvider $provider): mixed  $attempt
     *
     * @throws AiAllProvidersFailedException When the chain is empty or exhausted.
     * @throws Throwable The original error when it is not fallbackable.
     */
    public function execute(
        User $user,
        AiResolution $resolution,
        callable $attempt,
        ?string $sessionId = null,
    ): mixed {
        $chain = $resolution->chain;

        if ($chain->isEmpty()) {
            throw new AiAllProvidersFailedException(
                ['' => 'Sin proveedores disponibles.'],
                new RuntimeException('empty chain'),
            );
        }

        /** @var array<string, string> $errorsByProvider */
        $errorsByProvider = [];
        $last = count($chain);

        foreach ($chain->values() as $index => $provider) {
            $providerKey = AiProviderConfigurator::wire($provider, $sessionId);

            try {
                $result = $attempt($providerKey, $this->modelFor($provider), $provider);
            } catch (Throwable $e) {
                $this->health()->recordFailure($provider, $e->getMessage());
                $errorsByProvider[$provider->name] = $e->getMessage();

                if (! $this->errors->isFallbackable($e)) {
                    throw $e;
                }

                if ($index === $last - 1) {
                    throw new AiAllProvidersFailedException($errorsByProvider, $e);
                }

                continue;
            }

            // Half-open probes land here too: a success closes the circuit.
            $this->health()->markSuccess($provider);

            return $result;
        }

        // Unreachable: the final iteration always returns or throws.
        throw new AiAllProvidersFailedException(
            $errorsByProvider !== [] ? $errorsByProvider : ['' => 'Sin proveedores disponibles.'],
            new RuntimeException('chain exhausted without a result'),
        );
    }

    /**
     * The provider's own model, falling back to the same default the legacy
     * resolver used (`ai_providers.model` is NOT NULL, so this is belt-and-braces).
     */
    private function modelFor(AiProvider $provider): string
    {
        return (string) ($provider->model ?: 'gpt-4o-mini');
    }
}
