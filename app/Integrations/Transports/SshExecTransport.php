<?php

namespace App\Integrations\Transports;

use App\Exceptions\Integrations\UnsupportedTransportException;
use App\Integrations\Actions\ConnectionTestResult;
use App\Models\Connection;
use Illuminate\Support\Facades\Process;
use Throwable;

class SshExecTransport implements Transport
{
    public function request(Connection $connection, HttpCall $call): HttpResult
    {
        throw UnsupportedTransportException::for('request', 'ssh_exec');
    }

    public function exec(Connection $connection, string $command): ExecResult
    {
        $allowed = $connection->transport_config['allowed_commands'] ?? null;

        if (is_array($allowed) && ! $this->isAllowed($command, $allowed)) {
            return new ExecResult(false, -1, '', 'Comando fuera de la allowlist para esta conexión.');
        }

        try {
            $result = Process::timeout(config('integrations.ssh.command_timeout'))
                ->run(SshCommandBuilder::execCommand($connection, $command));

            $cap = (int) config('integrations.ssh.output_cap_bytes');
            $output = substr($result->output(), 0, $cap);

            return new ExecResult(
                $result->successful(),
                $result->exitCode(),
                $output,
                $result->successful() ? null : $result->errorOutput(),
            );
        } catch (Throwable $e) {
            return new ExecResult(false, -1, '', $e->getMessage());
        }
    }

    public function health(Connection $connection): ConnectionTestResult
    {
        $result = $this->exec($connection, 'true');

        return $result->ok
            ? ConnectionTestResult::ok('SSH OK')
            : ConnectionTestResult::fail($result->error ?? 'SSH falló');
    }

    /**
     * @param  string[]  $allowed
     */
    protected function isAllowed(string $command, array $allowed): bool
    {
        foreach ($allowed as $pattern) {
            if ($command === $pattern || str_starts_with($command, $pattern.' ')) {
                return true;
            }
        }

        return false;
    }
}
