<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * Newgrounds art portal HTML feed.
 *
 * Cards are `.item-art` tiles wrapping a `/art/view/{artist}/{slug}` anchor
 * plus a `.item-title` caption. Explore is the newest-first browse feed; search
 * is the on-site post search scoped to the art category.
 *
 * Known divergence (verified live 2026-10-05): Newgrounds fronts the site with
 * an "NG Guard" interstitial that answers non-browser clients with HTTP 403, so
 * the real markup cannot be inspected from this environment. The selectors
 * therefore target the plausible server-rendered card shape and rely on the
 * shared degradation contract (SourceException → empty page, no crash).
 * Reactivating it after a redesign is a selector update, not a rewrite.
 */
final class NewgroundsSource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://www.newgrounds.com';

    public function key(): string
    {
        return 'newgrounds';
    }

    public function label(): string
    {
        return 'Newgrounds';
    }

    protected function selectors(): array
    {
        return [
            'card' => ['.item-art', '.art-item', '.browse-art-item'],
            'image' => 'img',
            'link' => 'a',
            'title' => ['.item-title', '.art-title', 'h3', 'h2'],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function searchUrl(string $query): string
    {
        return self::BASE_URL.'/search/conduct/post?query='.rawurlencode($query).'&category=art';
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/art/browse?sort=date';
    }
}
