<?php

declare(strict_types=1);

namespace App\Inspiration\Exceptions;

use RuntimeException;

/**
 * Raised when the user already hit the per-day full-download cap.
 */
class DownloadQuotaExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('límite diario alcanzado');
    }
}
