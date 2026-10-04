<?php

declare(strict_types=1);

namespace App\Inspiration\Dtos;

/**
 * Query options that shape an inspiration search.
 *
 * Task 2 defines the minimal shape; Task 3 expands the contract with the
 * provider-specific options.
 */
class SourceQuery
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly string $maturity = 'safe',
        public readonly array $extra = [],
    ) {}
}
