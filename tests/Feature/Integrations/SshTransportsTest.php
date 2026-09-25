<?php

use App\Exceptions\Integrations\UnsupportedTransportException;
use App\Integrations\Enums\TransportKind;
use App\Integrations\Transports\HttpCall;
use App\Integrations\Transports\SshCommandBuilder;
use App\Integrations\Transports\SshExecTransport;
use App\Integrations\Transports\SshTunnelTransport;
use App\Models\Connection;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

function sshConnection(array $config = [], array $attributes = []): Connection
{
    return Connection::factory()->make(array_merge([
        'transport' => TransportKind::SshExec,
        'transport_config' => array_merge([
            'ssh_host' => '10.0.0.5',
            'ssh_port' => 22,
            'ssh_user' => 'root',
            'key_path' => '/root/.ssh/id_ed25519',
        ], $config),
    ], $attributes));
}

it('executes a remote command through the ssh exec transport', function () {
    Process::fake([
        '*' => Process::result(output: 'docker-ok', exitCode: 0),
    ]);

    $result = (new SshExecTransport)->exec(sshConnection(), 'docker ps');

    expect($result->ok)->toBeTrue()->and($result->exitCode)->toBe(0)
        ->and(trim($result->output))->toBe('docker-ok');

    Process::assertRan(fn (PendingProcess $process) => str_contains((string) $process->command, 'ssh')
        && str_contains((string) $process->command, 'docker ps')
        && str_contains((string) $process->command, 'root@10.0.0.5'));
});

it('maps ssh failures to a failed result', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'permission denied', exitCode: 255),
    ]);

    $result = (new SshExecTransport)->exec(sshConnection(), 'docker ps');

    expect($result->ok)->toBeFalse()->and($result->exitCode)->toBe(255)
        ->and($result->error)->toContain('permission denied');
});

it('rejects commands outside the allowlist', function () {
    Process::fake();

    $result = (new SshExecTransport)->exec(
        sshConnection(['allowed_commands' => ['docker ps']]),
        'rm -rf /',
    );

    expect($result->ok)->toBeFalse()->and($result->error)->toContain('allowlist');
    Process::assertNothingRan();
});

it('refuses http requests on ssh_exec', function () {
    (new SshExecTransport)->request(sshConnection(), new HttpCall('GET'));
})->throws(UnsupportedTransportException::class);

it('builds the tunnel command with the expected forward and key', function () {
    $command = SshCommandBuilder::tunnelCommand(
        sshConnection(['remote_host' => '127.0.0.1', 'remote_port' => 2375], ['transport' => TransportKind::SshTunnel]),
        54321,
    );

    $joined = implode(' ', $command);

    expect($joined)->toContain('-L 127.0.0.1:54321:127.0.0.1:2375')
        ->and($joined)->toContain('root@10.0.0.5')
        ->and($joined)->toContain('-i /root/.ssh/id_ed25519')
        ->and($joined)->toContain('ExitOnForwardFailure=yes');
});

it('proxies requests through an opened tunnel', function () {
    Http::fake(['127.0.0.1:9999/*' => Http::response(['Version' => '1.0'], 200)]);

    $transport = new class extends SshTunnelTransport
    {
        public function openTunnel(Connection $connection): array
        {
            return ['port' => 9999, 'process' => null];
        }
    };

    $result = $transport->request(
        sshConnection([], ['transport' => TransportKind::SshTunnel, 'base_url' => 'http://docker.internal']),
        new HttpCall('GET', 'version'),
    );

    expect($result->ok)->toBeTrue()->and($result->data)->toBe(['Version' => '1.0']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '127.0.0.1:9999/version'));
});
