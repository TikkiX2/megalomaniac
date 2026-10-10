<?php

namespace App\Services\Media\Providers;

use App\Services\Media\MediaSearchResult;
use Illuminate\Support\Facades\Http;

/**
 * Provider keyless de discos vía MusicBrainz.
 *
 * La portada viene de Cover Art Archive: se consulta en modo HEAD y si el
 * archivo no existe (4xx/5xx) el resultado queda sin cover_url en vez de
 * romper la búsqueda.
 */
final class MusicBrainzProvider implements MediaProvider
{
    private const ENDPOINT = 'https://musicbrainz.org/ws/2/release-group';

    private const USER_AGENT = 'Megalomaniac/1.0 (contacto: usuario personal)';

    public function search(string $query): array
    {
        $response = Http::timeout(6)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get(self::ENDPOINT, [
                'query' => "{$query} AND primarytype:album",
                'fmt' => 'json',
                'limit' => 10,
            ])
            ->throw();

        return collect($response->json('release-groups') ?? [])
            ->filter(fn ($group): bool => is_array($group) && filled($group['title'] ?? null))
            ->map(fn (array $group): MediaSearchResult => MediaSearchResult::fromArray([
                'title' => $group['title'],
                'creator' => $group['artist-credit'][0]['name'] ?? null,
                'year' => $this->year($group['first-release-date'] ?? null),
                'cover_url' => $this->cover((string) ($group['id'] ?? '')),
                'source' => 'musicbrainz',
                'external_id' => (string) ($group['id'] ?? ''),
            ]))
            ->values()
            ->all();
    }

    private function year(?string $date): ?int
    {
        return $date && preg_match('/^\d{4}/', $date, $matches) ? (int) $matches[0] : null;
    }

    private function cover(string $releaseGroupId): ?string
    {
        if ($releaseGroupId === '') {
            return null;
        }

        $url = 'https://coverartarchive.org/release-group/'.$releaseGroupId.'/front-250';

        return Http::timeout(6)->head($url)->failed() ? null : $url;
    }
}
