<?php

namespace App\Integrations\Transports;

use App\Models\Connection;

final class SshCommandBuilder
{
    /**
     * @return string[]
     */
    public static function tunnelCommand(Connection $connection, int $localPort): array
    {
        $config = $connection->transport_config ?? [];

        $command = [
            'ssh', '-N',
            '-o', 'BatchMode=yes',
            '-o', 'ExitOnForwardFailure=yes',
            '-o', 'ConnectTimeout='.config('integrations.ssh.connect_timeout'),
            '-p', (string) ($config['ssh_port'] ?? 22),
        ];

        if (! empty($config['key_path'])) {
            $command[] = '-i';
            $command[] = $config['key_path'];
        }

        $command[] = '-L';
        $command[] = sprintf(
            '127.0.0.1:%d:%s:%d',
            $localPort,
            $config['remote_host'] ?? '127.0.0.1',
            $config['remote_port'] ?? 80,
        );
        $command[] = ($config['ssh_user'] ?? 'root').'@'.($config['ssh_host'] ?? '');

        return $command;
    }

    public static function execCommand(Connection $connection, string $remoteCommand): string
    {
        $config = $connection->transport_config ?? [];

        $parts = [
            'ssh',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout='.config('integrations.ssh.connect_timeout'),
            '-p', (string) ($config['ssh_port'] ?? 22),
        ];

        if (! empty($config['key_path'])) {
            $parts[] = '-i';
            $parts[] = escapeshellarg($config['key_path']);
        }

        $parts[] = escapeshellarg(($config['ssh_user'] ?? 'root').'@'.($config['ssh_host'] ?? ''));
        $parts[] = '--';
        $parts[] = escapeshellarg($remoteCommand);

        return implode(' ', $parts);
    }
}
