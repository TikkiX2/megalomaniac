<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

use App\Inspiration\Concerns\UsesCredentials;
use App\Inspiration\Contracts\Source;
use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\Exceptions\SourceException;
use App\Inspiration\Scraping\HtmlParser;
use App\Inspiration\Scraping\ScraperClient;

/**
 * Shared plumbing for the tier 2 HTML scrapers.
 *
 * Concrete adapters only declare the CSS contract of their site plus the feed
 * and search URLs; fetching, card normalization and failure normalization live
 * here. Every transport or HTTP failure is already wrapped in a SourceException
 * by ScraperClient, so the SourceManager isolation contract holds.
 *
 * Scraped sites expose neither a reliable deep link nor a trustworthy "next
 * page" control: search() serves a single page per term (page > 1 degrades to an
 * empty page) and explore() serves a single feed page. Both advertise
 * hasMore=false so the UI never requests a page the adapter refuses to build.
 */
abstract class AbstractScrapeSource implements Source
{
    use UsesCredentials;

    private const PAGE_SIZE = 24;

    public function __construct(
        private readonly ScraperClient $client = new ScraperClient,
        private readonly HtmlParser $parser = new HtmlParser,
    ) {}

    abstract public function key(): string;

    abstract public function label(): string;

    /**
     * CSS selectors for the site's card grid.
     *
     * @return array{card: string|array<int, string>, image: string, link: string, title: string|array<int, string>|null, base: string}
     */
    abstract protected function selectors(): array;

    /**
     * Absolute URL of the site's search results page for a term.
     */
    abstract protected function searchUrl(string $query): string;

    /**
     * Absolute URL of the site's public explore feed.
     */
    abstract protected function exploreUrl(): string;

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: true,
            supportsExplore: true,
            needsKey: false,
            hasMaturityLevels: false,
            maxPageSize: self::PAGE_SIZE,
        );
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function search(string $query, int $page, SourceQuery $queryOptions): Page
    {
        $query = trim($query);

        // One page per term: scraped sites do not honour a page parameter, so
        // page 2+ degrades to an empty page instead of repeating page 1.
        if ($query === '' || $page > 1) {
            return Page::fromItems([], false, null);
        }

        return $this->pageFrom($this->searchUrl($query));
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        return $this->pageFrom($this->exploreUrl());
    }

    public function test(): bool
    {
        try {
            $this->search('portrait', 1, new SourceQuery);

            return true;
        } catch (SourceException) {
            return false;
        }
    }

    private function pageFrom(string $url): Page
    {
        $html = $this->client->get($url);
        $items = [];

        foreach ($this->parser->cards($html, $this->selectors()) as $card) {
            $item = InspirationItem::fromSource($this->key(), [
                'sourceId' => md5($card['pageUrl']),
                'pageUrl' => $card['pageUrl'],
                'imageUrl' => $card['imageUrl'],
                'thumbnailUrl' => $card['imageUrl'],
                'title' => $card['title'],
            ]);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return Page::fromItems($items, false, null);
    }
}
