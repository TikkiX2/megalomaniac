<?php

declare(strict_types=1);

namespace App\Inspiration;

use App\Inspiration\Dtos\SourceQuery;

/**
 * Single source of truth for the maturity token handed to every adapter.
 *
 * The concrete API parameter mapping (purity, mature_content, rating,
 * safesearch, safe_search, tags) lives in each adapter; this value object only
 * decides which abstract token — `safe` or `allowed` — a query should carry.
 * The source argument is accepted so the token stays per-source and future
 * overrides land here instead of in the manager.
 */
final class SourceMaturity
{
    public const SAFE = 'safe';

    public const ALLOWED = 'allowed';

    /**
     * Build the query options for a source given the user's maturity toggle.
     */
    public static function forSource(string $source, bool $allowed): SourceQuery
    {
        return new SourceQuery($allowed ? self::ALLOWED : self::SAFE);
    }
}
