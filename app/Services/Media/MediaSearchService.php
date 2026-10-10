<?php

namespace App\Services\Media;

use App\Models\QueueItem;
use App\Services\Media\Providers\MediaProvider;
use App\Services\Media\Providers\MusicBrainzProvider;
use App\Services\Media\Providers\OpenLibraryProvider;
use App\Services\Media\Providers\WikidataProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;

/**
 * Búsqueda de media en providers externos keyless.
 *
 * Degradación: query de menos de 2 chars o tipo desconocido → [] sin llamar a
 * la red; cualquier ConnectionException|RequestException del provider → []
 * (y sin cachear el fallo, porque la excepción sale del Cache::remember).
 * Los éxitos se cachéan 24h por tipo + query.
 */
final class MediaSearchService
{
    /**
     * @return array<int, MediaSearchResult>
     */
    public function search(string $type, string $query): array
    {
        $normalized = trim($query);

        if (mb_strlen($normalized) < 2 || ! in_array($type, QueueItem::TYPES, true)) {
            return [];
        }

        $provider = $this->provider($type);

        if (! $provider) {
            return [];
        }

        try {
            return Cache::remember(
                'media:'.$type.':'.md5(mb_strtolower($normalized)),
                now()->addDay(),
                fn (): array => $provider->search($normalized),
            );
        } catch (ConnectionException|RequestException) {
            return [];
        }
    }

    private function provider(string $type): ?MediaProvider
    {
        return match ($type) {
            'libro' => new OpenLibraryProvider,
            'disco' => new MusicBrainzProvider,
            'pelicula', 'serie', 'juego' => new WikidataProvider($type),
            default => null,
        };
    }
}
