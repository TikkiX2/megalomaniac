<?php

declare(strict_types=1);

namespace App\Inspiration\Scraping;

use App\Inspiration\Exceptions\SourceException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shared HTTP plumbing for the tier 2 HTML scrapers.
 *
 * Every transport error and every non-successful response is normalized into a
 * SourceException so the SourceManager isolation contract is never broken by a
 * raw Guzzle error, mirroring AbstractApiSource for the JSON adapters.
 */
final class ScraperClient
{
    private const USER_AGENT = 'MegalomaniacInspiration/1.0 (+uso personal)';

    /**
     * Fetch a page and return its raw HTML body.
     *
     * @throws SourceException when the host rejects the request (4xx/5xx) or
     *                         when the transfer itself fails (timeout, DNS…).
     */
    public function get(string $url): string
    {
        try {
            $response = $this->request()->get($url);

            if ($response->failed()) {
                throw new SourceException('scraper: '.$this->host($url).' responded '.$response->status());
            }

            return $response->body();
        } catch (SourceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SourceException(
                'scraper: '.$this->host($url).': '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }

    private function request(): PendingRequest
    {
        // Two total attempts: the initial request plus one retry after a short
        // backoff (Laravel's retry helper counts attempts, not retries).
        return Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout((int) config('inspiration.timeouts.tier2', 12))
            ->retry(2, 200, throw: false);
    }

    private function host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $url;
    }
}
