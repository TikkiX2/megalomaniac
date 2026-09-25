<?php

namespace App\Integrations\Connectors\Docker;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\Connectors\AbstractConnector;
use App\Integrations\Enums\ActionAccess;
use App\Integrations\Enums\TransportKind;
use App\Integrations\Transports\HttpCall;
use App\Integrations\Transports\HttpResult;
use App\Models\Connection;

class DockerConnector extends AbstractConnector
{
    public function kind(): string
    {
        return 'docker';
    }

    public function label(): string
    {
        return 'Docker';
    }

    public function group(): string
    {
        return 'Infra';
    }

    public function description(): string
    {
        return 'Contenedores, imágenes, volúmenes y red, local o por SSH.';
    }

    public function authFields(): array
    {
        return [];
    }

    public function transports(): array
    {
        return ['local_socket', 'ssh_exec', 'direct'];
    }

    public function defaultBaseUrl(): ?string
    {
        return 'http://localhost';
    }

    public function actions(): array
    {
        $name = fn (): Param => new Param('name', 'string', true, 'Nombre o ID del contenedor');
        $tail = fn (): Param => new Param('tail', 'integer', false, 'Últimas N líneas', default: 200);

        return [
            new Action('containers.list', 'Listar contenedores', 'Lista contenedores (incluye detenidos)', ActionAccess::Read, [
                new Param('all', 'boolean', false, 'Incluir detenidos', default: true),
            ]),
            new Action('containers.inspect', 'Inspeccionar contenedor', 'Detalle completo de un contenedor', ActionAccess::Read, [$name()]),
            new Action('containers.logs', 'Ver logs', 'Últimas líneas de logs de un contenedor', ActionAccess::Read, [$name(), $tail()]),
            new Action('containers.start', 'Iniciar contenedor', 'Inicia un contenedor', ActionAccess::Write, [$name()]),
            new Action('containers.stop', 'Detener contenedor', 'Detiene un contenedor', ActionAccess::Write, [$name()]),
            new Action('containers.restart', 'Reiniciar contenedor', 'Reinicia un contenedor', ActionAccess::Write, [$name()]),
            new Action('containers.pause', 'Pausar contenedor', 'Pausa un contenedor', ActionAccess::Write, [$name()]),
            new Action('containers.unpause', 'Reanudar contenedor', 'Reanuda un contenedor', ActionAccess::Write, [$name()]),
            new Action('containers.remove', 'Eliminar contenedor', 'Elimina un contenedor', ActionAccess::Destructive, [
                $name(),
                new Param('force', 'boolean', false, 'Forzar', default: false),
            ]),
            new Action('images.list', 'Listar imágenes', 'Lista imágenes locales', ActionAccess::Read),
            new Action('images.prune', 'Limpiar imágenes', 'Elimina imágenes sin uso', ActionAccess::Destructive),
            new Action('volumes.list', 'Listar volúmenes', 'Lista volúmenes', ActionAccess::Read),
            new Action('networks.list', 'Listar redes', 'Lista redes', ActionAccess::Read),
            new Action('system.df', 'Uso de disco', 'Resumen de uso de disco', ActionAccess::Read),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        return $connection->transport === TransportKind::SshExec
            ? $this->viaSsh($connection, $key, $params)
            : $this->viaApi($connection, $key, $params);
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        if ($connection->transport === TransportKind::SshExec) {
            $result = $this->exec($connection, 'docker version --format "{{.Server.Version}}"');

            return $result->ok
                ? ConnectionTestResult::ok('Docker OK (SSH)', ['output' => trim($result->output)])
                : ConnectionTestResult::fail($result->error ?? 'Docker no respondió por SSH.');
        }

        $response = $this->request($connection, new HttpCall('GET', '_ping'));

        return $response->ok
            ? ConnectionTestResult::ok('Docker OK', ['ping' => trim($response->body)])
            : ConnectionTestResult::fail($response->error ?? 'Docker no respondió.');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function viaApi(Connection $connection, string $key, array $params): ActionResult
    {
        $name = $this->safeName((string) ($params['name'] ?? ''));
        $tail = (int) ($params['tail'] ?? 200);

        return match ($key) {
            'containers.list' => $this->result(
                $this->request($connection, new HttpCall('GET', 'containers/json', query: [
                    'all' => ($params['all'] ?? true) ? 1 : 0,
                ])),
                'Contenedores listados.',
            ),
            'containers.inspect' => $this->result(
                $this->request($connection, new HttpCall('GET', "containers/{$name}/json")),
                'Contenedor inspeccionado.',
            ),
            'containers.logs' => $this->logs(
                $this->request($connection, new HttpCall('GET', "containers/{$name}/logs", query: [
                    'stdout' => 1, 'stderr' => 1, 'tail' => $tail,
                ])),
            ),
            'containers.start' => $this->result(
                $this->request($connection, new HttpCall('POST', "containers/{$name}/start")),
                'Contenedor iniciado.',
            ),
            'containers.stop' => $this->result(
                $this->request($connection, new HttpCall('POST', "containers/{$name}/stop")),
                'Contenedor detenido.',
            ),
            'containers.restart' => $this->result(
                $this->request($connection, new HttpCall('POST', "containers/{$name}/restart")),
                'Contenedor reiniciado.',
            ),
            'containers.pause' => $this->result(
                $this->request($connection, new HttpCall('POST', "containers/{$name}/pause")),
                'Contenedor pausado.',
            ),
            'containers.unpause' => $this->result(
                $this->request($connection, new HttpCall('POST', "containers/{$name}/unpause")),
                'Contenedor reanudado.',
            ),
            'containers.remove' => $this->result(
                $this->request($connection, new HttpCall('DELETE', "containers/{$name}", query: [
                    'force' => ($params['force'] ?? false) ? 1 : 0,
                ])),
                'Contenedor eliminado.',
            ),
            'images.list' => $this->result($this->request($connection, new HttpCall('GET', 'images/json')), 'Imágenes listadas.'),
            'images.prune' => $this->result($this->request($connection, new HttpCall('POST', 'images/prune')), 'Imágenes sin uso eliminadas.'),
            'volumes.list' => $this->result($this->request($connection, new HttpCall('GET', 'volumes')), 'Volúmenes listados.'),
            'networks.list' => $this->result($this->request($connection, new HttpCall('GET', 'networks')), 'Redes listadas.'),
            'system.df' => $this->result($this->request($connection, new HttpCall('GET', 'system/df')), 'Uso de disco obtenido.'),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function viaSsh(Connection $connection, string $key, array $params): ActionResult
    {
        $name = $this->safeName((string) ($params['name'] ?? ''));
        $tail = (int) ($params['tail'] ?? 200);

        return match ($key) {
            'containers.list' => $this->sshJson($connection, "docker ps --all --format '{{json .}}'", 'Contenedores listados.'),
            'containers.inspect' => $this->sshJson($connection, "docker inspect {$name}", 'Contenedor inspeccionado.'),
            'containers.logs' => $this->sshText($connection, "docker logs --tail {$tail} {$name}", 'Logs obtenidos.'),
            'containers.start' => $this->sshText($connection, "docker start {$name}", 'Contenedor iniciado.'),
            'containers.stop' => $this->sshText($connection, "docker stop {$name}", 'Contenedor detenido.'),
            'containers.restart' => $this->sshText($connection, "docker restart {$name}", 'Contenedor reiniciado.'),
            'containers.pause' => $this->sshText($connection, "docker pause {$name}", 'Contenedor pausado.'),
            'containers.unpause' => $this->sshText($connection, "docker unpause {$name}", 'Contenedor reanudado.'),
            'containers.remove' => $this->sshText(
                $connection,
                ($params['force'] ?? false) ? "docker rm -f {$name}" : "docker rm {$name}",
                'Contenedor eliminado.',
            ),
            'images.list' => $this->sshJson($connection, "docker images --format '{{json .}}'", 'Imágenes listadas.'),
            'images.prune' => $this->sshText($connection, 'docker image prune -f', 'Imágenes sin uso eliminadas.'),
            'volumes.list' => $this->sshJson($connection, "docker volume ls --format '{{json .}}'", 'Volúmenes listados.'),
            'networks.list' => $this->sshJson($connection, "docker network ls --format '{{json .}}'", 'Redes listadas.'),
            'system.df' => $this->sshText($connection, 'docker system df', 'Uso de disco obtenido.'),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    protected function logs(HttpResult $response): ActionResult
    {
        return $response->ok
            ? ActionResult::success('Logs obtenidos.', ['output' => substr($response->body, 0, 20000)])
            : ActionResult::failure($response->error ?? 'No se pudieron obtener los logs.');
    }

    protected function sshJson(Connection $connection, string $command, string $success): ActionResult
    {
        $result = $this->exec($connection, $command);

        if (! $result->ok) {
            return ActionResult::failure($result->error ?? 'La acción falló.');
        }

        $data = [];

        foreach (array_filter(explode("\n", trim($result->output))) as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $data[] = $decoded;
            }
        }

        return ActionResult::success($success, $data);
    }

    protected function sshText(Connection $connection, string $command, string $success): ActionResult
    {
        $result = $this->exec($connection, $command);

        return $result->ok
            ? ActionResult::success($success, ['output' => substr($result->output, 0, 20000)])
            : ActionResult::failure($result->error ?? 'La acción falló.');
    }

    protected function safeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_.\-\/]/', '', $name) ?? '';
    }
}
