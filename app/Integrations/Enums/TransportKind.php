<?php

namespace App\Integrations\Enums;

enum TransportKind: string
{
    case Direct = 'direct';
    case LocalSocket = 'local_socket';
    case SshTunnel = 'ssh_tunnel';
    case SshExec = 'ssh_exec';
}
