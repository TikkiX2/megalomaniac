<?php

namespace App\Integrations\Connectors\Arr;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\AuthField;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\Enums\ActionAccess;
use App\Models\Connection;

class RadarrConnector extends AbstractArrConnector
{
    public function kind(): string
    {
        return 'radarr';
    }

    public function label(): string
    {
        return 'Radarr';
    }

    public function description(): string
    {
        return 'Películas: biblioteca, cola, calendario y búsquedas.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'http://localhost:7878';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'api_key',
                type: 'password',
                label: 'API Key',
                help: 'Settings → General → Security en Radarr.',
            ),
        ];
    }

    public function actions(): array
    {
        $id = fn (): Param => new Param('id', 'integer', true, 'ID de la película');

        return [
            new Action('movies.list', 'Listar películas', 'Lista la biblioteca de películas', ActionAccess::Read),
            new Action('movies.get', 'Ver película', 'Obtiene una película', ActionAccess::Read, [$id()]),
            new Action('movies.search', 'Buscar películas', 'Busca películas en TMDB', ActionAccess::Read, [
                new Param('term', 'string', true, 'Texto de búsqueda'),
            ]),
            new Action('movies.add', 'Agregar película', 'Agrega una película a la biblioteca', ActionAccess::Write, [
                new Param('tmdb_id', 'integer', true, 'ID de TMDB'),
                new Param('title', 'string', true, 'Título'),
                new Param('quality_profile_id', 'integer', true, 'ID del perfil de calidad'),
                new Param('root_folder', 'string', true, 'Carpeta raíz'),
                new Param('monitored', 'boolean', false, 'Monitorear', default: true),
                new Param('search', 'boolean', false, 'Buscar al agregar', default: true),
            ]),
            new Action('movies.delete', 'Eliminar película', 'Elimina una película', ActionAccess::Destructive, [
                $id(),
                new Param('delete_files', 'boolean', false, 'Borrar archivos', default: false),
            ]),
            new Action('queue.list', 'Ver cola', 'Cola de descargas', ActionAccess::Read, [
                new Param('page_size', 'integer', false, 'Cantidad de resultados', default: 20),
            ]),
            new Action('calendar.list', 'Ver calendario', 'Próximos estrenos', ActionAccess::Read, [
                new Param('start', 'string', false, 'Desde (YYYY-MM-DD)'),
                new Param('end', 'string', false, 'Hasta (YYYY-MM-DD)'),
            ]),
            new Action('health.list', 'Ver salud', 'Chequeos de salud de Radarr', ActionAccess::Read),
            new Action('system.status', 'Ver estado', 'Estado del sistema', ActionAccess::Read),
            new Action('commands.search', 'Ejecutar comando', 'Ejecuta un comando de Radarr', ActionAccess::Write, [
                new Param('name', 'string', true, 'Comando', enum: ['MoviesSearch', 'RssSync', 'RefreshMovie']),
            ]),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        return match ($key) {
            'movies.list' => $this->result($this->arrRequest($connection, 'GET', 'api/v3/movie'), 'Películas listadas.'),
            'movies.get' => $this->result($this->arrRequest($connection, 'GET', "api/v3/movie/{$params['id']}"), 'Película obtenida.'),
            'movies.search' => $this->result(
                $this->arrRequest($connection, 'GET', 'api/v3/movie/lookup', ['term' => $params['term']]),
                'Búsqueda completada.',
            ),
            'movies.add' => $this->result(
                $this->arrJson($connection, 'POST', 'api/v3/movie', [
                    'tmdbId' => $params['tmdb_id'],
                    'title' => $params['title'],
                    'qualityProfileId' => $params['quality_profile_id'],
                    'rootFolderPath' => $params['root_folder'],
                    'monitored' => $params['monitored'] ?? true,
                    'addOptions' => ['searchForMovie' => $params['search'] ?? true],
                ]),
                'Película agregada.',
            ),
            'movies.delete' => $this->result(
                $this->arrRequest($connection, 'DELETE', "api/v3/movie/{$params['id']}", [
                    'deleteFiles' => ($params['delete_files'] ?? false) ? 'true' : 'false',
                ]),
                'Película eliminada.',
            ),
            'queue.list' => $this->result(
                $this->arrRequest($connection, 'GET', 'api/v3/queue', ['pageSize' => $params['page_size'] ?? 20]),
                'Cola obtenida.',
            ),
            'calendar.list' => $this->result(
                $this->arrRequest($connection, 'GET', 'api/v3/calendar', array_filter([
                    'start' => $params['start'] ?? null,
                    'end' => $params['end'] ?? null,
                ], fn (mixed $value): bool => $value !== null)),
                'Calendario obtenido.',
            ),
            'health.list' => $this->result($this->arrRequest($connection, 'GET', 'api/v3/health'), 'Salud obtenida.'),
            'system.status' => $this->result($this->arrRequest($connection, 'GET', 'api/v3/system/status'), 'Estado obtenido.'),
            'commands.search' => $this->result(
                $this->arrJson($connection, 'POST', 'api/v3/command', ['name' => $params['name']]),
                'Comando ejecutado.',
            ),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $response = $this->arrRequest($connection, 'GET', 'api/v3/system/status');

        return $response->ok
            ? ConnectionTestResult::ok('Radarr OK', ['version' => $response->data['version'] ?? null])
            : ConnectionTestResult::fail($response->error ?? 'Radarr no respondió.');
    }
}
