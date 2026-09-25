<?php

namespace App\Integrations\Connectors\Arr;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\AuthField;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\Enums\ActionAccess;
use App\Models\Connection;

class ProwlarrConnector extends AbstractArrConnector
{
    public function kind(): string
    {
        return 'prowlarr';
    }

    public function label(): string
    {
        return 'Prowlarr';
    }

    public function description(): string
    {
        return 'Indexadores y búsqueda unificada.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'http://localhost:9696';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'api_key',
                type: 'password',
                label: 'API Key',
                help: 'Settings → General → Security en Prowlarr.',
            ),
        ];
    }

    public function actions(): array
    {
        return [
            new Action('indexers.list', 'Listar indexadores', 'Lista los indexadores configurados', ActionAccess::Read),
            new Action('indexers.test', 'Probar indexador', 'Ejecuta el test de un indexador', ActionAccess::Write, [
                new Param('id', 'integer', true, 'ID del indexador'),
            ]),
            new Action('search.query', 'Buscar', 'Búsqueda en todos los indexadores', ActionAccess::Read, [
                new Param('query', 'string', true, 'Texto de búsqueda'),
                new Param('categories', 'array', false, 'IDs de categorías'),
                new Param('limit', 'integer', false, 'Cantidad de resultados', default: 50),
            ]),
            new Action('health.list', 'Ver salud', 'Chequeos de salud de Prowlarr', ActionAccess::Read),
            new Action('system.status', 'Ver estado', 'Estado del sistema', ActionAccess::Read),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        return match ($key) {
            'indexers.list' => $this->result($this->arrRequest($connection, 'GET', 'api/v1/indexer'), 'Indexadores listados.'),
            'indexers.test' => $this->result(
                $this->arrJson($connection, 'POST', 'api/v1/indexer/test', ['id' => $params['id']]),
                'Test de indexador ejecutado.',
            ),
            'search.query' => $this->result(
                $this->arrRequest($connection, 'GET', 'api/v1/search', array_filter([
                    'query' => $params['query'],
                    'categories' => isset($params['categories']) ? implode(',', (array) $params['categories']) : null,
                    'limit' => $params['limit'] ?? 50,
                ], fn (mixed $value): bool => $value !== null)),
                'Búsqueda completada.',
            ),
            'health.list' => $this->result($this->arrRequest($connection, 'GET', 'api/v1/health'), 'Salud obtenida.'),
            'system.status' => $this->result($this->arrRequest($connection, 'GET', 'api/v1/system/status'), 'Estado obtenido.'),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $response = $this->arrRequest($connection, 'GET', 'api/v1/system/status');

        return $response->ok
            ? ConnectionTestResult::ok('Prowlarr OK', ['version' => $response->data['version'] ?? null])
            : ConnectionTestResult::fail($response->error ?? 'Prowlarr no respondió.');
    }
}
