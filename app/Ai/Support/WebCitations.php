<?php

namespace App\Ai\Support;

class WebCitations
{
    /**
     * Length of the persisted/framed snippet, in characters.
     */
    public const SNIPPET_LENGTH = 160;

    /**
     * Tool names whose results carry web sources.
     *
     * @var array<int, string>
     */
    public const TOOLS = ['WebSearchTool', 'WebFetchTool'];

    /**
     * Extract citations from an SDK tool_result event. Returns an empty list
     * for non-web tools, failed/denied results, malformed JSON and payloads
     * that only carry an error.
     *
     * @param  array<string, mixed>  $event
     * @return array<int, array{url: string, title: ?string, snippet: ?string}>
     */
    public static function fromToolResult(array $event): array
    {
        if (($event['type'] ?? null) !== 'tool_result' || ($event['successful'] ?? null) !== true) {
            return [];
        }

        $tool = $event['tool_name'] ?? null;

        if (! is_string($tool) || ! in_array($tool, self::TOOLS, true)) {
            return [];
        }

        $result = $event['result'] ?? null;

        if (is_string($result)) {
            $result = json_decode($result, true);
        }

        if (! is_array($result) || isset($result['error'])) {
            return [];
        }

        $rows = $tool === 'WebFetchTool' ? ($result['pages'] ?? []) : ($result['results'] ?? []);

        return is_array($rows) ? self::fromRows($rows) : [];
    }

    /**
     * Map raw source rows (Tavily search results, extracted pages or forced
     * pre-search sources) into citation entries. Rows without a URL are
     * dropped; missing titles fall back to the hostname.
     *
     * @param  iterable<mixed>  $rows
     * @return array<int, array{url: string, title: ?string, snippet: ?string}>
     */
    public static function fromRows(iterable $rows): array
    {
        $citations = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $url = $row['url'] ?? null;

            if (! is_string($url) || trim($url) === '') {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $content = trim((string) ($row['content'] ?? ''));

            $citations[] = [
                'url' => $url,
                'title' => $title !== '' ? $title : self::hostname($url),
                'snippet' => $content === '' ? null : mb_substr($content, 0, self::SNIPPET_LENGTH),
            ];
        }

        return $citations;
    }

    /**
     * Merge citation groups (native provider citations first, then web sources
     * by appearance order), dropping duplicate URLs and normalizing the keys
     * to {url, title, snippet}.
     *
     * @param  array<int, array<string, mixed>>  ...$groups
     * @return array<int, array{url: string, title: ?string, snippet: ?string}>
     */
    public static function merge(array ...$groups): array
    {
        $merged = [];
        $seen = [];

        foreach ($groups as $group) {
            foreach ($group as $citation) {
                if (! is_array($citation)) {
                    continue;
                }

                $url = $citation['url'] ?? null;

                if (! is_string($url) || $url === '' || isset($seen[$url])) {
                    continue;
                }

                $seen[$url] = true;
                $title = $citation['title'] ?? null;
                $snippet = $citation['snippet'] ?? null;

                $merged[] = [
                    'url' => $url,
                    'title' => is_string($title) && $title !== '' ? $title : null,
                    'snippet' => is_string($snippet) && $snippet !== '' ? $snippet : null,
                ];
            }
        }

        return $merged;
    }

    protected static function hostname(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
