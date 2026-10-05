<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * Godly HTML feed.
 *
 * Cards are `.website-card` blocks (with a generic `<article>` fallback): an
 * anchor wrapping the site screenshot plus a `.website-card__title` caption.
 * Explore is the home feed — the /trending page is deliberately not wired as a
 * second request to keep the adapter one-feed simple. Search is /search?q=.
 */
final class GodlySource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://godly.website';

    public function key(): string
    {
        return 'godly';
    }

    public function label(): string
    {
        return 'Godly';
    }

    protected function selectors(): array
    {
        return [
            'card' => ['.website-card', 'article'],
            'image' => 'img',
            'link' => 'a',
            'title' => ['.website-card__title', 'h3'],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function searchUrl(string $query): string
    {
        return self::BASE_URL.'/search?q='.rawurlencode($query);
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/';
    }
}
