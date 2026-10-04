<?php

declare(strict_types=1);

namespace App\Inspiration\Contracts;

use App\Inspiration\Dtos\Page;
use App\Inspiration\Dtos\SourceCapabilities;
use App\Inspiration\Dtos\SourceQuery;

/**
 * Single contract implemented by every inspiration source adapter.
 */
interface Source
{
    /**
     * Stable, machine-readable identifier used in settings, cache and URLs.
     */
    public function key(): string;

    /**
     * Human-readable label shown in the source chips.
     */
    public function label(): string;

    public function capabilities(): SourceCapabilities;

    /**
     * Whether the source can be used right now (no key needed, or the user
     * credential was provided through setCredentials()).
     */
    public function isConfigured(): bool;

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function setCredentials(array $credentials): void;

    public function search(string $query, int $page, SourceQuery $queryOptions): Page;

    public function explore(int $page, SourceQuery $queryOptions): Page;

    /**
     * Lightweight connectivity check used by the settings screen.
     */
    public function test(): bool;
}
