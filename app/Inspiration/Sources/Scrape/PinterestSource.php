<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * Pinterest pins feed (tier 3, best effort).
 *
 * Pinterest no longer ships an `application/ld+json` payload on the search
 * shell (verified 2026-10-05): the pins live in the `__PWS_INITIAL_PROPS__`
 * application/json script under `initialReduxState.pins`, keyed by pin id, where
 * each value is either null (not yet loaded) or a pin object carrying `id`,
 * `grid_title`/`description` and an `images` map (`orig`, `736x`… `236x`, each
 * either a URL string or `{url,width,height}`). `__PWS_DATA__` is also scanned
 * for the same state in case Pinterest moves it, and a bare top-level `pins` /
 * `pinResources` is tolerated as a defensive fallback.
 *
 * The live shell currently returns `initialReduxState.pins` empty (bot-wall /
 * prefetch mode), so explore() raises a SourceException and the manager serves
 * the cached payload rather than blanking the grid. Since the search surface is
 * a curated stream (not a term-accurate result page), search() delegates to the
 * explore feed and supportsSearch=false.
 */
final class PinterestSource extends AbstractEmbeddedJsonSource
{
    /**
     * Curated explore term. Pinterest's search results are personalised and
     * opaque, so the adapter pins a single design feed instead of pretending to
     * honour arbitrary queries.
     */
    private const EXPLORE_URL = 'https://www.pinterest.com/search/pins/?q=design';

    private const PIN_URL = 'https://www.pinterest.com/pin/%s/';

    private const IMAGE_VARIANTS = ['orig', '736x', '564x', '474x', '236x'];

    public function key(): string
    {
        return 'pinterest';
    }

    public function label(): string
    {
        return 'Pinterest';
    }

    protected function exploreUrl(): string
    {
        return self::EXPLORE_URL;
    }

    protected function itemsFromPayloads(array $payloads): array
    {
        $items = [];

        foreach ($payloads as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            foreach ($this->pinMaps($payload) as $pins) {
                foreach ($pins as $pin) {
                    $normalized = $this->normalizePin($pin);

                    if ($normalized !== null) {
                        $items[$normalized['sourceId']] = $normalized;
                    }
                }
            }
        }

        return array_values($items);
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<int, array<mixed>>
     */
    private function pinMaps(array $payload): array
    {
        $candidates = [
            $payload['initialReduxState']['pins'] ?? null,
            $payload['props']['initialReduxState']['pins'] ?? null,
            $payload['initialReduxState']['search']['pins'] ?? null,
            $payload['pins'] ?? null,
            $payload['pinResources'] ?? null,
        ];

        return array_values(array_filter($candidates, 'is_array'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizePin(mixed $pin): ?array
    {
        if (! is_array($pin)) {
            return null;
        }

        $id = $this->scalarString($pin['id'] ?? null);
        $image = $this->pinImage($pin['images'] ?? null);

        if ($id === null || $image === null) {
            return null;
        }

        return [
            'sourceId' => $id,
            'pageUrl' => sprintf(self::PIN_URL, $id),
            'imageUrl' => $image['url'],
            'thumbnailUrl' => $image['thumbnail'],
            'title' => $this->firstString(
                $pin['grid_title'] ?? null,
                $pin['description'] ?? null,
                $pin['title'] ?? null,
            ),
            'width' => $image['width'],
            'height' => $image['height'],
        ];
    }

    /**
     * @return array{url: string, thumbnail: string, width: ?int, height: ?int}|null
     */
    private function pinImage(mixed $images): ?array
    {
        if (! is_array($images)) {
            return null;
        }

        $main = null;

        foreach (self::IMAGE_VARIANTS as $variant) {
            $main = $this->imageEntry($images[$variant] ?? null);

            if ($main !== null) {
                break;
            }
        }

        if ($main === null) {
            return null;
        }

        $thumbnail = $main['url'];

        // The thumbnail is the smallest variant the pin actually ships.
        foreach (array_reverse(self::IMAGE_VARIANTS) as $variant) {
            $candidate = $this->imageEntry($images[$variant] ?? null);

            if ($candidate !== null) {
                $thumbnail = $candidate['url'];

                break;
            }
        }

        return [
            'url' => $main['url'],
            'thumbnail' => $thumbnail,
            'width' => $main['width'],
            'height' => $main['height'],
        ];
    }

    /**
     * @return array{url: string, width: ?int, height: ?int}|null
     */
    private function imageEntry(mixed $entry): ?array
    {
        if (is_string($entry)) {
            $url = $this->scalarString($entry);

            return $url === null ? null : ['url' => $url, 'width' => null, 'height' => null];
        }

        if (! is_array($entry)) {
            return null;
        }

        $url = $this->scalarString($entry['url'] ?? null);

        if ($url === null) {
            return null;
        }

        return [
            'url' => $url,
            'width' => is_numeric($entry['width'] ?? null) ? (int) $entry['width'] : null,
            'height' => is_numeric($entry['height'] ?? null) ? (int) $entry['height'] : null,
        ];
    }
}
