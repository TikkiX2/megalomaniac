<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * Trend List HTML feed.
 *
 * Cards are `.gallery-item` blocks: an anchor wrapping the image plus a
 * `.gallery-title` caption. Explore is the home feed; search is
 * /?search={term}.
 */
final class TrendListSource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://trendlist.org';

    public function key(): string
    {
        return 'trendlist';
    }

    public function label(): string
    {
        return 'Trend List';
    }

    protected function selectors(): array
    {
        return [
            'card' => '.gallery-item',
            'image' => 'img',
            'link' => 'a',
            'title' => ['.gallery-title', '.gallery-caption'],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function searchUrl(string $query): string
    {
        return self::BASE_URL.'/?search='.rawurlencode($query);
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/';
    }
}
