<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

use App\Inspiration\Concerns\SendsSessionCookie;
use App\Inspiration\Concerns\UsesCredentials;
use App\Inspiration\Contracts\Source;
use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\Exceptions\SourceException;
use App\Inspiration\Scraping\HtmlParser;
use App\Inspiration\Scraping\ScraperClient;
use Illuminate\Support\Facades\Log;

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
    use SendsSessionCookie;
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
     *
     * Only reached when supportsSearch() is true; a site without a native
     * search never calls it because search() delegates to explore(). The
     * explore feed is the safe default for adapters that opt out.
     */
    protected function searchUrl(string $query): string
    {
        return $this->exploreUrl();
    }

    /**
     * Absolute URL of the site's public explore feed.
     */
    abstract protected function exploreUrl(): string;

    /**
     * Whether the site ships a native search box.
     *
     * Sites that do not are still searchable by delegating to their explore
     * feed, with a warning so the degradation is observable in logs.
     */
    protected function supportsSearch(): bool
    {
        return true;
    }

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: $this->supportsSearch(),
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

        if (! $this->supportsSearch()) {
            Log::warning('inspiration: source has no native search, delegating to explore', [
                'source' => $this->key(),
                'query' => $query,
            ]);

            return $this->explore($page, $queryOptions);
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
            // Probing a source without a native search through search() would
            // emit the delegation warning on every connectivity check, so probe
            // its real feed directly instead.
            if ($this->supportsSearch()) {
                $this->search('portrait', 1, new SourceQuery);
            } else {
                $this->explore(1, new SourceQuery);
            }

            return true;
        } catch (SourceException) {
            return false;
        }
    }

    /**
     * Fetch a page and normalize its cards.
     *
     * The resolver base defaults to the adapter's declared base (its primary
     * host). A multi-host adapter must pass the base of the *active* feed so
     * relative src/href served by a secondary host resolve to that host and do
     * not leak into the primary host's URLs.
     */
    protected function pageFrom(string $url, ?string $base = null): Page
    {
        $selectors = $this->selectors();

        if ($base !== null) {
            $selectors['base'] = $base;
        }

        $html = $this->client->get($url, $this->sessionHeaders());
        $items = [];

        foreach ($this->parser->cards($html, $selectors) as $card) {
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
