<?php

namespace App\Integrations\Connectors\Arr;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\AuthField;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\Enums\ActionAccess;
use App\Models\Connection;

class SonarrConnector extends AbstractArrConnector
{
    public function kind(): string
    {
        return 'sonarr';
    }

    public function label(): string
    {
        return 'Sonarr';
    }

    public function description(): string
    {
        return 'Series: biblioteca, cola, calendario y búsquedas.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'http://localhost:8989';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'api_key',
                type: 'password',
                label: 'API Key',
                help: 'Settings → General → Security en Sonarr.',
            ),
        ];
    }

    public function actions(): array
    {
        $id = fn (): Param => new Param('id', 'integer', true, 'ID de la serie');

        return [
            new Action('series.list', 'Listar series', 'Lista la biblioteca de series', ActionAccess::Read),
            new Action('series.get', 'Ver serie', 'Obtiene una serie', ActionAccess::Read, [$id()]),
            new Action('series.search', 'Buscar series', 'Busca series en TheTVDB', ActionAccess::Read, [
                new Param('term', 'string', true, 'Texto de búsqueda'),
            ]),
            new Action('series.add', 'Agregar serie', 'Agrega una serie a la biblioteca', ActionAccess::Write, [
                new Param('tvdb_id', 'integer', true, 'ID de TheTVDB'),
                new Param('title', 'string', true, 'Título'),
                new Param('quality_profile_id', 'integer', true, 'ID del perfil de calidad'),
                new Param('root_folder', 'string', true, 'Carpeta raíz'),
                new Param('monitored', 'boolean', false, 'Monitorear', default: true),
                new Param('search', 'boolean', false, 'Buscar episodios faltantes', default: true),
            ]),
            new Action('series.delete', 'Eliminar serie', 'Elimina una serie', ActionAccess::Destructive, [
                $id(),
                new Param('delete_files', 'boolean', false, 'Borrar archivos', default: false),
            ]),
            new Action('queue.list', 'Ver cola', 'Cola de descargas', ActionAccess::Read, [
                new Param('page_size', 'integer', false, 'Cantidad de resultados', default: 20),
            ]),
            new Action('calendar.list', 'Ver calendario', 'Próximos episodios', ActionAccess::Read, [
                new Param('start', 'string', false, 'Desde (YYYY-MM-DD)'),
                new Param('end', 'string', false, 'Hasta (YYYY-MM-DD)'),
            ]),
            new Action('health.list', 'Ver salud', 'Chequeos de salud de Sonarr', ActionAccess::Read),
            new Action('system.status', 'Ver estado', 'Estado del sistema', ActionAccess::Read),
            new Action('commands.search', 'Ejecutar comando', 'Ejecuta un comando de Sonarr', ActionAccess::Write, [
                new Param('name', 'string', true, 'Comando', enum: ['MissingEpisodeSearch', 'RssSync', 'RefreshSeries']),
            ]),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        return match ($key) {
            'series.list' => $this->result($this->arrRequest($connection, 'GET', 'api/v3/series'), 'Series listadas.'),
            'series.get' => $this->result($this->arrRequest($connection, 'GET', "api/v3/series/{$params['id']}"), 'Serie obtenida.'),
            'series.search' => $this->result(
                $this->arrRequest($connection, 'GET', 'api/v3/series/lookup', ['term' => $params['term']]),
                'Búsqueda completada.',
            ),
            'series.add' => $this->result(
                $this->arrJson($connection, 'POST', 'api/v3/series', [
                    'tvdbId' => $params['tvdb_id'],
                    'title' => $params['title'],
                    'qualityProfileId' => $params['quality_profile_id'],
                    'rootFolderPath' => $params['root_folder'],
                    'monitored' => $params['monitored'] ?? true,
                    'addOptions' => ['searchForMissingEpisodes' => $params['search'] ?? true],
                ]),
                'Serie agregada.',
            ),
            'series.delete' => $this->result(
                $this->arrRequest($connection, 'DELETE', "api/v3/series/{$params['id']}", [
                    'deleteFiles' => ($params['delete_files'] ?? false) ? 'true' : 'false',
                ]),
                'Serie eliminada.',
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
            ? ConnectionTestResult::ok('Sonarr OK', ['version' => $response->data['version'] ?? null])
            : ConnectionTestResult::fail($response->error ?? 'Sonarr no respondió.');
    }
}
