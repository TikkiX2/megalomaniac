<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * Savee HTML feed.
 *
 * Cards are `.post` entries: an anchor wrapping the image plus a `.post-title`
 * heading. Explore is the home feed; search is /search/?q={term}.
 */
final class SaveeSource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://savee.it';

    public function key(): string
    {
        return 'savee';
    }

    public function label(): string
    {
        return 'Savee';
    }

    protected function selectors(): array
    {
        return [
            'card' => '.post',
            'image' => 'img',
            'link' => 'a',
            'title' => ['.post-title', '.post__title'],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function searchUrl(string $query): string
    {
        return self::BASE_URL.'/search/?q='.rawurlencode($query);
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/';
    }
}
