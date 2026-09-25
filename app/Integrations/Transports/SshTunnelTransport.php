<?php

namespace App\Integrations\Transports;

use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Enums\TransportKind;
use App\Models\Connection;
use Symfony\Component\Process\Process as SymfonyProcess;

class SshTunnelTransport extends DirectTransport
{
    public function request(Connection $connection, HttpCall $call): HttpResult
    {
        $tunnel = $this->openTunnel($connection);

        try {
            $local = $connection->replicate();
            $local->base_url = "http://127.0.0.1:{$tunnel['port']}";
            $local->transport = TransportKind::Direct;

            return parent::request($local, $call);
        } finally {
            if ($tunnel['process'] instanceof SymfonyProcess) {
                $tunnel['process']->stop(1);
            }
        }
    }

    public function health(Connection $connection): ConnectionTestResult
    {
        return ConnectionTestResult::ok('ssh tunnel transport ready');
    }

    /**
     * @return array{port: int, process: SymfonyProcess|null}
     */
    public function openTunnel(Connection $connection): array
    {
        $config = $connection->transport_config ?? [];
        $port = (int) ($config['local_port'] ?? $this->freePort());

        $process = new SymfonyProcess(SshCommandBuilder::tunnelCommand($connection, $port));
        $process->setTimeout(null);
        $process->start();

        $this->waitForPort($port);

        return ['port' => $port, 'process' => $process];
    }

    protected function waitForPort(int $port, int $attempts = 50): void
    {
        for ($i = 0; $i < $attempts; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(100_000);
        }
    }

    protected function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
