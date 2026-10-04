<?php

declare(strict_types=1);

namespace App\Inspiration\Dtos;

/**
 * A single page of inspiration items returned by a source.
 */
class Page
{
    /**
     * @param  array<int, InspirationItem>  $items
     */
    public function __construct(
        public readonly array $items = [],
        public readonly bool $hasMore = false,
        public readonly ?int $nextPage = null,
    ) {}

    /**
     * @param  array<int, InspirationItem>  $items
     */
    public static function fromItems(array $items, bool $hasMore, ?int $nextPage): self
    {
        return new self(array_values($items), $hasMore, $nextPage);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, has_more: bool, next_page: ?int}
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                static fn (InspirationItem $item): array => $item->toArray(),
                $this->items,
            ),
            'has_more' => $this->hasMore,
            'next_page' => $this->nextPage,
        ];
    }
}
