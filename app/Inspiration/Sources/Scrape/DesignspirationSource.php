<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * Designspiration HTML feed.
 *
 * Cards are `.pin` tiles: an anchor wrapping the image plus a `.pin-title`
 * caption. Explore lives at /explore/ and search at /search/{term}/.
 */
final class DesignspirationSource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://www.designspiration.net';

    public function key(): string
    {
        return 'designspiration';
    }

    public function label(): string
    {
        return 'Designspiration';
    }

    protected function selectors(): array
    {
        return [
            'card' => '.pin',
            'image' => 'img',
            'link' => 'a',
            'title' => ['.pin-title', '.title'],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function searchUrl(string $query): string
    {
        return self::BASE_URL.'/search/'.rawurlencode($query).'/';
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/explore/';
    }
}
