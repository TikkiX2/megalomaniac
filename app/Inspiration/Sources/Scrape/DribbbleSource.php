<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * Dribbble shot HTML feed.
 *
 * Cards are `.shot-thumbnail` entries: an anchor wrapping the screenshot plus a
 * `.shot-thumbnail-title` caption. Explore is /shots/popular; search is
 * /search/shots?q={term}. Screenshots normally live on cdn.dribbble.com
 * (protocol-relative src/data-src/srcset), which the shared parser resolves to
 * https and follows without leaking into the page host.
 *
 * The live site is behind an AWS WAF challenge (HTTP 202 with a JS cookie
 * interstitial) for non-browser clients, so the selectors could not be
 * calibrated against live HTML; they follow the historically stable shot-grid
 * markup and the brief, and a selector drift degrades to an empty page.
 */
final class DribbbleSource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://dribbble.com';

    public function key(): string
    {
        return 'dribbble';
    }

    public function label(): string
    {
        return 'Dribbble';
    }

    protected function selectors(): array
    {
        return [
            'card' => ['.shot-thumbnail', '.dribbble-shot', 'li.shot'],
            'image' => 'img',
            'link' => 'a',
            'title' => ['.shot-thumbnail-title', '.shot-title', '.dribbble-title'],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function searchUrl(string $query): string
    {
        return self::BASE_URL.'/search/shots?q='.rawurlencode($query);
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/shots/popular';
    }
}
