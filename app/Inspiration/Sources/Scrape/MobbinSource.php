<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

use App\Inspiration\Concerns\UsesCredentials;
use App\Inspiration\Contracts\Source;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * Mobbin (tier 3, account-gated).
 *
 * Mobbin's library is only reachable with an authenticated session cookie, and
 * this app deliberately does not store session credentials, so the adapter is
 * never configured: isConfigured() returns false and the settings screen renders
 * it as "requiere cuenta". explore() and search() degrade to an empty page
 * *without touching the network* — scraping the public shell would only return
 * the sign-in page and burn the rate-limit budget. Its intended feed lives at
 * https://mobbin.com/browse/ios/apps, which stays unreachable without the session.
 */
final class MobbinSource implements Source
{
    use UsesCredentials;

    private const PAGE_SIZE = 24;

    public function key(): string
    {
        return 'mobbin';
    }

    public function label(): string
    {
        return 'Mobbin';
    }

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: false,
            supportsExplore: true,
            needsKey: false,
            hasMaturityLevels: false,
            maxPageSize: self::PAGE_SIZE,
            ratePerMinute: 2,
        );
    }

    /**
     * Requires an authenticated Mobbin session, which this app never stores.
     */
    public function isConfigured(): bool
    {
        return false;
    }

    public function search(string $query, int $page, SourceQuery $queryOptions): Page
    {
        return Page::fromItems([], false, null);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        return Page::fromItems([], false, null);
    }

    public function test(): bool
    {
        return false;
    }
}
