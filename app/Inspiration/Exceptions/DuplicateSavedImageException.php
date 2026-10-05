<?php

declare(strict_types=1);

namespace App\Inspiration\Exceptions;

use App\Models\SavedImage;
use RuntimeException;

/**
 * Raised when the (user, source, source_id) pair is already saved.
 *
 * The service pre-checks the common case and also translates the database
 * unique violation, so a losing concurrent save is reported as a 409 too.
 */
class DuplicateSavedImageException extends RuntimeException
{
    public function __construct(public readonly SavedImage $existing)
    {
        parent::__construct('La imagen ya está guardada.');
    }
}
