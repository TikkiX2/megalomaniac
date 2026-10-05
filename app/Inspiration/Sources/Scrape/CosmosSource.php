<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

use App\Inspiration\Exceptions\SourceException;

/**
 * Cosmos (cosmos.so) curated discover feed.
 *
 * The site is a client-rendered GraphQL app, but `/discover` server-renders its
 * first page through Apollo's SSR transport: inline scripts call
 * `(window[Symbol.for("ApolloSSRDataTransport")] ??= []).push({...})` with the
 * rehydrated cache. Elements inside that cache carry `shareUrl`
 * (`https://www.cosmos.so/e/{id}`) and `source.url` (the original image, often
 * hosted on the artist's own site).
 *
 * A shell without those pushes, or a cache without recognizable elements, is
 * treated as a redesign or bot-wall: the adapter raises a SourceException so
 * the manager serves the cached payload instead of caching an empty page.
 *
 * GraphQL introspection and field suggestions are disabled on api.cosmos.so,
 * so this embedded SSR route is the only anonymous surface; there is no native
 * search endpoint and search() delegates to the discover feed.
 */
final class CosmosSource extends AbstractEmbeddedJsonSource
{
    private const BASE_URL = 'https://www.cosmos.so';

    private const APOLLO_PUSH = '(window[Symbol.for("ApolloSSRDataTransport")] ??= []).push(';

    public function key(): string
    {
        return 'cosmos';
    }

    public function label(): string
    {
        return 'Cosmos';
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/discover';
    }

    /**
     * Decode the Apollo SSR transport pushes instead of JSON-typed scripts.
     *
     * @return array<int, array<mixed>>
     *
     * @throws SourceException when the shell carries no Apollo payload at all.
     */
    protected function embeddedJson(string $html): array
    {
        $payloads = [];
        $offset = 0;

        while (($start = strpos($html, self::APOLLO_PUSH, $offset)) !== false) {
            $jsonStart = strpos($html, '{', $start);

            if ($jsonStart === false) {
                break;
            }

            $end = $this->matchingBrace($html, $jsonStart);

            if ($end === null) {
                break;
            }

            $decoded = json_decode(substr($html, $jsonStart, $end - $jsonStart + 1), true);

            if (is_array($decoded)) {
                $payloads[] = $decoded;
            }

            $offset = $end;
        }

        if ($payloads === []) {
            throw new SourceException($this->key().': no Apollo embedded payload (bot-wall or site change)');
        }

        return $payloads;
    }

    /**
     * @param  array<int, array<mixed>>  $payloads
     * @return array<int, array<string, mixed>>
     */
    protected function itemsFromPayloads(array $payloads): array
    {
        $items = [];
        $seen = [];

        foreach ($payloads as $payload) {
            foreach ($this->elementMaps($payload) as $element) {
                $shareUrl = $this->firstString($element['shareUrl'] ?? null);
                $source = is_array($element['source'] ?? null) ? $element['source'] : [];
                $imageUrl = $this->firstString($source['url'] ?? null);

                if ($shareUrl === null || $imageUrl === null || ! str_contains($shareUrl, '/e/')) {
                    continue;
                }

                if (isset($seen[$shareUrl])) {
                    continue;
                }

                $seen[$shareUrl] = true;

                $owner = is_array($element['owner'] ?? null) ? $element['owner'] : [];
                $caption = is_array($element['generatedCaption'] ?? null) ? $element['generatedCaption'] : [];
                $username = $this->firstString($owner['username'] ?? null);
                $path = parse_url($shareUrl, PHP_URL_PATH);

                $items[] = [
                    'sourceId' => basename(is_string($path) && $path !== '' ? $path : $shareUrl),
                    'title' => $this->firstString($caption['text'] ?? null, $element['title'] ?? null),
                    'author' => $username,
                    'authorUrl' => $username !== null ? self::BASE_URL.'/'.$username : null,
                    'pageUrl' => $shareUrl,
                    'imageUrl' => $imageUrl,
                    'thumbnailUrl' => null,
                    'tags' => [],
                ];
            }
        }

        return $items;
    }

    /**
     * Element nodes are any array carrying a `shareUrl`; the cache nests them
     * under arbitrary operation keys, so the walk is depth-first and loose.
     *
     * @return array<int, array<mixed>>
     */
    private function elementMaps(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $found = isset($node['shareUrl']) && is_scalar($node['shareUrl']) ? [$node] : [];

        foreach ($node as $value) {
            if (is_array($value)) {
                $found = array_merge($found, $this->elementMaps($value));
            }
        }

        return $found;
    }

    /**
     * Index of the `}` matching the `{` at $start, string-aware.
     */
    private function matchingBrace(string $html, int $start): ?int
    {
        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($html);

        for ($i = $start; $i < $length; $i++) {
            $char = $html[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;

                    continue;
                }

                if ($char === '\\') {
                    $escape = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }
}
