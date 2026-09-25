<?php

namespace App\Integrations\Connectors\Arr;

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
use Illuminate\Support\Facades\Cache;

class QbittorrentConnector extends AbstractConnector
{
    public function kind(): string
    {
        return 'qbittorrent';
    }

    public function label(): string
    {
        return 'qBittorrent';
    }

    public function group(): string
    {
        return 'Media';
    }

    public function description(): string
    {
        return 'Torrents: lista, agregar, pausar y borrar.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'http://localhost:8080';
    }

    public function authFields(): array
    {
        return [
            new AuthField(name: 'username', type: 'text', label: 'Usuario'),
            new AuthField(name: 'password', type: 'password', label: 'Contraseña'),
        ];
    }

    public function actions(): array
    {
        return [
            new Action('torrents.list', 'Listar torrents', 'Lista torrents', ActionAccess::Read, [
                new Param('filter', 'string', false, 'Filtro', enum: ['all', 'downloading', 'seeding', 'paused', 'completed']),
                new Param('category', 'string', false, 'Categoría'),
            ]),
            new Action('torrents.add', 'Agregar torrent', 'Agrega un torrent por URL o magnet', ActionAccess::Write, [
                new Param('urls', 'string', true, 'URL o magnet'),
                new Param('category', 'string', false, 'Categoría'),
                new Param('paused', 'boolean', false, 'Agregar pausado', default: false),
            ]),
            new Action('torrents.pause', 'Pausar torrents', 'Pausa torrents', ActionAccess::Write, [
                new Param('hashes', 'string', true, 'Hashes separados por |'),
            ]),
            new Action('torrents.resume', 'Reanudar torrents', 'Reanuda torrents', ActionAccess::Write, [
                new Param('hashes', 'string', true, 'Hashes separados por |'),
            ]),
            new Action('torrents.delete', 'Eliminar torrents', 'Elimina torrents', ActionAccess::Destructive, [
                new Param('hashes', 'string', true, 'Hashes separados por |'),
                new Param('delete_files', 'boolean', false, 'Borrar archivos', default: false),
            ]),
            new Action('transfer.info', 'Ver transferencia', 'Estadísticas de transferencia', ActionAccess::Read),
            new Action('categories.list', 'Listar categorías', 'Categorías de torrents', ActionAccess::Read),
            new Action('system.status', 'Ver versión', 'Versión de qBittorrent', ActionAccess::Read),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        return match ($key) {
            'torrents.list' => $this->result(
                $this->api($connection, 'GET', 'api/v2/torrents/info', array_filter([
                    'filter' => $params['filter'] ?? null,
                    'category' => $params['category'] ?? null,
                ], fn (mixed $value): bool => $value !== null)),
                'Torrents listados.',
            ),
            'torrents.add' => $this->form($connection, 'api/v2/torrents/add', array_filter([
                'urls' => $params['urls'],
                'category' => $params['category'] ?? null,
                'paused' => ($params['paused'] ?? false) ? 'true' : 'false',
            ], fn (mixed $value): bool => $value !== null), 'Torrent agregado.'),
            'torrents.pause' => $this->form($connection, 'api/v2/torrents/pause', ['hashes' => $params['hashes']], 'Torrents pausados.'),
            'torrents.resume' => $this->form($connection, 'api/v2/torrents/resume', ['hashes' => $params['hashes']], 'Torrents reanudados.'),
            'torrents.delete' => $this->form($connection, 'api/v2/torrents/delete', [
                'hashes' => $params['hashes'],
                'deleteFiles' => ($params['delete_files'] ?? false) ? 'true' : 'false',
            ], 'Torrents eliminados.'),
            'transfer.info' => $this->result($this->api($connection, 'GET', 'api/v2/transfer/info'), 'Transferencia obtenida.'),
            'categories.list' => $this->result($this->api($connection, 'GET', 'api/v2/torrents/categories'), 'Categorías listadas.'),
            'system.status' => $this->result($this->api($connection, 'GET', 'api/v2/app/version'), 'Versión obtenida.'),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $response = $this->api($connection, 'GET', 'api/v2/app/version');

        return $response->ok
            ? ConnectionTestResult::ok('qBittorrent OK', ['version' => trim($response->body)])
            : ConnectionTestResult::fail($response->error ?? 'qBittorrent no respondió.');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function api(Connection $connection, string $method, string $path, array $query = []): HttpResult
    {
        return $this->request($connection, new HttpCall(
            method: $method,
            path: $path,
            query: $query,
            headers: ['Cookie' => 'SID='.$this->sessionCookie($connection)],
        ));
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function form(Connection $connection, string $path, array $fields, string $success): ActionResult
    {
        $response = $this->request($connection, new HttpCall(
            method: 'POST',
            path: $path,
            body: http_build_query($fields),
            contentType: 'application/x-www-form-urlencoded',
            headers: ['Cookie' => 'SID='.$this->sessionCookie($connection)],
        ));

        return $response->ok
            ? ActionResult::success($success, ['response' => trim($response->body)])
            : ActionResult::failure($response->error ?? 'La acción falló.');
    }

    protected function sessionCookie(Connection $connection): string
    {
        return Cache::remember("qbittorrent:sid:{$connection->id}", 1800, function () use ($connection): string {
            $credentials = $connection->credentials ?? [];

            $response = $this->request($connection, new HttpCall(
                method: 'POST',
                path: 'api/v2/auth/login',
                body: http_build_query([
                    'username' => $credentials['username'] ?? 'admin',
                    'password' => $credentials['password'] ?? '',
                ]),
                contentType: 'application/x-www-form-urlencoded',
            ));

            preg_match('/SID=([^;]+)/', (string) ($response->headers['Set-Cookie'] ?? ''), $matches);

            return $matches[1] ?? '';
        });
    }
}
