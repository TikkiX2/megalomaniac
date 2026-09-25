<?php

namespace App\Integrations\Connectors;

use App\Integrations\Actions\ActionResult;
use App\Integrations\Contracts\Connector;
use App\Integrations\Transports\ExecResult;
use App\Integrations\Transports\HttpCall;
use App\Integrations\Transports\HttpResult;
use App\Integrations\Transports\TransportFactory;
use App\Models\Connection;

abstract class AbstractConnector implements Connector
{
    public function group(): string
    {
        return 'Otros';
    }

    public function transports(): array
    {
        return ['direct'];
    }

    public function defaultBaseUrl(): ?string
    {
        return null;
    }

    protected function request(Connection $connection, HttpCall $call): HttpResult
    {
        if (blank($connection->base_url) && $this->defaultBaseUrl() !== null) {
            $connection = clone $connection;
            $connection->base_url = $this->defaultBaseUrl();
        }

        return TransportFactory::make($connection)->request($connection, $call);
    }

    protected function exec(Connection $connection, string $command): ExecResult
    {
        return TransportFactory::make($connection)->exec($connection, $command);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    protected function result(HttpResult $response, string $success, ?array $data = null): ActionResult
    {
        return $response->ok
            ? ActionResult::success($success, $data ?? $response->data)
            : ActionResult::failure($response->error ?? 'La acción falló.');
    }
}
