<?php

namespace App\Integrations\Transports;

use App\Integrations\Actions\ConnectionTestResult;
use App\Models\Connection;
use Illuminate\Http\Client\PendingRequest;

class LocalSocketTransport extends DirectTransport
{
    /**
     * @return array<int, string>
     */
    public function curlOptions(Connection $connection): array
    {
        return [
            CURLOPT_UNIX_SOCKET_PATH => $connection->transport_config['socket_path'] ?? '/var/run/docker.sock',
        ];
    }

    public function health(Connection $connection): ConnectionTestResult
    {
        $socket = $connection->transport_config['socket_path'] ?? '/var/run/docker.sock';

        return file_exists($socket)
            ? ConnectionTestResult::ok("socket {$socket} presente")
            : ConnectionTestResult::fail("socket {$socket} no encontrado");
    }

    protected function client(Connection $connection, HttpCall $call): PendingRequest
    {
        return parent::client($connection, $call)->withOptions([
            'curl' => $this->curlOptions($connection),
        ]);
    }
}
