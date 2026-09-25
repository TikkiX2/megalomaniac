<?php

namespace App\Integrations\Contracts;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\AuthField;
use App\Integrations\Actions\ConnectionTestResult;
use App\Models\Connection;

interface Connector
{
    public function kind(): string;

    public function label(): string;

    public function group(): string;

    public function description(): string;

    /** @return AuthField[] */
    public function authFields(): array;

    /** @return string[] */
    public function transports(): array;

    public function defaultBaseUrl(): ?string;

    /** @return Action[] */
    public function actions(): array;

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(Connection $connection, string $key, array $params): ActionResult;

    public function test(Connection $connection): ConnectionTestResult;
}
