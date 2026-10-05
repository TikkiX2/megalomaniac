<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * Dark Mode Design HTML feed.
 *
 * Cards are `.site` tiles: an anchor wrapping the dark-mode screenshot plus a
 * `.site__name` caption. Explore is the home feed. The site has no search box,
 * so supportsSearch() is false and search() degrades to the explore feed with a
 * warning instead of hitting a non-existent endpoint.
 */
final class DarkModeDesignSource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://www.darkmodedesign.com';

    public function key(): string
    {
        return 'darkmode';
    }

    public function label(): string
    {
        return 'Dark Mode Design';
    }

    protected function supportsSearch(): bool
    {
        return false;
    }

    protected function selectors(): array
    {
        return [
            'card' => ['.site', '.dark-site'],
            'image' => 'img',
            'link' => 'a',
            'title' => ['.site__name', '.dark-site__name'],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/';
    }
}
