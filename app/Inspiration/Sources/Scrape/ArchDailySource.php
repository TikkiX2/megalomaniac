<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * ArchDaily HTML feed.
 *
 * Cards are server-rendered `.afd-post-stream` article blocks: the title anchor
 * (`a.afd-title--black-link`) links to the project (`/{id}/{slug}`) and the
 * first image inside the card is the featured cover from `images.adsttc.com`.
 *
 * The site has no server-rendered search results (the search page loads its
 * grid client-side), so supportsSearch() is false and search() delegates to the
 * home stream with a warning instead of parsing an empty shell.
 */
final class ArchDailySource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://www.archdaily.com';

    public function key(): string
    {
        return 'archdaily';
    }

    public function label(): string
    {
        return 'ArchDaily';
    }

    protected function supportsSearch(): bool
    {
        return false;
    }

    protected function selectors(): array
    {
        return [
            'card' => '.afd-post-stream',
            'image' => 'img',
            'link' => 'a',
            'title' => ['.afd-title--black-link', "[itemprop='name']"],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/';
    }
}
