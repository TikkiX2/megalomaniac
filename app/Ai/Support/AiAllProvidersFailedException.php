<?php

declare(strict_types=1);

namespace App\Ai\Support;

use RuntimeException;
use Throwable;

/**
 * Raised when every provider in the chain has been tried without success.
 *
 * Carries one message per provider (in chain order) for diagnostics and keeps
 * the last underlying failure as the `previous` exception, so callers can map
 * it back to the exact user-facing copy they used to produce for that status.
 */
class AiAllProvidersFailedException extends RuntimeException
{
    /**
     * @param  array<string, string>  $errorsByProvider  Provider name => message.
     * @param  Throwable  $last  Failure of the final attempt.
     */
    public function __construct(
        private readonly array $errorsByProvider,
        private readonly Throwable $last,
    ) {
        parent::__construct($last->getMessage(), 0, $last);
    }

    /** The failure of the final attempt, also reachable via `getPrevious()`. */
    public function lastException(): Throwable
    {
        return $this->last;
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errorsByProvider;
    }
}
