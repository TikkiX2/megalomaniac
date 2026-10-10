<?php

namespace App\Services\Media\Providers;

use App\Services\Media\MediaSearchResult;
use Illuminate\Support\Facades\Http;

/**
 * Provider keyless de pelicula/serie/juego vía Wikidata.
 *
 * Dos llamadas: wbsearchentities (ids + labels) y wbgetentities (claims),
 * filtrando por la clase P31 correspondiente al tipo para descartar homónimos.
 */
final class WikidataProvider implements MediaProvider
{
    private const API = 'https://www.wikidata.org/w/api.php';

    /** @var array<string, string> clase P31 esperada por tipo */
    private const CLASSES = [
        'pelicula' => 'Q11424',
        'serie' => 'Q5398426',
        'juego' => 'Q7889',
    ];

    public function __construct(private readonly string $type)
    {
        if (! array_key_exists($type, self::CLASSES)) {
            throw new \InvalidArgumentException("Tipo sin provider de wikidata: {$type}");
        }
    }

    public function search(string $query): array
    {
        $search = collect(Http::timeout(6)
            ->get(self::API, [
                'action' => 'wbsearchentities',
                'search' => $query,
                'language' => 'es',
                'type' => 'item',
                'limit' => 10,
                'format' => 'json',
            ])
            ->throw()
            ->json('search') ?? [])
            ->filter(fn ($hit): bool => is_array($hit) && preg_match('/^Q\d+$/', (string) ($hit['id'] ?? '')))
            ->map(fn (array $hit): array => [
                'id' => (string) $hit['id'],
                'label' => (string) ($hit['label'] ?? $hit['id']),
            ])
            ->values();

        if ($search->isEmpty()) {
            return [];
        }

        $entities = Http::timeout(6)
            ->get(self::API, [
                'action' => 'wbgetentities',
                'ids' => $search->pluck('id')->implode('|'),
                'props' => 'claims',
                'languages' => 'es',
                'format' => 'json',
            ])
            ->throw()
            ->json('entities') ?? [];

        return $search
            ->filter(fn (array $hit): bool => $this->matchesClass($entities[$hit['id']]['claims'] ?? null))
            ->map(fn (array $hit): MediaSearchResult => MediaSearchResult::fromArray([
                'title' => $hit['label'],
                'creator' => null,
                'year' => $this->year($entities[$hit['id']]['claims']['P577'] ?? []),
                'cover_url' => $this->cover($entities[$hit['id']]['claims']['P18'] ?? []),
                'source' => 'wikidata',
                'external_id' => $hit['id'],
            ]))
            ->values()
            ->all();
    }

    private function matchesClass(?array $claims): bool
    {
        return collect($claims['P31'] ?? [])
            ->contains(fn ($claim): bool => $this->entityId($claim) === self::CLASSES[$this->type]);
    }

    private function year(array $claims): ?int
    {
        $value = $this->claimValue($claims[0] ?? null);
        $time = is_array($value) ? (string) ($value['time'] ?? '') : (string) $value;

        return preg_match('/\d{4}/', $time, $matches) ? (int) $matches[0] : null;
    }

    private function cover(array $claims): ?string
    {
        $file = (string) $this->claimValue($claims[0] ?? null);

        if ($file === '') {
            return null;
        }

        return 'https://commons.wikimedia.org/wiki/Special:FilePath/'.rawurlencode($file).'?width=200';
    }

    /**
     * Id de entidad (Q…) de un claim, p.ej. P31.
     */
    private function entityId(mixed $claim): ?string
    {
        $value = $this->claimValue($claim);

        return is_array($value) ? ($value['id'] ?? null) : null;
    }

    /**
     * Devuelve el datavalue de un claim (id de entidad, texto o tiempo).
     */
    private function claimValue(mixed $claim): mixed
    {
        return data_get($claim, 'mainsnak.datavalue.value');
    }
}
