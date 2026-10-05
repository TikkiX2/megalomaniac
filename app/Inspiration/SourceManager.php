<?php

declare(strict_types=1);

namespace App\Inspiration;

use App\Inspiration\Contracts\Source;
use App\Inspiration\Dtos\SettingsBag;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\Exceptions\SourceException;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Registry and dispatcher for every inspiration source.
 *
 * Sources are resolved from the `inspiration.sources` container tag on each
 * call, so tests (and later tasks) can register adapters without touching this
 * class. search()/explore() are write-through: a fresh cache hit is served
 * as-is, a miss calls the adapter and persists the payload, and an adapter
 * failure falls back to a stale row when one exists or rethrows a
 * SourceException carrying the previous cache age.
 *
 * A failure is also persisted as a short-lived cache marker so statuses() can
 * report a dead source in a later request, even though the in-memory manager
 * that observed the failure is gone.
 */
class SourceManager
{
    /**
     * Cache key prefix for the last observed adapter failure per source.
     */
    public const ERROR_CACHE_PREFIX = 'inspiration:error:';

    /**
     * How long a failure observation stays relevant (6 hours).
     */
    public const ERROR_CACHE_TTL = 21600;

    public function __construct(
        private readonly Container $app,
        private readonly InspirationSettings $settings,
        private readonly InspirationCache $cache,
    ) {}

    /**
     * @return Collection<string, Source>
     */
    public function all(): Collection
    {
        return collect($this->app->tagged('inspiration.sources'))
            ->filter(static fn (mixed $source): bool => $source instanceof Source)
            ->keyBy(static fn (Source $source): string => $source->key());
    }

    public function get(string $key): ?Source
    {
        return $this->all()->get($key);
    }

    /**
     * Sources enabled by the user whose credentials are present.
     *
     * @return Collection<string, Source>
     */
    public function activeConfigured(User $user): Collection
    {
        $bag = $this->settings->for($user);

        return $this->all()->filter(function (Source $source) use ($bag): bool {
            $this->hydrateCredentials($bag, $source);

            return $bag->isEnabled($source->key()) && $source->isConfigured();
        });
    }

    /**
     * @return array<string, array{enabled: bool, configured: bool, down: bool, cache_age_minutes: ?int, error_at: ?CarbonInterface}>
     */
    public function statuses(User $user): array
    {
        $bag = $this->settings->for($user);
        $statuses = [];

        foreach ($this->all() as $key => $source) {
            $this->hydrateCredentials($bag, $source);
            $cacheAge = $this->cacheAge($key);
            $errorAt = $this->lastErrorAt($key);

            $statuses[$key] = [
                'enabled' => $bag->isEnabled($key),
                'configured' => $source->isConfigured(),
                'down' => $errorAt !== null && $cacheAge === null,
                'cache_age_minutes' => $cacheAge,
                'error_at' => $errorAt,
            ];
        }

        return $statuses;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, has_more: bool, next_page: ?int, from_cache: bool, age_minutes: ?int}
     */
    public function search(User $user, string $key, string $query, int $page = 1): array
    {
        $source = $this->resolve($key);
        $bag = $this->settings->for($user);
        $this->hydrateCredentials($bag, $source);

        $options = new SourceQuery(
            maturity: $bag->maturity ? 'allowed' : 'safe',
            extra: ['page' => $page],
        );

        return $this->fetch(
            $user,
            $key,
            'search',
            InspirationCache::queryHash($query, $options),
            (int) config('inspiration.ttl.search', 1800),
            fn (): array => $source->search($query, $page, $options)->toArray(),
        );
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, has_more: bool, next_page: ?int, from_cache: bool, age_minutes: ?int}
     */
    public function explore(User $user, string $key, int $page = 1): array
    {
        $source = $this->resolve($key);
        $bag = $this->settings->for($user);
        $this->hydrateCredentials($bag, $source);

        $options = new SourceQuery(
            maturity: $bag->maturity ? 'allowed' : 'safe',
            extra: ['page' => $page],
        );

        return $this->fetch(
            $user,
            $key,
            'explore',
            InspirationCache::queryHash('', $options),
            (int) config('inspiration.ttl.explore', 3600),
            fn (): array => $source->explore($page, $options)->toArray(),
        );
    }

    /**
     * Mashup search: one page per active source, isolated per source.
     *
     * @return array<string, array{items: array<int, array<string, mixed>>, has_more: bool, next_page: ?int, from_cache: bool, age_minutes: ?int}>
     */
    public function searchAll(User $user, string $query, int $perSource = 12): array
    {
        $results = [];

        foreach ($this->activeConfigured($user) as $key => $source) {
            try {
                $page = $this->search($user, $key, $query, 1);
                $page['items'] = array_slice($page['items'], 0, $perSource);
                $results[$key] = $page;
            } catch (SourceException $exception) {
                $this->recordError($key);

                $results[$key] = [
                    'items' => [],
                    'has_more' => false,
                    'next_page' => null,
                    'from_cache' => false,
                    'age_minutes' => $exception->previousCacheAge,
                ];
            }
        }

        return $results;
    }

    /**
     * Consume one rate-limit slot for the source, honouring its per-minute
     * capability (defaults to 30 when unset).
     */
    public function rateLimit(User $user, string $key): bool
    {
        $source = $this->get($key);
        $perMinute = $source?->capabilities()->ratePerMinute ?? 30;

        return RateLimiter::attempt('inspiration:'.$key, $perMinute, static fn (): bool => true);
    }

    private function resolve(string $key): Source
    {
        $source = $this->get($key);

        if ($source === null) {
            throw new SourceException("Unknown inspiration source [{$key}].");
        }

        return $source;
    }

    /**
     * @param  Closure(): array<string, mixed>  $adapter
     * @return array{items: array<int, array<string, mixed>>, has_more: bool, next_page: ?int, from_cache: bool, age_minutes: ?int}
     */
    private function fetch(User $user, string $key, string $kind, string $queryHash, int $ttl, Closure $adapter): array
    {
        try {
            if (! $this->rateLimit($user, $key)) {
                throw new SourceException('inspiration: rate limited (seguí explorando otros orígenes)');
            }

            $result = $this->cache->remember($key, $kind, $queryHash, $ttl, $adapter);
            $this->clearError($key);

            return $this->present($result['payload'], $result['from_cache'], $result['age_minutes']);
        } catch (SourceException $exception) {
            $this->recordError($key);

            $stale = InspirationCache::latestRow($key, $kind, $queryHash);

            if ($stale !== null) {
                return $this->present($stale->payload, true, InspirationCache::ageOf($stale));
            }

            throw new SourceException($exception->getMessage(), null, $exception->getCode(), $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{items: array<int, array<string, mixed>>, has_more: bool, next_page: ?int, from_cache: bool, age_minutes: ?int}
     */
    private function present(array $payload, bool $fromCache, ?int $ageMinutes): array
    {
        return [
            'items' => array_values($payload['items'] ?? []),
            'has_more' => (bool) ($payload['has_more'] ?? false),
            'next_page' => $payload['next_page'] ?? null,
            'from_cache' => $fromCache,
            'age_minutes' => $ageMinutes,
        ];
    }

    private function cacheAge(string $key): ?int
    {
        $entry = InspirationCache::latestForSource($key);

        return $entry === null ? null : InspirationCache::ageOf($entry);
    }

    private function recordError(string $key): void
    {
        Cache::put(self::ERROR_CACHE_PREFIX.$key, now(), self::ERROR_CACHE_TTL);
    }

    private function clearError(string $key): void
    {
        Cache::forget(self::ERROR_CACHE_PREFIX.$key);
    }

    private function lastErrorAt(string $key): ?CarbonInterface
    {
        $value = Cache::get(self::ERROR_CACHE_PREFIX.$key);

        if ($value === null) {
            return null;
        }

        return $value instanceof CarbonInterface ? $value : Carbon::parse($value);
    }

    private function hydrateCredentials(SettingsBag $bag, Source $source): void
    {
        $key = $source->key();

        $source->setCredentials(array_merge(
            $bag->keys[$key] ?? [],
            ['user_agent' => $bag->zerochanUa],
        ));
    }
}
