<?php

declare(strict_types=1);

namespace App\Inspiration\Dtos;

/**
 * Canonical image item produced by every adapter.
 */
class InspirationItem
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public readonly string $source,
        public readonly string $sourceId,
        public readonly string $pageUrl,
        public readonly string $imageUrl,
        public readonly ?string $title = null,
        public readonly ?string $author = null,
        public readonly ?string $authorUrl = null,
        public readonly ?string $thumbnailUrl = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly array $tags = [],
        public readonly ?string $dominantColor = null,
        public readonly ?string $license = null,
        public readonly ?string $maturity = null,
    ) {}

    /**
     * Build an item from a normalized adapter payload, discarding entries
     * that lack the two fields a card cannot render without.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromSource(string $source, array $data): ?self
    {
        $pageUrl = self::stringOrNull($data['pageUrl'] ?? null);
        $imageUrl = self::stringOrNull($data['imageUrl'] ?? null);

        if ($pageUrl === null || $imageUrl === null) {
            return null;
        }

        return new self(
            source: $source,
            sourceId: self::stringOrNull($data['sourceId'] ?? null) ?? '',
            pageUrl: $pageUrl,
            imageUrl: $imageUrl,
            title: self::stringOrNull($data['title'] ?? null),
            author: self::stringOrNull($data['author'] ?? null),
            authorUrl: self::stringOrNull($data['authorUrl'] ?? null),
            thumbnailUrl: self::stringOrNull($data['thumbnailUrl'] ?? null),
            width: self::intOrNull($data['width'] ?? null),
            height: self::intOrNull($data['height'] ?? null),
            tags: array_values(array_filter(
                (array) ($data['tags'] ?? []),
                static fn (mixed $tag): bool => is_string($tag) && $tag !== '',
            )),
            dominantColor: self::stringOrNull($data['dominantColor'] ?? null),
            license: self::stringOrNull($data['license'] ?? null),
            maturity: self::stringOrNull($data['maturity'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'sourceId' => $this->sourceId,
            'title' => $this->title,
            'author' => $this->author,
            'authorUrl' => $this->authorUrl,
            'pageUrl' => $this->pageUrl,
            'imageUrl' => $this->imageUrl,
            'thumbnailUrl' => $this->thumbnailUrl,
            'width' => $this->width,
            'height' => $this->height,
            'tags' => $this->tags,
            'dominantColor' => $this->dominantColor,
            'license' => $this->license,
            'maturity' => $this->maturity,
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
