<?php

declare(strict_types=1);

namespace App\Inspiration\Dtos;

/**
 * Declares what a source can do, so the manager can dispatch without
 * knowing the concrete adapter.
 */
class SourceCapabilities
{
    public function __construct(
        public readonly bool $supportsSearch,
        public readonly bool $supportsExplore,
        public readonly bool $needsKey,
        public readonly bool $hasMaturityLevels,
        public readonly int $maxPageSize = 24,
        public readonly ?int $ratePerMinute = null,
    ) {}
}
