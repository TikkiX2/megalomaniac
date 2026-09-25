<?php

namespace App\Integrations\Transports;

use App\Integrations\Enums\TransportKind;
use App\Models\Connection;

final class TransportFactory
{
    public static function make(Connection $connection): Transport
    {
        return match ($connection->transport) {
            TransportKind::LocalSocket => app(LocalSocketTransport::class),
            TransportKind::SshTunnel => app(SshTunnelTransport::class),
            TransportKind::SshExec => app(SshExecTransport::class),
            default => app(DirectTransport::class),
        };
    }
}
