<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

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
 * Known divergence: the live screenshot carries `data-srcset` plus a base64
 * placeholder `src`, and the shared HtmlParser reads only src/data-src/srcset.
 * The fixture therefore uses a plain srcset. Until the parser learns
 * `data-srcset`, the live feed degrades to an empty page rather than crashing;
 * the card/link/title selectors are otherwise the real ones.
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
}
