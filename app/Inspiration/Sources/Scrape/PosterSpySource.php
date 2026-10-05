<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * PosterSpy HTML feed.
 *
 * Cards are `<article>` entries: an anchor wrapping the image plus an
 * `.entry-title` heading. Explore is the home feed; search is /?s={term}.
 */
final class PosterSpySource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://posterspy.com';

    public function key(): string
    {
        return 'posterspy';
    }

    public function label(): string
    {
        return 'PosterSpy';
    }

    protected function selectors(): array
    {
        return [
            'card' => 'article',
            'image' => 'img',
            'link' => 'a',
            'title' => ['.entry-title', 'h2'],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function searchUrl(string $query): string
    {
        return self::BASE_URL.'/?s='.rawurlencode($query);
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/';
    }
}
