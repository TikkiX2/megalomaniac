<?php

namespace App\Integrations\Transports;

use App\Integrations\Actions\ConnectionTestResult;
use App\Models\Connection;

interface Transport
{
    public function request(Connection $connection, HttpCall $call): HttpResult;

    public function exec(Connection $connection, string $command): ExecResult;

    public function health(Connection $connection): ConnectionTestResult;
}
