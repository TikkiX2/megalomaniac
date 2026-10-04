<?php

declare(strict_types=1);

namespace App\Inspiration\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Raised when a source cannot fulfil a request (HTTP error, timeout, parse
 * failure). Carries the age of the last cached payload, when one exists, so
 * the UI can degrade to "caché · hace Xh".
 */
class SourceException extends RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly ?int $previousCacheAge = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
