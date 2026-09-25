<?php

namespace App\Exceptions\Integrations;

use RuntimeException;

class UnsupportedTransportException extends RuntimeException
{
    public static function for(string $operation, string $transport): self
    {
        return new self("Transport [{$transport}] does not support [{$operation}].");
    }
}
