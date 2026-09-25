<?php

namespace App\Integrations\Connectors\Arr;

use App\Integrations\Connectors\AbstractConnector;
use App\Integrations\Transports\HttpCall;
use App\Integrations\Transports\HttpResult;
use App\Models\Connection;

abstract class AbstractArrConnector extends AbstractConnector
{
    public function group(): string
    {
        return 'Media';
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function arrRequest(Connection $connection, string $method, string $path, array $query = []): HttpResult
    {
        return $this->request($connection, new HttpCall(
            method: $method,
            path: ltrim($path, '/'),
            query: $query,
            headers: ['X-Api-Key' => (string) ($connection->credentials['api_key'] ?? '')],
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function arrJson(Connection $connection, string $method, string $path, array $payload = []): HttpResult
    {
        return $this->request($connection, new HttpCall(
            method: $method,
            path: ltrim($path, '/'),
            json: $payload,
            headers: ['X-Api-Key' => (string) ($connection->credentials['api_key'] ?? '')],
        ));
    }
}
