<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * Behance gallery HTML feed.
 *
 * Cards are `.project-card` tiles: an anchor wrapping the cover plus a
 * `.project-card__title` caption, linked to /gallery/{id}/{slug}. Explore is the
 * /galleries feed; search is /search/projects?search={term}.
 *
 * Known divergence: the live Behance grid is a JavaScript app shell whose state
 * is embedded as JSON, not cards in static HTML. Behance also answers non-browser
 * clients with 403. The adapter therefore targets a plausible server-rendered
 * shape and relies on the shared degradation contract (empty page, no crash)
 * for the real markup; reactivating it needs an HTML/JSON extraction strategy,
 * not a selector tweak.
 */
final class BehanceSource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://www.behance.net';

    public function key(): string
    {
        return 'behance';
    }

    public function label(): string
    {
        return 'Behance';
    }

    protected function selectors(): array
    {
        return [
            'card' => ['.project-card', '.gallery-card', '.project'],
            'image' => 'img',
            'link' => 'a',
            'title' => ['.project-card__title', '.project-title', 'h3'],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function searchUrl(string $query): string
    {
        return self::BASE_URL.'/search/projects?search='.rawurlencode($query);
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/galleries';
    }
}
