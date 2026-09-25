<?php

namespace App\Integrations\Connectors\Jellyfin;

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

class JellyfinConnector extends AbstractConnector
{
    public function kind(): string
    {
        return 'jellyfin';
    }

    public function label(): string
    {
        return 'Jellyfin';
    }

    public function group(): string
    {
        return 'Media';
    }

    public function description(): string
    {
        return 'Servidor multimedia: biblioteca, sesiones y tareas.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'http://localhost:8096';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'api_key',
                type: 'password',
                label: 'API Key',
                help: 'Dashboard → API Keys en Jellyfin.',
            ),
        ];
    }

    public function actions(): array
    {
        return [
            new Action('system.info', 'Ver sistema', 'Información del servidor', ActionAccess::Read),
            new Action('items.list', 'Listar biblioteca', 'Lista items de la biblioteca', ActionAccess::Read, [
                new Param('types', 'string', false, 'Tipos', default: 'Movie,Series'),
                new Param('limit', 'integer', false, 'Cantidad de resultados', default: 50),
            ]),
            new Action('items.search', 'Buscar items', 'Busca en la biblioteca', ActionAccess::Read, [
                new Param('search_term', 'string', true, 'Texto de búsqueda'),
                new Param('types', 'string', false, 'Tipos', default: 'Movie,Series'),
            ]),
            new Action('sessions.list', 'Ver sesiones', 'Sesiones activas', ActionAccess::Read),
            new Action('users.list', 'Listar usuarios', 'Usuarios del servidor', ActionAccess::Read),
            new Action('library.refresh', 'Refrescar biblioteca', 'Lanza un refresh de biblioteca', ActionAccess::Write),
            new Action('items.favorite', 'Marcar favorito', 'Marca un item como favorito', ActionAccess::Write, [
                new Param('user_id', 'string', true, 'ID de usuario'),
                new Param('item_id', 'string', true, 'ID del item'),
            ]),
            new Action('tasks.list', 'Ver tareas', 'Tareas programadas', ActionAccess::Read),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        return match ($key) {
            'system.info' => $this->result($this->api($connection, 'GET', 'System/Info'), 'Sistema obtenido.'),
            'items.list' => $this->result(
                $this->api($connection, 'GET', 'Items', array_filter([
                    'IncludeItemTypes' => $params['types'] ?? 'Movie,Series',
                    'Limit' => $params['limit'] ?? 50,
                    'Recursive' => 'true',
                ], fn (mixed $value): bool => $value !== null)),
                'Biblioteca listada.',
            ),
            'items.search' => $this->result(
                $this->api($connection, 'GET', 'Items', array_filter([
                    'SearchTerm' => $params['search_term'],
                    'IncludeItemTypes' => $params['types'] ?? 'Movie,Series',
                    'Recursive' => 'true',
                ], fn (mixed $value): bool => $value !== null)),
                'Búsqueda completada.',
            ),
            'sessions.list' => $this->result($this->api($connection, 'GET', 'Sessions'), 'Sesiones listadas.'),
            'users.list' => $this->result($this->api($connection, 'GET', 'Users'), 'Usuarios listados.'),
            'library.refresh' => $this->result($this->api($connection, 'POST', 'Library/Refresh'), 'Refresh lanzado.'),
            'items.favorite' => $this->result(
                $this->api($connection, 'POST', "Users/{$params['user_id']}/FavoriteItems/{$params['item_id']}"),
                'Favorito actualizado.',
            ),
            'tasks.list' => $this->result($this->api($connection, 'GET', 'ScheduledTasks'), 'Tareas listadas.'),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $response = $this->api($connection, 'GET', 'System/Info');

        return $response->ok
            ? ConnectionTestResult::ok('Jellyfin OK', ['version' => $response->data['Version'] ?? null])
            : ConnectionTestResult::fail($response->error ?? 'Jellyfin no respondió.');
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
            headers: ['Authorization' => 'MediaBrowser Token="'.($connection->credentials['api_key'] ?? '').'"'],
        ));
    }
}
