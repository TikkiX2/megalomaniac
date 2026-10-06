<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * It's Nice That editorial feed.
 *
 * Cards are server-rendered `.listing-item` blocks: the title anchor links to
 * `/articles/{slug}` and the cover image lives in `.listing-item-image`
 * (served from media.itsnicethat.com). Search results load client-side, so
 * supportsSearch() is false and search() delegates to the articles feed.
 */
final class ItsNiceThatSource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://www.itsnicethat.com';

    public function key(): string
    {
        return 'itsnicethat';
    }

    public function label(): string
    {
        return "It's Nice That";
    }

    protected function supportsSearch(): bool
    {
        return false;
    }

    protected function selectors(): array
    {
        return [
            'card' => ['.listing-item', '.ln-container'],
            'image' => 'img',
            'link' => 'a',
            'title' => ['.listing-item-title', '.ln-title', 'h3'],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/articles';
    }
}
