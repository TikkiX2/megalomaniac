<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Ai\Enums\AiScope;
use App\Models\AiProvider;
use App\Models\User;

/**
 * Runs one eager `surface:insights` request for the non-chat callers
 * (`InsightService`, `AiInsightController`, `AiFitnessController`).
 *
 * It is the single seam where the chain is resolved, executed and where an
 * exhausted chain stops being an exception: per the design spec (§4.3) every
 * caller turns `AiAllProvidersFailedException` into an honest message for the
 * user plus a single `report($e)`, so the endpoints answer 200 instead of 500.
 */
final class AiInsightRunner
{
    public function __construct(
        private readonly AiScopeResolver $resolver,
        private readonly AiRequestExecutor $executor,
    ) {}

    /**
     * @param  callable(string $providerKey, string $model, ?string $promptBlock): mixed  $attempt
     */
    public function run(User $user, string $moduleKey, callable $attempt): AiInsightOutcome
    {
        $resolution = $this->resolver->resolve($user, AiScope::SurfaceInsights, $moduleKey);

        // Genuinely nothing configured: the caller keeps its historical copy.
        if ($resolution->isEmpty()) {
            return AiInsightOutcome::unconfigured();
        }

        try {
            return AiInsightOutcome::resolved($this->executor->execute(
                $user,
                $resolution,
                fn (string $key, string $model, AiProvider $provider): mixed => $attempt(
                    $key,
                    $model ?: $provider->model,
                    $resolution->promptBlock,
                ),
            ));
        } catch (AiAllProvidersFailedException $e) {
            // Reported once, here: the caller only sees the honest copy.
            report($e);

            return AiInsightOutcome::exhausted();
        }
    }
}
