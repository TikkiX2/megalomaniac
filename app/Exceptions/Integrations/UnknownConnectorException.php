<?php

namespace App\Exceptions\Integrations;

use RuntimeException;

class UnknownConnectorException extends RuntimeException
{
    public static function for(string $kind): self
    {
        return new self("Unknown integration connector [{$kind}].");
    }
}
