<?php

declare(strict_types=1);

namespace App\Inspiration\Sources\Scrape;

/**
 * Savee feed (best-effort).
 *
 * NOTE (2026): savee.it redirects to savee.com and the app is now a
 * client-rendered SPA; the public API (`api.savee.it/v1/...`) answers
 * `401 Missing Bearer token`. The card selectors below are kept as a
 * best-effort attempt for any server-rendered fragment and degrade to the
 * cached payload when the shell no longer matches — the source is otherwise
 * dormant until Savee exposes an anonymous surface again.
 */
final class SaveeSource extends AbstractScrapeSource
{
    private const BASE_URL = 'https://savee.com';

    public function key(): string
    {
        return 'savee';
    }

    public function label(): string
    {
        return 'Savee';
    }

    protected function selectors(): array
    {
        return [
            'card' => '.post',
            'image' => 'img',
            'link' => 'a',
            'title' => ['.post-title', '.post__title'],
            'base' => self::BASE_URL.'/',
        ];
    }

    protected function searchUrl(string $query): string
    {
        return self::BASE_URL.'/search/?q='.rawurlencode($query);
    }

    protected function exploreUrl(): string
    {
        return self::BASE_URL.'/';
    }
}
