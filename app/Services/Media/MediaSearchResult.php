<?php

namespace App\Services\Media;

/**
 * Resultado normalizado de una búsqueda externa de media.
 *
 * Todos los providers lo devuelven con la misma forma para que el front
 * (y el alta en cola) no tenga que conocer el payload crudo de cada API.
 */
final readonly class MediaSearchResult
{
    public function __construct(
        public string $title,
        public ?string $creator,
        public ?int $year,
        public ?string $cover_url,
        public string $source,
        public string $external_id,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: (string) ($data['title'] ?? ''),
            creator: isset($data['creator']) && $data['creator'] !== '' ? (string) $data['creator'] : null,
            year: isset($data['year']) && $data['year'] !== '' ? (int) $data['year'] : null,
            cover_url: isset($data['cover_url']) && $data['cover_url'] !== '' ? (string) $data['cover_url'] : null,
            source: (string) ($data['source'] ?? ''),
            external_id: (string) ($data['external_id'] ?? ''),
        );
    }

    /**
     * Forma exacta que expone el endpoint GET /today/queue/search.
     *
     * @return array{title: string, creator: ?string, year: ?int, cover_url: ?string, source: string, external_id: string}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'creator' => $this->creator,
            'year' => $this->year,
            'cover_url' => $this->cover_url,
            'source' => $this->source,
            'external_id' => $this->external_id,
        ];
    }
}
