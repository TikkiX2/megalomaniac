<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Ai\Enums\AiScope;
use App\Models\AiProvider;
use App\Models\User;
use App\Models\UserAiScope;
use Illuminate\Support\Collection;

/**
 * Resolves which providers a given (surface, module) context should try.
 *
 * Lookup order is `surface:{surface}` → (when a module is given)
 * `module:{moduleKey}` → `global` → implicit. The **first** row with a
 * non-empty `provider_chain` wins the whole chain: chains are never merged
 * across levels. When no row carries a chain, the implicit chain is every
 * enabled provider of the user ordered by `sort_order`.
 *
 * The chain is then health-filtered: `enabled = false` providers and providers
 * whose circuit breaker is open (`broken_until > now`) are dropped. If that
 * leaves nothing, the resolution goes *half-open* and probes the enabled
 * provider with the soonest `broken_until` instead of failing outright.
 * Disabled providers never enter the chain, not even half-open.
 *
 * Instances cache the `ai_scopes` rows per user, so the class is meant to be
 * resolved from the container as a singleton (bound in `AppServiceProvider`).
 */
class AiScopeResolver
{
    /** @var array<int|string, Collection<int, UserAiScope>> */
    private array $scopeRows = [];

    public function __construct(
        private readonly AiHealthService $health,
        private readonly AiPromptComposer $composer,
    ) {}

    /**
     * @param  string|null  $moduleKey  Module the surface is rendered inside
     *                                  (`gym`, `nutrition`, …).
     * @param  string|null  $sessionId  Accepted for API symmetry with
     *                                  {@see AiProviderConfigurator::wire()} and the
     *                                  executor; the resolver does not need it.
     */
    public function resolve(User $user, AiScope $surface, ?string $moduleKey = null, ?string $sessionId = null): AiResolution
    {
        $chain = $this->chainFor($user, $surface, $moduleKey);

        return new AiResolution(
            chain: $this->filterByHealth($chain),
            promptBlock: $this->composer->personalizationBlock($user, $moduleKey, $surface->value),
        );
    }

    /**
     * The configured chain (or the implicit one) before the health filter.
     *
     * @return Collection<int, AiProvider>
     */
    private function chainFor(User $user, AiScope $surface, ?string $moduleKey): Collection
    {
        $candidateScopes = [$surface->value];

        if ($moduleKey !== null) {
            $candidateScopes[] = AiScope::fromModuleKey($moduleKey)->value;
        }

        $candidateScopes[] = AiScope::Global->value;

        $rows = $this->rowsFor($user, $candidateScopes);

        foreach ($candidateScopes as $scope) {
            $row = $rows->firstWhere('scope', $scope);

            // First row with a non-empty chain wins the whole chain: no merging.
            if ($row !== null && ($row->provider_chain ?? []) !== []) {
                return $this->providersInOrder($user, $row->provider_chain);
            }
        }

        return $this->implicitProviders($user);
    }

    /**
     * Scope rows of the user, indexed by scope value and cached per instance.
     *
     * @param  array<int, string>  $scopes
     * @return Collection<int, UserAiScope>
     */
    private function rowsFor(User $user, array $scopes): Collection
    {
        $key = $user->getKey();

        if (! isset($this->scopeRows[$key])) {
            $this->scopeRows[$key] = UserAiScope::query()
                ->where('user_id', $key)
                ->get();
        }

        return $this->scopeRows[$key];
    }

    /**
     * Providers of the chain in the exact order of the stored JSON array.
     *
     * @param  array<int, int>  $chain
     * @return Collection<int, AiProvider>
     */
    private function providersInOrder(User $user, array $chain): Collection
    {
        $providers = AiProvider::query()
            ->where('user_id', $user->getKey())
            ->whereIn('id', $chain)
            ->get();

        return $providers
            ->sortBy(fn (AiProvider $provider): int => (int) array_search($provider->getKey(), $chain, true))
            ->values();
    }

    /** @return Collection<int, AiProvider> */
    private function implicitProviders(User $user): Collection
    {
        return AiProvider::query()
            ->where('user_id', $user->getKey())
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Drops disabled and broken providers, going half-open when that empties the
     * chain.
     *
     * @param  Collection<int, AiProvider>  $chain
     * @return Collection<int, AiProvider>
     */
    private function filterByHealth(Collection $chain): Collection
    {
        // Disabled providers never enter, not even half-open.
        $enabled = $chain->filter(fn (AiProvider $provider): bool => (bool) $provider->enabled)->values();

        $healthy = $enabled->reject(fn (AiProvider $provider): bool => $this->health->isBroken($provider));

        if ($healthy->isNotEmpty()) {
            return $healthy->values();
        }

        if ($enabled->isEmpty()) {
            return new Collection;
        }

        // Half-open: probe the provider whose breaker opens soonest; a null
        // `broken_until` means the probe is already due, so it sorts first.
        return $enabled
            ->sortBy(fn (AiProvider $provider): int => $this->health->statusFor($provider)?->broken_until?->getTimestamp() ?? PHP_INT_MIN)
            ->take(1)
            ->values();
    }
}
