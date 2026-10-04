<?php

declare(strict_types=1);

namespace App\Inspiration\Concerns;

/**
 * Adds the per-user credential bag every source contract expects.
 */
trait UsesCredentials
{
    /**
     * @var array<string, mixed>
     */
    protected array $credentials = [];

    /**
     * Replace the credential bag with the non-null values provided.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function setCredentials(array $credentials): void
    {
        $this->credentials = array_filter(
            $credentials,
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
