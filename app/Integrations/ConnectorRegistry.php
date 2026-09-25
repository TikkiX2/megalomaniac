<?php

namespace App\Integrations;

use App\Exceptions\Integrations\UnknownConnectorException;
use App\Integrations\Contracts\Connector;

final class ConnectorRegistry
{
    /** @var array<string, Connector> */
    private array $connectors = [];

    /**
     * @param  array<class-string<Connector>>  $classes
     */
    public function __construct(array $classes = [])
    {
        foreach ($classes as $class) {
            $this->register($class);
        }
    }

    /**
     * @param  class-string<Connector>  $class
     */
    public function register(string $class): void
    {
        $connector = app($class);
        $this->connectors[$connector->kind()] = $connector;
    }

    public function has(string $kind): bool
    {
        return isset($this->connectors[$kind]);
    }

    public function for(string $kind): Connector
    {
        return $this->connectors[$kind] ?? throw UnknownConnectorException::for($kind);
    }

    /**
     * @return array<string, Connector>
     */
    public function all(): array
    {
        return $this->connectors;
    }
}
