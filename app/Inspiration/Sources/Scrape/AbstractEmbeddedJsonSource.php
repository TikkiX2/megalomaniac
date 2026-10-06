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
use App\Inspiration\Scraping\ScraperClient;
use DOMDocument;
use Illuminate\Support\Facades\Log;

/**
 * Shared plumbing for the tier 3 best-effort scrapers that read JSON embedded
 * in a server-rendered HTML shell (Pinterest's __PWS_INITIAL_PROPS__, Cara's
 * __NEXT_DATA__).
 *
 * These sites expose no stable public JSON API and answer bot-walls on a whim,
 * so the contract is intentionally narrow: explore() fetches one shell and
 * requires at least one decodable JSON <script> *and* at least one recognizable
 * entry. A shell without usable JSON, or JSON whose shape no longer matches the
 * expected one, is indistinguishable from a redesign or a challenge page and
 * raises a SourceException, so the SourceManager degrades to the last cached
 * payload instead of caching an empty page over it.
 *
 * HtmlParser::cards is deliberately *not* used here: the payload is JSON, not a
 * card grid, so each adapter parses its own embedded shape (documented on the
 * concrete class). search() delegates to the curated explore feed with a warning
 * because neither search surface is stable enough to parse.
 */
abstract class AbstractEmbeddedJsonSource implements Source
{
    use SendsSessionCookie;
    use UsesCredentials;

    private const PAGE_SIZE = 24;

    public function __construct(
        private readonly ScraperClient $client = new ScraperClient,
    ) {}

    abstract public function key(): string;

    abstract public function label(): string;

    /**
     * Absolute URL of the curated feed explored when the source is used.
     */
    abstract protected function exploreUrl(): string;

    /**
     * Normalize the decoded embedded payloads into InspirationItem payloads.
     *
     * @param  array<int, array<mixed>>  $payloads
     * @return array<int, array<string, mixed>>
     */
    abstract protected function itemsFromPayloads(array $payloads): array;

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: false,
            supportsExplore: true,
            needsKey: false,
            hasMaturityLevels: false,
            maxPageSize: self::PAGE_SIZE,
            ratePerMinute: 2,
        );
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function search(string $query, int $page, SourceQuery $queryOptions): Page
    {
        $query = trim($query);

        // One curated feed page: page 2+ degrades to an empty page rather than
        // repeating the same embed, so the UI never requests it.
        if ($query === '' || $page > 1) {
            return Page::fromItems([], false, null);
        }

        Log::warning('inspiration: source has no native search, delegating to explore', [
            'source' => $this->key(),
            'query' => $query,
        ]);

        return $this->explore($page, $queryOptions);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        $html = $this->client->get($this->exploreUrl(), $this->sessionHeaders());
        $items = [];

        foreach ($this->itemsFromPayloads($this->embeddedJson($html)) as $data) {
            $item = InspirationItem::fromSource($this->key(), $data);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        // A curated feed that suddenly yields nothing means the embedded shape
        // changed (or the shell is a bot-wall); failing lets the manager serve
        // the cached payload instead of overwriting it with an empty page.
        if ($items === []) {
            throw new SourceException($this->key().': no recognizable items in embedded payload (site change?)');
        }

        return Page::fromItems($items, false, null);
    }

    public function test(): bool
    {
        try {
            $this->explore(1, new SourceQuery);

            return true;
        } catch (SourceException) {
            return false;
        }
    }

    /**
     * Decode every JSON-bearing <script> in the shell.
     *
     * @return array<int, array<mixed>>
     *
     * @throws SourceException when the shell carries no decodable JSON at all,
     *                         which is the bot-wall / site-change signature.
     */
    protected function embeddedJson(string $html): array
    {
        $payloads = [];
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded) {
            foreach ($document->getElementsByTagName('script') as $script) {
                $type = strtolower(trim($script->getAttribute('type')));

                if (! in_array($type, ['application/json', 'application/ld+json'], true)) {
                    continue;
                }

                $decoded = json_decode(trim($script->textContent), true);

                if (is_array($decoded)) {
                    $payloads[] = $decoded;
                }
            }
        }

        if ($payloads === []) {
            throw new SourceException($this->key().': no embedded JSON payload (bot-wall or site change)');
        }

        return $payloads;
    }

    protected function scalarString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function firstString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $string = $this->scalarString($value);

            if ($string !== null) {
                return $string;
            }
        }

        return null;
    }
}
