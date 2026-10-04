<?php

declare(strict_types=1);

namespace App\Inspiration;

use App\Inspiration\Dtos\SourceQuery;
use App\Models\InspirationCacheEntry;
use Closure;

class InspirationCache
{
    /**
     * Return the cached payload for the key, fetching and writing it through
     * when missing or expired.
     *
     * @param  Closure(): array<string, mixed>  $fetch
     * @return array{payload: array<string, mixed>, from_cache: bool, age_minutes: ?int}
     */
    public static function remember(string $source, string $kind, string $queryHash, int $ttlSeconds, Closure $fetch): array
    {
        $entry = self::latestRow($source, $kind, $queryHash);

        if ($entry !== null && $entry->expires_at->isFuture()) {
            return [
                'payload' => $entry->payload,
                'from_cache' => true,
                'age_minutes' => self::ageOf($entry),
            ];
        }

        $payload = $fetch();

        InspirationCacheEntry::updateOrCreate(
            [
                'source' => $source,
                'kind' => $kind,
                'query_hash' => $queryHash,
            ],
            [
                'payload' => $payload,
                'fetched_at' => now(),
                'expires_at' => now()->addSeconds($ttlSeconds),
            ],
        );

        return [
            'payload' => $payload,
            'from_cache' => false,
            'age_minutes' => null,
        ];
    }

    /**
     * Age in whole minutes of the stored entry, or null when absent.
     */
    public static function ageMinutes(string $source, string $kind, string $queryHash): ?int
    {
        $entry = self::latestRow($source, $kind, $queryHash);

        return $entry === null ? null : self::ageOf($entry);
    }

    /**
     * Stored row for an exact cache key, regardless of expiry. Callers that
     * fall back to stale content use this so column knowledge stays here.
     */
    public static function latestRow(string $source, string $kind, string $queryHash): ?InspirationCacheEntry
    {
        return InspirationCacheEntry::query()
            ->where('source', $source)
            ->where('kind', $kind)
            ->where('query_hash', $queryHash)
            ->latest('fetched_at')
            ->first();
    }

    /**
     * Newest stored row for a source across every kind and query; used by the
     * status chips, which do not know which query produced the payload.
     */
    public static function latestForSource(string $source): ?InspirationCacheEntry
    {
        return InspirationCacheEntry::query()
            ->where('source', $source)
            ->latest('fetched_at')
            ->first();
    }

    /**
     * Age in whole minutes of a stored row.
     */
    public static function ageOf(InspirationCacheEntry $entry): int
    {
        return abs((int) $entry->fetched_at->diffInMinutes(now()));
    }

    /**
     * Stable hash of the query plus its options; maturity changes the key so
     * safe and unrestricted searches never share a cache entry.
     */
    public static function queryHash(string $query, SourceQuery $options): string
    {
        return md5($query.'|'.$options->maturity.'|'.json_encode($options->extra));
    }
}
