<?php

namespace App\Integrations\Connectors\HomeAssistant;

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

class HomeAssistantConnector extends AbstractConnector
{
    public function kind(): string
    {
        return 'home_assistant';
    }

    public function label(): string
    {
        return 'Home Assistant';
    }

    public function group(): string
    {
        return 'Hogar';
    }

    public function description(): string
    {
        return 'Estados, servicios, escenas y plantillas.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'http://localhost:8123';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'token',
                type: 'password',
                label: 'Long-lived access token',
                help: 'Perfil → Tokens de acceso de larga duración en Home Assistant.',
            ),
        ];
    }

    public function actions(): array
    {
        return [
            new Action('api.status', 'Ver estado', 'Estado de la API', ActionAccess::Read),
            new Action('states.list', 'Listar estados', 'Estados de entidades (filtrable por dominio)', ActionAccess::Read, [
                new Param('domain', 'string', false, 'Dominio, p. ej. light, sensor, switch'),
            ]),
            new Action('states.get', 'Ver estado', 'Estado de una entidad', ActionAccess::Read, [
                new Param('entity_id', 'string', true, 'Entidad, p. ej. light.kitchen'),
            ]),
            new Action('services.call', 'Llamar servicio', 'Ejecuta un servicio (turn_on, toggle, …)', ActionAccess::Write, [
                new Param('domain', 'string', true, 'Dominio', enum: ['light', 'switch', 'climate', 'media_player', 'script', 'scene', 'automation']),
                new Param('service', 'string', true, 'Servicio', enum: ['turn_on', 'turn_off', 'toggle']),
                new Param('entity_id', 'string', false, 'Entidad destino'),
                new Param('data', 'array', false, 'Datos extra del servicio'),
            ]),
            new Action('scenes.list', 'Listar escenas', 'Escenas disponibles', ActionAccess::Read),
            new Action('template.render', 'Renderizar plantilla', 'Renderiza una plantilla Jinja', ActionAccess::Read, [
                new Param('template', 'string', true, 'Plantilla Jinja'),
            ]),
            new Action('config.get', 'Ver configuración', 'Configuración de Home Assistant', ActionAccess::Read),
            new Action('logbook.list', 'Ver logbook', 'Eventos del logbook', ActionAccess::Read, [
                new Param('timestamp', 'string', false, 'Timestamp ISO 8601'),
                new Param('entity', 'string', false, 'Entidad'),
            ]),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        return match ($key) {
            'api.status' => $this->result($this->api($connection, 'GET', 'api/'), 'API disponible.'),
            'states.list' => $this->statesList($connection, $params['domain'] ?? null),
            'states.get' => $this->result($this->api($connection, 'GET', "api/states/{$params['entity_id']}"), 'Estado obtenido.'),
            'services.call' => $this->result(
                $this->api($connection, 'POST', "api/services/{$params['domain']}/{$params['service']}", json: array_filter([
                    'entity_id' => $params['entity_id'] ?? null,
                ] + ($params['data'] ?? []), fn (mixed $value): bool => $value !== null)),
                'Servicio ejecutado.',
            ),
            'scenes.list' => $this->statesList($connection, 'scene'),
            'template.render' => $this->result(
                $this->api($connection, 'POST', 'api/template', json: ['template' => $params['template']]),
                'Plantilla renderizada.',
            ),
            'config.get' => $this->result($this->api($connection, 'GET', 'api/config'), 'Configuración obtenida.'),
            'logbook.list' => $this->result(
                $this->api($connection, 'GET', 'api/logbook/'.($params['timestamp'] ?? now()->toIso8601String()), array_filter([
                    'entity' => $params['entity'] ?? null,
                ], fn (mixed $value): bool => $value !== null)),
                'Logbook obtenido.',
            ),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $response = $this->api($connection, 'GET', 'api/');

        return $response->ok
            ? ConnectionTestResult::ok('Home Assistant OK', ['message' => $response->data['message'] ?? null])
            : ConnectionTestResult::fail($response->error ?? 'Home Assistant no respondió.');
    }

    protected function statesList(Connection $connection, ?string $domain): ActionResult
    {
        $response = $this->api($connection, 'GET', 'api/states');

        if (! $response->ok) {
            return ActionResult::failure($response->error ?? 'La acción falló.');
        }

        $states = $response->data ?? [];

        if ($domain !== null) {
            $states = array_values(array_filter(
                $states,
                fn (array $state): bool => str_starts_with((string) ($state['entity_id'] ?? ''), $domain.'.'),
            ));
        }

        return ActionResult::success('Estados listados.', $states);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     */
    protected function api(Connection $connection, string $method, string $path, array $query = [], ?array $json = null): HttpResult
    {
        return $this->request($connection, new HttpCall(
            method: $method,
            path: $path,
            query: $query,
            json: $json,
            headers: ['Authorization' => 'Bearer '.($connection->credentials['token'] ?? '')],
        ));
    }
}
