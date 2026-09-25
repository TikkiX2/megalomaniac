<?php

namespace App\Integrations\Connectors\Proxmox;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\AuthField;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\Connectors\AbstractConnector;
use App\Integrations\Enums\ActionAccess;
use App\Integrations\Transports\HttpCall;
use App\Integrations\Transports\HttpResult;
use App\Models\Connection;

class ProxmoxConnector extends AbstractConnector
{
    public function kind(): string
    {
        return 'proxmox';
    }

    public function label(): string
    {
        return 'Proxmox VE';
    }

    public function group(): string
    {
        return 'Infra';
    }

    public function description(): string
    {
        return 'VMs y contenedores LXC: estado, encendido, snapshots y tareas.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'https://localhost:8006';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'token_id',
                type: 'text',
                label: 'Token ID',
                help: 'Formato USUARIO@REALM!TOKENID, p. ej. root@pam!megalomaniac.',
            ),
            new AuthField(name: 'token_secret', type: 'password', label: 'Token Secret'),
        ];
    }

    public function actions(): array
    {
        $node = fn (): Param => new Param('node', 'string', true, 'Nodo');
        $vmid = fn (): Param => new Param('vmid', 'integer', true, 'ID de VM/LXC');

        return [
            new Action('nodes.list', 'Listar nodos', 'Nodos del clúster', ActionAccess::Read),
            new Action('nodes.status', 'Ver nodo', 'Estado de un nodo', ActionAccess::Read, [$node()]),
            new Action('qemu.list', 'Listar VMs', 'VMs del nodo', ActionAccess::Read, [$node()]),
            new Action('qemu.status', 'Ver estado de VM', 'Estado actual de una VM', ActionAccess::Read, [$node(), $vmid()]),
            new Action('qemu.start', 'Encender VM', 'Enciende una VM', ActionAccess::Write, [$node(), $vmid()]),
            new Action('qemu.shutdown', 'Apagar VM', 'Apaga una VM (shutdown limpio)', ActionAccess::Write, [$node(), $vmid()]),
            new Action('qemu.reboot', 'Reiniciar VM', 'Reinicia una VM', ActionAccess::Write, [$node(), $vmid()]),
            new Action('qemu.snapshots', 'Listar snapshots', 'Snapshots de una VM', ActionAccess::Read, [$node(), $vmid()]),
            new Action('qemu.snapshot.create', 'Crear snapshot', 'Crea un snapshot', ActionAccess::Write, [
                $node(), $vmid(),
                new Param('snapname', 'string', true, 'Nombre del snapshot'),
            ]),
            new Action('qemu.snapshot.rollback', 'Rollback snapshot', 'Vuelve a un snapshot', ActionAccess::Destructive, [
                $node(), $vmid(),
                new Param('snapname', 'string', true, 'Nombre del snapshot'),
            ]),
            new Action('lxc.list', 'Listar LXC', 'Contenedores del nodo', ActionAccess::Read, [$node()]),
            new Action('lxc.status', 'Ver estado de LXC', 'Estado de un contenedor', ActionAccess::Read, [$node(), $vmid()]),
            new Action('lxc.start', 'Encender LXC', 'Enciende un contenedor', ActionAccess::Write, [$node(), $vmid()]),
            new Action('lxc.shutdown', 'Apagar LXC', 'Apaga un contenedor', ActionAccess::Write, [$node(), $vmid()]),
            new Action('tasks.list', 'Listar tareas', 'Tareas recientes del nodo', ActionAccess::Read, [
                $node(),
                new Param('limit', 'integer', false, 'Cantidad de resultados', default: 25),
            ]),
            new Action('storage.list', 'Listar storage', 'Storages del nodo', ActionAccess::Read, [$node()]),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        $node = $params['node'] ?? '';
        $vmid = $params['vmid'] ?? 0;

        return match ($key) {
            'nodes.list' => $this->unwrap($this->api($connection, 'GET', 'api2/json/nodes'), 'Nodos listados.'),
            'nodes.status' => $this->unwrap($this->api($connection, 'GET', "api2/json/nodes/{$node}/status"), 'Nodo obtenido.'),
            'qemu.list' => $this->unwrap($this->api($connection, 'GET', "api2/json/nodes/{$node}/qemu"), 'VMs listadas.'),
            'qemu.status' => $this->unwrap($this->api($connection, 'GET', "api2/json/nodes/{$node}/qemu/{$vmid}/status/current"), 'Estado obtenido.'),
            'qemu.start' => $this->unwrap($this->api($connection, 'POST', "api2/json/nodes/{$node}/qemu/{$vmid}/status/start"), 'VM encendida.'),
            'qemu.shutdown' => $this->unwrap($this->api($connection, 'POST', "api2/json/nodes/{$node}/qemu/{$vmid}/status/shutdown"), 'VM apagada.'),
            'qemu.reboot' => $this->unwrap($this->api($connection, 'POST', "api2/json/nodes/{$node}/qemu/{$vmid}/status/reboot"), 'VM reiniciada.'),
            'qemu.snapshots' => $this->unwrap($this->api($connection, 'GET', "api2/json/nodes/{$node}/qemu/{$vmid}/snapshot"), 'Snapshots listados.'),
            'qemu.snapshot.create' => $this->unwrap(
                $this->api($connection, 'POST', "api2/json/nodes/{$node}/qemu/{$vmid}/snapshot", ['snapname' => $params['snapname']]),
                'Snapshot creado.',
            ),
            'qemu.snapshot.rollback' => $this->unwrap(
                $this->api($connection, 'POST', "api2/json/nodes/{$node}/qemu/{$vmid}/snapshot/{$params['snapname']}/rollback"),
                'Rollback completado.',
            ),
            'lxc.list' => $this->unwrap($this->api($connection, 'GET', "api2/json/nodes/{$node}/lxc"), 'Contenedores listados.'),
            'lxc.status' => $this->unwrap($this->api($connection, 'GET', "api2/json/nodes/{$node}/lxc/{$vmid}/status/current"), 'Estado obtenido.'),
            'lxc.start' => $this->unwrap($this->api($connection, 'POST', "api2/json/nodes/{$node}/lxc/{$vmid}/status/start"), 'Contenedor encendido.'),
            'lxc.shutdown' => $this->unwrap($this->api($connection, 'POST', "api2/json/nodes/{$node}/lxc/{$vmid}/status/shutdown"), 'Contenedor apagado.'),
            'tasks.list' => $this->unwrap(
                $this->api($connection, 'GET', "api2/json/nodes/{$node}/tasks", ['limit' => $params['limit'] ?? 25]),
                'Tareas listadas.',
            ),
            'storage.list' => $this->unwrap($this->api($connection, 'GET', "api2/json/nodes/{$node}/storage"), 'Storages listados.'),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $response = $this->api($connection, 'GET', 'api2/json/version');
        $data = $response->data['data'] ?? [];

        return $response->ok
            ? ConnectionTestResult::ok('Proxmox OK', ['version' => $data['version'] ?? null])
            : ConnectionTestResult::fail($response->error ?? 'Proxmox no respondió.');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function api(Connection $connection, string $method, string $path, array $query = []): HttpResult
    {
        $credentials = $connection->credentials ?? [];
        $token = ($credentials['token_id'] ?? '').'='.($credentials['token_secret'] ?? '');

        return $this->request($connection, new HttpCall(
            method: $method,
            path: $path,
            query: $query,
            headers: ['Authorization' => 'PVEAPIToken='.$token],
        ));
    }

    protected function unwrap(HttpResult $response, string $success): ActionResult
    {
        if (! $response->ok) {
            return ActionResult::failure($response->error ?? 'La acción falló.');
        }

        $data = $response->data['data'] ?? $response->data;

        return ActionResult::success($success, is_array($data) ? $data : ['value' => $data]);
    }
}
