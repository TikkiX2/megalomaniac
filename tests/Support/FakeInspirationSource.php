<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Inspiration\Concerns\UsesCredentials;
use App\Inspiration\Contracts\Source;
use App\Inspiration\Dtos\InspirationItem;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\Exceptions\SourceException;
use Closure;

/**
 * Configurable in-memory source used to exercise SourceManager without HTTP.
 */
class FakeInspirationSource implements Source
{
    use UsesCredentials;

    public int $searchCalls = 0;

    public int $exploreCalls = 0;

    /**
     * @param  Closure(int, string, SourceQuery): Page|null  $searchCallback
     * @param  Closure(int, SourceQuery): Page|null  $exploreCallback
     */
    public function __construct(
        private readonly string $key,
        private readonly bool $fails = false,
        private readonly bool $needsKey = false,
        private readonly ?int $ratePerMinute = null,
        private readonly ?Closure $searchCallback = null,
        private readonly ?Closure $exploreCallback = null,
    ) {}

    /**
     * Bind these fakes into the tagged container registry SourceManager reads.
     *
     * The production provider already tagged the real adapters; `tag()` appends,
     * so fakes coexist and only surface when the user enables them.
     *
     * @param  array<int, self>  $sources
     */
    public static function register(array $sources): void
    {
        foreach ($sources as $source) {
            app()->instance('inspiration.fake.'.$source->key(), $source);
        }

        app()->tag(
            array_map(static fn (self $source): string => 'inspiration.fake.'.$source->key(), $sources),
            'inspiration.sources',
        );
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return ucfirst($this->key);
    }

    public function capabilities(): SourceCapabilities
    {
        return new SourceCapabilities(
            supportsSearch: true,
            supportsExplore: true,
            needsKey: $this->needsKey,
            hasMaturityLevels: true,
            ratePerMinute: $this->ratePerMinute,
        );
    }

    public function isConfigured(): bool
    {
        return ! $this->needsKey || isset($this->credentials['key']);
    }

    /**
     * Expose the credential bag the manager hydrated, so tests can assert the
     * canonical per-source shape plus the injected user agent.
     *
     * @return array<string, mixed>
     */
    public function receivedCredentials(): array
    {
        return $this->credentials;
    }

    public function search(string $query, int $page, SourceQuery $queryOptions): Page
    {
        $this->searchCalls++;

        if ($this->fails) {
            throw new SourceException('Fake source is down.');
        }

        if ($this->searchCallback !== null) {
            return ($this->searchCallback)($page, $query, $queryOptions);
        }

        return Page::fromItems([$this->item($this->key.'-1')], false, null);
    }

    public function explore(int $page, SourceQuery $queryOptions): Page
    {
        $this->exploreCalls++;

        if ($this->fails) {
            throw new SourceException('Fake source is down.');
        }

        if ($this->exploreCallback !== null) {
            return ($this->exploreCallback)($page, $queryOptions);
        }

        return Page::fromItems([$this->item($this->key.'-explore-'.$page)], false, null);
    }

    public function test(): bool
    {
        return ! $this->fails;
    }

    public function item(string $sourceId): InspirationItem
    {
        return new InspirationItem(
            source: $this->key,
            sourceId: $sourceId,
            pageUrl: 'https://example.com/'.$sourceId,
            imageUrl: 'https://cdn.example.com/'.$sourceId.'.jpg',
            title: $sourceId,
        );
    }
}
