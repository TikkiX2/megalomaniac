<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\Exceptions\SourceException;

/**
 * Awwwards website-showcase HTML feed.
 *
 * Selectors were calibrated against the live /websites/ grid (curl + shared
 * HtmlParser): cards are `li.js-collectable` (.card-site fallback), the anchor
 * `.figure-rollover__link` points at /sites/{slug}, and the second
 * `.figure-rollover__row` inside the hover panel carries the project name.
 * Explore is /websites/; search is /search/?q={term} (the brief's contract; the
 * live search path returned 404 during calibration, so it is frozen here and
 * locked by the URL test).
 *
 * The live screenshot pairs a base64 placeholder `src` with the real URL in
 * `data-srcset`; the shared HtmlParser skips the placeholder and reads the
 * data-srcset candidate, so the fixture mirrors that exact shape and the live
 * feed parses. `test()` probes this feed instead of the 404 search page.
 */
final class AwwwardsSource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://www.awwwards.com';

    public function key(): string
    {
        return 'awwwards';
    }

    public function label(): string
    {
        return 'Awwwards';
    }

    protected function selectors(): array
    {
        return [
            'card' => ['.js-collectable', '.card-site', '.site-img'],
            'image' => 'img',
            'link' => 'a',
            'title' => [
                '.figure-rollover__hover .figure-rollover__left .figure-rollover__row:last-child',
                '.card-site__title',
                '.site-img__title',
            ],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function searchUrl(string $query): string
    {
        return self::BASE_URL.'/search/?q='.rawurlencode($query);
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/websites/';
    }

    /**
     * Probe the reachable feed for connectivity.
     *
     * The base `test()` would hit `/search/?q=portrait`, which the live site
     * answers with 404, so a healthy Awwwards source would report itself down.
     * The explore feed is the surface we actually serve, so probe that instead.
     */
    public function test(): bool
    {
        try {
            $this->explore(1, new SourceQuery);

            return true;
        } catch (SourceException) {
            return false;
        }
    }
}
