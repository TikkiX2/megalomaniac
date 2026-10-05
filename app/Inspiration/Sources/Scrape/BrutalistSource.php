<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceQuery;

/**
 * Brutalist Websites HTML feed, backed by two hosts in one adapter.
 *
 * Cards are `.website` blocks: an anchor wrapping the site screenshot plus a
 * `.website__title` caption. The primary feed is brutalistwebsites.com; when it
 * yields no cards the adapter retries the secondary Brutal Web feed
 * (brutalweb.xyz) rather than merging both on every request. A transport error
 * from the primary still propagates so the SourceManager isolation/status
 * contract keeps working — only an empty primary triggers the fallback.
 *
 * The site has no search box, so supportsSearch() is false and search()
 * degrades to explore() with a warning.
 */
final class BrutalistSource extends AbstractScrapeSource
{
    private const PRIMARY_URL = 'https://brutalistwebsites.com/';

    private const FALLBACK_URL = 'https://brutalweb.xyz/';

    public function key(): string
    {
        return 'brutalist';
    }

    public function label(): string
    {
        return 'Brutalist Websites';
    }

    protected function supportsSearch(): bool
    {
        return false;
    }

    protected function selectors(): array
    {
        return [
            'card' => ['.website', '.brutalist-entry'],
            'image' => 'img',
            'link' => 'a',
            'title' => ['.website__title', '.brutalist-entry__title'],
            'base' => self::PRIMARY_URL,
        ];
    }

    protected function exploreUrl(): string
    {
        return self::PRIMARY_URL;
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        $page = parent::explore($page, $queryOptions);

        if ($page->items !== []) {
            return $page;
        }

        return $this->pageFrom(self::FALLBACK_URL);
    }
}
