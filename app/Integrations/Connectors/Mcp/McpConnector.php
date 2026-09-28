<?php

namespace App\Integrations\Connectors\Mcp;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\AuthField;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\Connectors\AbstractConnector;
use App\Integrations\Contracts\ConnectionAwareConnector;
use App\Integrations\Enums\ActionAccess;
use App\Integrations\Mcp\McpClientFactory;
use App\Integrations\Mcp\McpDiscoveryService;
use App\Integrations\Mcp\McpTokenStore;
use App\Integrations\Mcp\McpToolMapper;
use App\Models\Connection;
use Illuminate\Support\Str;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\WebClient;
use Throwable;

class McpConnector extends AbstractConnector implements ConnectionAwareConnector
{
    public function kind(): string
    {
        return 'mcp';
    }

    public function label(): string
    {
        return 'MCP personalizado';
    }

    public function group(): string
    {
        return 'MCP';
    }

    public function description(): string
    {
        return 'Servidor MCP remoto (Notion, Linear, Sentry…): tools, resources y prompts.';
    }

    public function defaultBaseUrl(): ?string
    {
        return null;
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'token',
                type: 'password',
                label: 'Bearer token (opcional)',
                required: false,
                help: 'Para servidores MCP con token. Si el server pide OAuth, dejalo vacío y usá el botón Conectar.',
            ),
            new AuthField(
                name: 'client_id',
                type: 'text',
                label: 'OAuth client_id (opcional)',
                required: false,
                help: 'Solo si el server no soporta registro dinámico (DCR).',
            ),
            new AuthField(
                name: 'client_secret',
                type: 'password',
                label: 'OAuth client_secret (opcional)',
                required: false,
            ),
            new AuthField(
                name: 'scope',
                type: 'text',
                label: 'OAuth scope (opcional)',
                required: false,
            ),
        ];
    }

    public function actions(): array
    {
        return $this->genericActions();
    }

    public function actionsFor(Connection $connection): array
    {
        return array_merge($this->genericActions(), $this->toolActions($connection));
    }

    /**
     * @return Action[]
     */
    protected function genericActions(): array
    {
        return [
            new Action('mcp.info', 'Info del servidor', 'Nombre, versión y capacidades del servidor MCP', ActionAccess::Read),
            new Action('resources.list', 'Listar recursos', 'Recursos expuestos por el servidor MCP', ActionAccess::Read),
            new Action('resources.read', 'Leer recurso', 'Lee un recurso por URI', ActionAccess::Read, [
                new Param('uri', 'string', true, 'URI del recurso'),
            ]),
            new Action('prompts.list', 'Listar prompts', 'Prompts expuestos por el servidor MCP', ActionAccess::Read),
            new Action('prompts.get', 'Renderizar prompt', 'Renderiza un prompt con argumentos', ActionAccess::Read, [
                new Param('name', 'string', true, 'Nombre del prompt'),
                new Param('arguments', 'array', false, 'Argumentos del prompt'),
            ]),
        ];
    }

    /**
     * @return Action[]
     */
    protected function toolActions(Connection $connection): array
    {
        try {
            $tools = app(McpDiscoveryService::class)->tools($connection);
        } catch (Throwable) {
            return [];
        }

        $enabled = $connection->options['tools'] ?? null;

        if (is_array($enabled)) {
            $tools = $tools->filter(fn ($tool): bool => in_array($tool->name, $enabled, true));
        }

        return $tools
            ->map(fn ($tool): Action => app(McpToolMapper::class)->toAction($tool))
            ->values()
            ->all();
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        $connection = app(McpTokenStore::class)->ensureFresh($connection);

        try {
            $client = app(McpClientFactory::class)->for($connection);

            if (str_starts_with($key, 'tools.')) {
                return $this->callTool($client, $connection, substr($key, 6), $params);
            }

            return match ($key) {
                'mcp.info' => $this->serverInfo($client),
                'resources.list' => $this->listResources($client),
                'resources.read' => $this->readResource($client, (string) $params['uri']),
                'prompts.list' => $this->listPrompts($client),
                'prompts.get' => $this->getPrompt($client, (string) $params['name'], (array) ($params['arguments'] ?? [])),
                default => ActionResult::failure("Acción desconocida [{$key}]."),
            };
        } catch (AuthorizationRequiredException $e) {
            return ActionResult::failure(
                'Requiere autorización: usá el botón Conectar de la conexión.',
                ['authorization_required' => true] + $e->query(),
            );
        } catch (Throwable $e) {
            return ActionResult::failure(Str::limit($e->getMessage(), 300));
        }
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $connection = app(McpTokenStore::class)->ensureFresh($connection);

        try {
            $client = app(McpClientFactory::class)->for($connection);

            return ConnectionTestResult::ok('MCP OK', [
                'tools' => $client->tools()->count(),
                'resources' => $client->resources()->count(),
                'prompts' => $client->prompts()->count(),
            ]);
        } catch (AuthorizationRequiredException $e) {
            return ConnectionTestResult::fail('Requiere autorización.', [
                'authorization_required' => true,
                'resource_metadata' => $e->resourceMetadataUrl(),
                'scope' => $e->scope(),
            ]);
        } catch (Throwable $e) {
            return ConnectionTestResult::fail(Str::limit($e->getMessage(), 300));
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function callTool(WebClient $client, Connection $connection, string $name, array $params): ActionResult
    {
        $enabled = $connection->options['tools'] ?? null;

        if (is_array($enabled) && ! in_array($name, $enabled, true)) {
            return ActionResult::failure("La tool [{$name}] no está habilitada en esta conexión.");
        }

        $result = $client->callTool($name, $params);

        if ($result->isError) {
            return ActionResult::failure($result->text() !== '' ? $result->text() : "La tool [{$name}] devolvió un error.");
        }

        return ActionResult::success("Tool [{$name}] ejecutada.", $result->structuredContent ?? [
            'text' => $this->cap($result->text()),
        ]);
    }

    protected function serverInfo(WebClient $client): ActionResult
    {
        $client->connect();

        $info = $client->initializeResult();
        $server = $info?->serverInfo;

        return ActionResult::success('Info del servidor MCP.', [
            'name' => $server?->name,
            'version' => $server?->version,
            'protocol_version' => $info?->protocolVersion,
            'capabilities' => array_keys((array) ($info?->capabilities ?? [])),
            'instructions' => $info?->instructions,
        ]);
    }

    protected function listResources(WebClient $client): ActionResult
    {
        $resources = $client->resources()
            ->map(fn ($resource): array => [
                'uri' => $resource->uri,
                'name' => $resource->name,
                'description' => $resource->description,
                'mime_type' => $resource->mimeType,
            ])
            ->values()
            ->all();

        return ActionResult::success('Recursos listados.', $resources);
    }

    protected function readResource(WebClient $client, string $uri): ActionResult
    {
        $result = $client->readResource($uri);

        return ActionResult::success('Recurso leído.', [
            'uri' => $uri,
            'mime_type' => $result->mimeType(),
            'content' => $this->cap($result->content()),
        ]);
    }

    protected function listPrompts(WebClient $client): ActionResult
    {
        $prompts = $client->prompts()
            ->map(fn ($prompt): array => [
                'name' => $prompt->name,
                'title' => $prompt->title,
                'description' => $prompt->description,
                'arguments' => $prompt->arguments,
            ])
            ->values()
            ->all();

        return ActionResult::success('Prompts listados.', $prompts);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function getPrompt(WebClient $client, string $name, array $arguments): ActionResult
    {
        $result = $client->getPrompt($name, $arguments);

        return ActionResult::success('Prompt renderizado.', [
            'name' => $name,
            'text' => $this->cap($result->text()),
        ]);
    }

    protected function cap(string $text): string
    {
        return substr($text, 0, (int) config('integrations.mcp.output_cap_bytes', 65536));
    }
}
