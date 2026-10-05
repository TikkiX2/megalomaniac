<?php

declare(strict_types=1);

namespace App\Inspiration\Scraping;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;
use Throwable;

/**
 * Turns an HTML feed into normalized card tuples for the tier 2 scrapers.
 *
 * A card matches one of the configured CSS selectors (the first selector that
 * yields nodes wins, so a site redesign can fall back to a second variant) and
 * the image/link/title selectors are resolved *inside* that card. URLs are
 * always returned absolute; broken markup and unmatched selectors degrade to an
 * empty array instead of throwing.
 */
final class HtmlParser
{
    /**
     * @param  array{card: string|array<int, string>, image: string, link: string, title?: string|null, base?: string|null}  $selectors
     * @return array<int, array{imageUrl: string, pageUrl: string, title: ?string}>
     */
    public function cards(string $html, array $selectors): array
    {
        $imageSelector = (string) ($selectors['image'] ?? '');

        if (trim($html) === '' || $imageSelector === '') {
            return [];
        }

        $base = $selectors['base'] ?? null;

        try {
            $crawler = new Crawler($html, is_string($base) ? $base : null);
        } catch (Throwable) {
            return [];
        }

        $baseUri = $crawler->getBaseHref();
        $linkSelector = (string) ($selectors['link'] ?? '');
        $titleSelector = $selectors['title'] ?? null;
        $cards = [];

        foreach ($this->normalizeSelectors($selectors['card'] ?? []) as $cardSelector) {
            $nodes = $crawler->filter($cardSelector);

            if ($nodes->count() === 0) {
                continue;
            }

            $nodes->each(function (Crawler $card) use (
                &$cards,
                $imageSelector,
                $linkSelector,
                $titleSelector,
                $baseUri,
            ): void {
                $imageNode = $this->firstMatch($card, $imageSelector);

                if ($imageNode === null) {
                    return;
                }

                $imageUrl = $this->resolveUrl($this->imageRawUrl($imageNode), $baseUri);
                $linkNode = $this->linkNode($card, $imageNode, $linkSelector);
                $pageUrl = $linkNode === null ? null : $this->resolveUrl($linkNode->attr('href'), $baseUri);

                if ($imageUrl === null || $pageUrl === null) {
                    return;
                }

                $cards[] = [
                    'imageUrl' => $imageUrl,
                    'pageUrl' => $pageUrl,
                    'title' => $this->title($card, $titleSelector),
                ];
            });

            // The first selector that yields nodes wins; later entries are the
            // site-change fallback.
            break;
        }

        return $cards;
    }

    /**
     * @param  string|array<int, string>  $selectors
     * @return array<int, string>
     */
    private function normalizeSelectors(string|array $selectors): array
    {
        $selectors = is_array($selectors) ? $selectors : [$selectors];

        return array_values(array_filter(
            $selectors,
            static fn (mixed $selector): bool => is_string($selector) && trim($selector) !== '',
        ));
    }

    private function firstMatch(Crawler $scope, string $selector): ?Crawler
    {
        if (trim($selector) === '') {
            return null;
        }

        try {
            $matches = $scope->filter($selector);
        } catch (Throwable) {
            return null;
        }

        return $matches->count() > 0 ? $matches->first() : null;
    }

    private function linkNode(Crawler $card, Crawler $imageNode, string $linkSelector): ?Crawler
    {
        $direct = $this->firstMatch($card, $linkSelector);

        if ($direct !== null) {
            return $direct;
        }

        // The card node itself may be the anchor that wraps the image.
        if ($card->nodeName() === 'a' && $card->attr('href') !== null) {
            return $card;
        }

        // Or the anchor may wrap the whole card from the outside.
        $ancestors = $imageNode->ancestors()->filter('a');

        return $ancestors->count() > 0 ? $ancestors->first() : null;
    }

    private function imageRawUrl(Crawler $imageNode): ?string
    {
        foreach (['src', 'data-src'] as $attribute) {
            $value = $this->clean($imageNode->attr($attribute));

            // A `data:` URI is a lazy-load placeholder, not the real image, so
            // keep looking instead of returning something the filters reject.
            if ($value !== null && ! $this->isPlaceholder($value)) {
                return $value;
            }
        }

        $srcset = $this->clean($imageNode->attr('srcset'));

        return $srcset === null ? null : $this->firstSrcsetCandidate($srcset);
    }

    private function isPlaceholder(string $url): bool
    {
        return str_starts_with(strtolower($url), 'data:');
    }

    private function firstSrcsetCandidate(string $srcset): ?string
    {
        $first = trim(explode(',', $srcset, 2)[0]);

        if ($first === '') {
            return null;
        }

        $candidate = trim((string) (preg_split('/\s+/', $first, 2)[0] ?? ''));

        return $candidate === '' ? null : $candidate;
    }

    /**
     * @param  string|array<mixed>|null  $titleSelector
     */
    private function title(Crawler $card, string|array|null $titleSelector): ?string
    {
        if (! is_string($titleSelector) || $titleSelector === '') {
            return null;
        }

        $node = $this->firstMatch($card, $titleSelector);

        return $node === null ? null : $this->clean($node->text());
    }

    private function resolveUrl(?string $url, ?string $baseUri): ?string
    {
        $url = $this->clean($url);

        if ($url === null) {
            return null;
        }

        try {
            $resolved = UriResolver::resolve($url, $baseUri);
        } catch (Throwable) {
            return null;
        }

        return $this->isAbsoluteHttpUrl($resolved) ? $resolved : null;
    }

    private function isAbsoluteHttpUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        return is_string($scheme)
            && is_string($host)
            && $host !== ''
            && in_array(strtolower($scheme), ['http', 'https'], true);
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
