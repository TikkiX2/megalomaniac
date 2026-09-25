<?php

use App\Integrations\Connectors\Proxmox\ProxmoxConnector;
use App\Integrations\Enums\ActionAccess;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function proxmox(): Connection
{
    return Connection::factory()->make([
        'kind' => 'proxmox',
        'base_url' => 'https://pve.local:8006',
        'credentials' => [
            'token_id' => 'root@pam!megalomaniac',
            'token_secret' => 'uuid-secret',
        ],
        'options' => ['verify' => false],
    ]);
}

it('declares the proxmox catalog', function () {
    $connector = new ProxmoxConnector;
    $keys = collect($connector->actions())->pluck('key')->all();

    expect($keys)->toContain('nodes.list', 'qemu.list', 'qemu.start', 'qemu.shutdown', 'qemu.snapshot.rollback', 'tasks.list')
        ->and($connector->authFields()[0]->name)->toBe('token_id')
        ->and(collect($connector->actions())->firstWhere('key', 'qemu.snapshot.rollback')->access)->toBe(ActionAccess::Destructive)
        ->and($connector->defaultBaseUrl())->toBe('https://localhost:8006');
});

it('lists cluster nodes with the api token header', function () {
    Http::fake(['pve.local:8006/api2/json/nodes' => Http::response(['data' => [['node' => 'pve', 'status' => 'online']]], 200)]);

    $result = (new ProxmoxConnector)->execute(proxmox(), 'nodes.list', []);

    expect($result->ok)->toBeTrue()->and($result->data[0]['node'])->toBe('pve');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'PVEAPIToken=root@pam!megalomaniac=uuid-secret'));
});

it('reads a vm status', function () {
    Http::fake(['pve.local:8006/api2/json/nodes/pve/qemu/100/status/current' => Http::response(['data' => ['status' => 'running']], 200)]);

    $result = (new ProxmoxConnector)->execute(proxmox(), 'qemu.status', ['node' => 'pve', 'vmid' => 100]);

    expect($result->ok)->toBeTrue()->and($result->data['status'])->toBe('running');
});

it('starts a vm', function () {
    Http::fake(['pve.local:8006/api2/json/nodes/pve/qemu/100/status/start' => Http::response(['data' => 'UPID:pve:0001'], 200)]);

    $result = (new ProxmoxConnector)->execute(proxmox(), 'qemu.start', ['node' => 'pve', 'vmid' => 100]);

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'qemu/100/status/start'));
});

it('tests the connection via version', function () {
    Http::fake(['pve.local:8006/api2/json/version' => Http::response(['data' => ['version' => '8.2.4']], 200)]);

    $result = (new ProxmoxConnector)->test(proxmox());

    expect($result->ok)->toBeTrue()->and($result->meta['version'])->toBe('8.2.4');
});
