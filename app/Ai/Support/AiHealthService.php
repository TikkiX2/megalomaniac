<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Models\AiProvider;
use App\Models\AiProviderHealth;
use Illuminate\Support\Str;

/**
 * Per-provider circuit breaker.
 *
 * A provider is only marked as down after {@see self::FAILURE_THRESHOLD}
 * consecutive failures; from there `broken_until` is pushed forward by an
 * exponential backoff of `2 ^ consecutive_failures` minutes, capped at
 * {@see self::MAX_BACKOFF_MINUTES} (one day). A single success closes the
 * circuit again.
 *
 * Rows are lazily created per `[user_id, provider_id]` and kept in an
 * in-memory cache for the lifetime of this instance so that `isBroken()`,
 * `recordFailure()` and `markSuccess()` never issue duplicate queries inside a
 * single request.
 */
class AiHealthService
{
    /** Consecutive failures required before a provider leaves the circuit. */
    public const FAILURE_THRESHOLD = 3;

    /** Hard ceiling for the exponential backoff, in minutes. */
    public const MAX_BACKOFF_MINUTES = 1440;

    /** Width of the `last_error` column; longer messages are truncated. */
    public const MAX_ERROR_LENGTH = 255;

    /** Marker `Str::limit()` appends to a truncated value. */
    private const ELLIPSIS_LENGTH = 3;

    /**
     * Health rows already loaded during this request, keyed by provider id.
     *
     * @var array<int, AiProviderHealth|null>
     */
    private array $cache = [];

    public function markSuccess(AiProvider $provider): void
    {
        $row = $this->rowFor($provider);

        $row->consecutive_failures = 0;
        $row->broken_until = null;
        $row->last_success_at = now();
        $row->save();
    }

    public function recordFailure(AiProvider $provider, string $error): void
    {
        $row = $this->rowFor($provider);

        $row->consecutive_failures++;
        $row->last_failure_at = now();
        // `Str::limit()` appends its '...' marker on top of the limit, so the
        // limit is reduced by its width to stay inside the 255 char column.
        $row->last_error = Str::limit($error, self::MAX_ERROR_LENGTH - self::ELLIPSIS_LENGTH);

        if ($row->consecutive_failures >= self::FAILURE_THRESHOLD) {
            $row->broken_until = now()->addMinutes(min(2 ** $row->consecutive_failures, self::MAX_BACKOFF_MINUTES));
        }

        $row->save();
    }

    public function isBroken(AiProvider $provider): bool
    {
        $row = $this->statusFor($provider);

        return $row !== null
            && $row->broken_until !== null
            && $row->broken_until->isFuture();
    }

    public function statusFor(AiProvider $provider): ?AiProviderHealth
    {
        $key = $provider->getKey();

        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = AiProviderHealth::query()
                ->where('user_id', $provider->user_id)
                ->where('provider_id', $key)
                ->first();
        }

        return $this->cache[$key];
    }

    /**
     * The health row for the provider, created on first write.
     *
     * Reuses the cached instance when there is one so mutations made by
     * `recordFailure()`/`markSuccess()` are visible to callers already holding
     * the row returned by `statusFor()`.
     */
    private function rowFor(AiProvider $provider): AiProviderHealth
    {
        $row = $this->statusFor($provider);

        if ($row !== null) {
            return $row;
        }

        return $this->cache[$provider->getKey()] = AiProviderHealth::query()->firstOrCreate([
            'user_id' => $provider->user_id,
            'provider_id' => $provider->getKey(),
        ]);
    }
}
