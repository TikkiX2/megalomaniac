<?php

namespace App\Integrations\Connectors\Notion;

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

class NotionConnector extends AbstractConnector
{
    public function kind(): string
    {
        return 'notion';
    }

    public function label(): string
    {
        return 'Notion';
    }

    public function group(): string
    {
        return 'Contenido';
    }

    public function description(): string
    {
        return 'Búsqueda, páginas y bases de datos de Notion.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'https://api.notion.com';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'token',
                type: 'password',
                label: 'Internal integration token',
                help: 'notion.so/my-integrations → crear integración y compartir las páginas con ella.',
            ),
        ];
    }

    public function actions(): array
    {
        return [
            new Action('search.query', 'Buscar', 'Busca páginas y bases de datos', ActionAccess::Read, [
                new Param('query', 'string', false, 'Texto de búsqueda'),
                new Param('page_size', 'integer', false, 'Resultados', default: 20),
            ]),
            new Action('pages.get', 'Ver página', 'Obtiene una página', ActionAccess::Read, [
                new Param('page_id', 'string', true, 'ID de la página'),
            ]),
            new Action('pages.create', 'Crear página', 'Crea una página', ActionAccess::Write, [
                new Param('parent_type', 'string', true, 'Tipo de padre', enum: ['page', 'database']),
                new Param('parent_id', 'string', true, 'ID del padre'),
                new Param('title', 'string', true, 'Título'),
            ]),
            new Action('pages.update', 'Actualizar página', 'Archiva o actualiza propiedades', ActionAccess::Write, [
                new Param('page_id', 'string', true, 'ID de la página'),
                new Param('archived', 'boolean', false, 'Archivar'),
                new Param('properties', 'array', false, 'Propiedades'),
            ]),
            new Action('pages.delete', 'Archivar página', 'Archiva (borra lógicamente) una página', ActionAccess::Destructive, [
                new Param('page_id', 'string', true, 'ID de la página'),
            ]),
            new Action('blocks.children.list', 'Ver bloques', 'Lista bloques hijos', ActionAccess::Read, [
                new Param('block_id', 'string', true, 'ID del bloque/página'),
                new Param('page_size', 'integer', false, 'Resultados', default: 50),
            ]),
            new Action('blocks.append', 'Agregar párrafo', 'Agrega un párrafo al final', ActionAccess::Write, [
                new Param('block_id', 'string', true, 'ID del bloque/página'),
                new Param('paragraph', 'string', true, 'Texto del párrafo'),
            ]),
            new Action('databases.query', 'Consultar database', 'Consulta una base de datos', ActionAccess::Read, [
                new Param('database_id', 'string', true, 'ID de la base de datos'),
                new Param('page_size', 'integer', false, 'Resultados', default: 20),
            ]),
            new Action('users.me', 'Ver integración', 'Usuario de la integración', ActionAccess::Read),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        return match ($key) {
            'search.query' => $this->result($this->api($connection, 'POST', 'v1/search', [
                'query' => $params['query'] ?? '',
                'page_size' => $params['page_size'] ?? 20,
            ]), 'Búsqueda completada.'),
            'pages.get' => $this->result($this->api($connection, 'GET', "v1/pages/{$params['page_id']}"), 'Página obtenida.'),
            'pages.create' => $this->result(
                $this->api($connection, 'POST', 'v1/pages', [
                    'parent' => [$params['parent_type'] === 'database' ? 'database_id' : 'page_id' => $params['parent_id']],
                    'properties' => [
                        'title' => ['title' => [['text' => ['content' => $params['title']]]]],
                    ],
                ]),
                'Página creada.',
            ),
            'pages.update' => $this->result(
                $this->api($connection, 'PATCH', "v1/pages/{$params['page_id']}", array_filter([
                    'archived' => $params['archived'] ?? null,
                    'properties' => $params['properties'] ?? null,
                ], fn (mixed $value): bool => $value !== null)),
                'Página actualizada.',
            ),
            'pages.delete' => $this->result(
                $this->api($connection, 'PATCH', "v1/pages/{$params['page_id']}", ['archived' => true]),
                'Página archivada.',
            ),
            'blocks.children.list' => $this->result(
                $this->api($connection, 'GET', "v1/blocks/{$params['block_id']}/children", [
                    'page_size' => $params['page_size'] ?? 50,
                ]),
                'Bloques listados.',
            ),
            'blocks.append' => $this->result(
                $this->api($connection, 'PATCH', "v1/blocks/{$params['block_id']}/children", [
                    'children' => [[
                        'object' => 'block',
                        'type' => 'paragraph',
                        'paragraph' => ['rich_text' => [['type' => 'text', 'text' => ['content' => $params['paragraph']]]]],
                    ]],
                ]),
                'Párrafo agregado.',
            ),
            'databases.query' => $this->result(
                $this->api($connection, 'POST', "v1/databases/{$params['database_id']}/query", [
                    'page_size' => $params['page_size'] ?? 20,
                ]),
                'Database consultada.',
            ),
            'users.me' => $this->result($this->api($connection, 'GET', 'v1/users/me'), 'Integración obtenida.'),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $response = $this->api($connection, 'GET', 'v1/users/me');

        return $response->ok
            ? ConnectionTestResult::ok('Notion OK', ['name' => $response->data['name'] ?? null])
            : ConnectionTestResult::fail($response->error ?? 'Notion no respondió.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     */
    protected function api(Connection $connection, string $method, string $path, array $payload = [], array $query = []): HttpResult
    {
        return $this->request($connection, new HttpCall(
            method: $method,
            path: $path,
            query: $query,
            json: in_array($method, ['POST', 'PATCH'], true) ? $payload : null,
            headers: [
                'Authorization' => 'Bearer '.($connection->credentials['token'] ?? ''),
                'Notion-Version' => '2022-06-28',
            ],
        ));
    }
}
