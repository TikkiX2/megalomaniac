<?php

namespace Tests\Support;

use App\Models\Connection;
use Illuminate\Support\Facades\Http;

class McpFake
{
    /**
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array<string, mixed>  $overrides  result overrides keyed by JSON-RPC method
     */
    public static function server(array $tools = [], array $overrides = []): void
    {
        Http::fake(function ($request) use ($tools, $overrides) {
            $body = $request->data();
            $method = $body['method'] ?? '';

            if ($method === 'notifications/initialized') {
                return Http::response('', 202);
            }

            if (array_key_exists($method, $overrides)) {
                $result = $overrides[$method];

                return Http::response(
                    json_encode(['jsonrpc' => '2.0', 'id' => $body['id'] ?? null, 'result' => $result]),
                    200,
                    ['Content-Type' => 'application/json'],
                );
            }

            $result = match ($method) {
                'initialize' => [
                    'protocolVersion' => '2025-11-25',
                    'capabilities' => ['tools' => []],
                    'serverInfo' => ['name' => 'fake-mcp', 'version' => '1.0'],
                ],
                'tools/list' => ['tools' => $tools],
                'resources/list' => ['resources' => [['uri' => 'file://doc', 'name' => 'Doc', 'mimeType' => 'text/plain']]],
                'prompts/list' => ['prompts' => [['name' => 'resumen', 'description' => 'Resume', 'arguments' => [['name' => 'tema', 'required' => true]]]]],
                'resources/read' => ['contents' => [['uri' => 'file://doc', 'mimeType' => 'text/plain', 'text' => 'contenido']]],
                'prompts/get' => ['description' => 'd', 'messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Prompt renderizado']]]],
                'tools/call' => ['content' => [['type' => 'text', 'text' => 'resultado']], 'structuredContent' => ['ok' => true], 'isError' => false],
                default => [],
            };

            return Http::response(
                json_encode(['jsonrpc' => '2.0', 'id' => $body['id'] ?? null, 'result' => $result]),
                200,
                ['Content-Type' => 'application/json'],
            );
        });
    }

    public static function unauthorized(): void
    {
        Http::fake([
            '*' => Http::response('unauthorized', 401, [
                'WWW-Authenticate' => 'Bearer resource_metadata="https://mcp.test/.well-known/oauth-protected-resource", scope="default"',
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function connection(array $attributes = []): Connection
    {
        return Connection::factory()->make(array_merge([
            'kind' => 'mcp',
            'base_url' => 'https://mcp.test/mcp',
            'auth_type' => 'api_token',
            'credentials' => [],
            'options' => ['timeout' => 20],
        ], $attributes));
    }
}
