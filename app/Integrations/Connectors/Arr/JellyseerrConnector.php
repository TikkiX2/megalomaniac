<?php

namespace App\Integrations\Connectors\Arr;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\AuthField;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\Enums\ActionAccess;
use App\Models\Connection;

class JellyseerrConnector extends AbstractArrConnector
{
    public function kind(): string
    {
        return 'jellyseerr';
    }

    public function label(): string
    {
        return 'Jellyseerr';
    }

    public function description(): string
    {
        return 'Pedidos de media: aprobar, rechazar y buscar.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'http://localhost:5055';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'api_key',
                type: 'password',
                label: 'API Key',
                help: 'Settings → General en Jellyseerr.',
            ),
        ];
    }

    public function actions(): array
    {
        return [
            new Action('requests.list', 'Listar pedidos', 'Lista pedidos de media', ActionAccess::Read, [
                new Param('take', 'integer', false, 'Cantidad de resultados', default: 20),
                new Param('skip', 'integer', false, 'Desplazamiento', default: 0),
                new Param('filter', 'string', false, 'Filtro', enum: ['all', 'pending', 'approved', 'available']),
            ]),
            new Action('requests.approve', 'Aprobar pedido', 'Aprueba un pedido', ActionAccess::Write, [
                new Param('id', 'integer', true, 'ID del pedido'),
            ]),
            new Action('requests.decline', 'Rechazar pedido', 'Rechaza un pedido', ActionAccess::Write, [
                new Param('id', 'integer', true, 'ID del pedido'),
            ]),
            new Action('requests.create', 'Crear pedido', 'Solicita media', ActionAccess::Write, [
                new Param('media_type', 'string', true, 'Tipo de media', enum: ['movie', 'tv']),
                new Param('media_id', 'integer', true, 'ID de TMDB'),
            ]),
            new Action('media.search', 'Buscar media', 'Busca películas y series', ActionAccess::Read, [
                new Param('query', 'string', true, 'Texto de búsqueda'),
            ]),
            new Action('status.get', 'Ver estado', 'Estado de Jellyseerr', ActionAccess::Read),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        return match ($key) {
            'requests.list' => $this->result(
                $this->arrRequest($connection, 'GET', 'api/v1/request', array_filter([
                    'take' => $params['take'] ?? 20,
                    'skip' => $params['skip'] ?? 0,
                    'filter' => $params['filter'] ?? null,
                ], fn (mixed $value): bool => $value !== null)),
                'Pedidos listados.',
            ),
            'requests.approve' => $this->result(
                $this->arrJson($connection, 'POST', "api/v1/request/{$params['id']}/approve"),
                'Pedido aprobado.',
            ),
            'requests.decline' => $this->result(
                $this->arrJson($connection, 'POST', "api/v1/request/{$params['id']}/decline"),
                'Pedido rechazado.',
            ),
            'requests.create' => $this->result(
                $this->arrJson($connection, 'POST', 'api/v1/request', [
                    'mediaType' => $params['media_type'],
                    'mediaId' => $params['media_id'],
                ]),
                'Pedido creado.',
            ),
            'media.search' => $this->result(
                $this->arrRequest($connection, 'GET', 'api/v1/search', ['query' => $params['query']]),
                'Búsqueda completada.',
            ),
            'status.get' => $this->result($this->arrRequest($connection, 'GET', 'api/v1/status'), 'Estado obtenido.'),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $response = $this->arrRequest($connection, 'GET', 'api/v1/status');

        return $response->ok
            ? ConnectionTestResult::ok('Jellyseerr OK', ['version' => $response->data['version'] ?? null])
            : ConnectionTestResult::fail($response->error ?? 'Jellyseerr no respondió.');
    }
}
