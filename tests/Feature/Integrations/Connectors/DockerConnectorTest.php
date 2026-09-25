<?php

use App\Integrations\Connectors\Docker\DockerConnector;
use App\Integrations\Enums\ActionAccess;
use App\Integrations\Enums\TransportKind;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

function docker(): Connection
{
    return Connection::factory()->make([
        'kind' => 'docker',
        'auth_type' => 'none',
        'base_url' => 'http://localhost',
        'transport' => TransportKind::LocalSocket,
        'transport_config' => ['socket_path' => '/var/run/docker.sock'],
    ]);
}

it('declares docker actions and transports', function () {
    $connector = new DockerConnector;

    expect(collect($connector->actions())->pluck('key'))->toContain(
        'containers.list', 'containers.inspect', 'containers.logs', 'containers.restart',
        'containers.remove', 'images.list', 'images.prune', 'system.df',
    )
        ->and($connector->transports())->toBe(['local_socket', 'ssh_exec', 'direct'])
        ->and(collect($connector->actions())->firstWhere('key', 'containers.remove')->access)->toBe(ActionAccess::Destructive);
});

it('lists containers over the docker socket', function () {
    Http::fake(['localhost/*' => Http::response([['Names' => ['/jellyfin']]], 200)]);

    $result = (new DockerConnector)->execute(docker(), 'containers.list', ['all' => true]);

    expect($result->ok)->toBeTrue()->and($result->data[0]['Names'][0])->toBe('/jellyfin');
});

it('restarts a container via the api', function () {
    Http::fake(['localhost/*' => Http::response('', 204)]);

    $result = (new DockerConnector)->execute(docker(), 'containers.restart', ['name' => 'jellyfin']);

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/containers/jellyfin/restart'));
});

it('reads container logs', function () {
    Http::fake(['localhost/*' => Http::response('log line', 200)]);

    $result = (new DockerConnector)->execute(docker(), 'containers.logs', ['name' => 'jellyfin', 'tail' => 50]);

    expect($result->ok)->toBeTrue()
        ->and($result->data['output'])->toContain('log line');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/containers/jellyfin/logs')
        && str_contains($request->url(), 'tail=50'));
});

it('lists containers via ssh exec on remote hosts', function () {
    Process::fake(['*' => Process::result(output: "{\"Names\":\"jellyfin\"}\n", exitCode: 0)]);

    $connection = Connection::factory()->make([
        'kind' => 'docker',
        'auth_type' => 'none',
        'transport' => TransportKind::SshExec,
        'transport_config' => ['ssh_host' => '10.0.0.9', 'ssh_user' => 'root', 'key_path' => '/k'],
    ]);

    $result = (new DockerConnector)->execute($connection, 'containers.list', []);

    expect($result->ok)->toBeTrue()->and($result->data[0]['Names'])->toBe('jellyfin');
});

it('reports docker errors', function () {
    Http::fake(['localhost/*' => Http::response(['message' => 'No such container'], 404)]);

    $result = (new DockerConnector)->execute(docker(), 'containers.restart', ['name' => 'nope']);

    expect($result->ok)->toBeFalse()->and($result->error)->toContain('404');
});

it('tests the connection against the ping endpoint', function () {
    Http::fake(['localhost/_ping' => Http::response('OK', 200)]);

    $result = (new DockerConnector)->test(docker());

    expect($result->ok)->toBeTrue();
});
