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
    /**
     * HTTP status that produced the failure (401/403 flag an expired session
     * credential when the source uses one), or null for transport failures.
     */
    public ?int $httpStatus = null;

    public function __construct(
        string $message = '',
        public readonly ?int $previousCacheAge = null,
        int $code = 0,
        ?Throwable $previous = null,
        ?int $httpStatus = null,
    ) {
        parent::__construct($message, $code, $previous);

        $this->httpStatus = $httpStatus;
    }
}
