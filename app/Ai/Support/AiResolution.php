<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Models\AiProvider;
use Illuminate\Support\Collection;

/**
 * Outcome of a scope resolution: the ordered provider attempts to try plus the
 * composed personalization block for the resolved context.
 */
final readonly class AiResolution
{
    /**
     * @param  Collection<int, AiProvider>  $chain  Ordered attempts; already
     *                                              health-filtered, never empty when
     *                                              a usable provider exists.
     * @param  string|null  $promptBlock  Composed global → module → surface layers.
     */
    public function __construct(
        public Collection $chain,
        public ?string $promptBlock,
    ) {}

    /** The first attempt, i.e. the provider a request should hit first. */
    public function primary(): ?AiProvider
    {
        return $this->chain->first();
    }

    /** True when no provider survived the health filter. */
    public function isEmpty(): bool
    {
        return $this->chain->isEmpty();
    }
}
