<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * Lapa Ninja HTML feed.
 *
 * STATUS 2026-10: lapa.ninja answers 403 (Cloudflare bot-wall) from server
 * IPs, so the source is DORMANT in practice: cards here are the contract for
 * the day the wall lifts, and the adapter degrades to cached content either
 * way. Removed from the default sources.
 *
 * Cards are `.shot` tiles: an anchor wrapping the site screenshot plus a
 * `.shot__title` caption. Explore is the home feed. Lapa ships a search page at
 * /search; the site does not document its parameter and returns 403 to
 * non-browser clients, so the adapter locks the legacy `?s=` query that the
 * brief prescribes when the shape cannot be confirmed.
 */
final class LapaNinjaSource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://www.lapa.ninja';

    public function key(): string
    {
        return 'lapaninja';
    }

    public function label(): string
    {
        return 'Lapa Ninja';
    }

    protected function selectors(): array
    {
        return [
            'card' => ['.shot', '.shot-card'],
            'image' => 'img',
            'link' => 'a',
            'title' => ['.shot__title', '.shot-card__title'],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function searchUrl(string $query): string
    {
        return self::BASE_URL.'/search?s='.rawurlencode($query);
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/';
    }
}
