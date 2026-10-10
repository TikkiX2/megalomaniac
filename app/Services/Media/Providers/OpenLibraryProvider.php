<?php

namespace App\Services\Media\Providers;

use App\Services\Media\MediaSearchResult;
use Illuminate\Support\Facades\Http;

/**
 * Provider keyless de libros vía Open Library.
 */
final class OpenLibraryProvider implements MediaProvider
{
    private const ENDPOINT = 'https://openlibrary.org/search.json';

    public function search(string $query): array
    {
        $response = Http::timeout(6)
            ->get(self::ENDPOINT, ['q' => $query, 'limit' => 10])
            ->throw();

        return collect($response->json('docs') ?? [])
            ->filter(fn ($doc): bool => is_array($doc) && filled($doc['title'] ?? null))
            ->map(fn (array $doc): MediaSearchResult => MediaSearchResult::fromArray([
                'title' => $doc['title'],
                'creator' => $doc['author_name'][0] ?? null,
                'year' => $doc['first_publish_year'] ?? null,
                'cover_url' => filled($doc['cover_i'] ?? null)
                    ? 'https://covers.openlibrary.org/b/id/'.(int) $doc['cover_i'].'-M.jpg'
                    : null,
                'source' => 'openlibrary',
                'external_id' => (string) ($doc['key'] ?? ''),
            ]))
            ->values()
            ->all();
    }
}
