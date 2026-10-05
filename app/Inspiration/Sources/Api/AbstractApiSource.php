<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Api;

use App\Inspiration\Concerns\UsesCredentials;
use App\Inspiration\Contracts\Source;
use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;
use App\Inspiration\Exceptions\SourceException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shared HTTP plumbing for the official-API (tier 1) adapters.
 *
 * Every transport or JSON failure is normalized into a SourceException so the
 * SourceManager isolation contract is never broken by a raw Guzzle error.
 */
abstract class AbstractApiSource implements Source
{
    use UsesCredentials;

    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function capabilities(): SourceCapabilities;

    abstract public function search(string $query, int $page, SourceQuery $queryOptions): Page;

    abstract public function explore(int $page, SourceQuery $queryOptions): Page;

    public function isConfigured(): bool
    {
        return true;
    }

    public function test(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $this->search('portrait', 1, new SourceQuery);

            return true;
        } catch (SourceException) {
            return false;
        }
    }

    protected function timeout(): int
    {
        return (int) config('inspiration.timeouts.tier1', 5);
    }

    protected function request(): PendingRequest
    {
        return Http::timeout($this->timeout())->acceptJson();
    }

    /**
     * Hook for adapters that must sign the outbound request.
     */
    protected function authorize(PendingRequest $request): PendingRequest
    {
        return $request;
    }

    /**
     * Perform a GET and return the decoded JSON object.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws SourceException
     */
    protected function getJson(string $url, array $query = []): array
    {
        try {
            $response = $this->authorize($this->request())->get($url, $query);

            if ($response->failed()) {
                throw new SourceException($this->key().': HTTP '.$response->status());
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new SourceException($this->key().': invalid JSON payload');
            }

            return $payload;
        } catch (SourceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SourceException($this->key().': '.$exception->getMessage(), previous: $exception);
        }
    }

    /**
     * Perform a JSON POST and return the decoded JSON object.
     *
     * Mirrors getJson()'s failure contract so POST-based adapters (Bandcamp's
     * internal discover endpoint) still surface every transport and JSON error
     * as a SourceException.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws SourceException
     */
    protected function postJson(string $url, array $payload = []): array
    {
        try {
            $response = $this->authorize($this->request())->asJson()->post($url, $payload);

            if ($response->failed()) {
                throw new SourceException($this->key().': HTTP '.$response->status());
            }

            $decoded = $response->json();

            if (! is_array($decoded)) {
                throw new SourceException($this->key().': invalid JSON payload');
            }

            return $decoded;
        } catch (SourceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SourceException($this->key().': '.$exception->getMessage(), previous: $exception);
        }
    }

    /**
     * Stable, always-string identifier used for dedupe; falls back to a hash of
     * the page URL when the API omits its own id.
     */
    protected function identifier(mixed $value, string $fallbackUrl): string
    {
        if (is_scalar($value)) {
            $string = trim((string) $value);

            if ($string !== '') {
                return $string;
            }
        }

        return md5($fallbackUrl);
    }

    protected function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * The canonical single-field user credential (`keys.{source}.key`).
     *
     * Returns null for missing, non-string and blank values so an empty string
     * never counts as a configured key.
     */
    protected function configuredKey(): ?string
    {
        return $this->stringValue($this->credentials['key'] ?? null);
    }

    /**
     * Strict boolean coercion: strings like "nope" resolve to false instead of
     * PHP's truthy cast.
     */
    protected function boolValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * Normalize both `["tag"]` and `[{"name": "tag"}]` shapes.
     *
     * @return array<int, string>
     */
    protected function tagList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $tags = [];

        foreach ($value as $tag) {
            if (is_string($tag) && trim($tag) !== '') {
                $tags[] = trim($tag);

                continue;
            }

            if (is_array($tag) && is_string($tag['name'] ?? null) && trim($tag['name']) !== '') {
                $tags[] = trim($tag['name']);
            }
        }

        return array_values(array_unique($tags));
    }
}
