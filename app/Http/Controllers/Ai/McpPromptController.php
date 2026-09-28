<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Integrations\ConnectorRegistry;
use App\Integrations\Mcp\McpDiscoveryService;
use App\Models\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class McpPromptController extends Controller
{
    public function index(Request $request, McpDiscoveryService $discovery): JsonResponse
    {
        $prompts = [];

        Connection::query()
            ->forUser($request->user())
            ->enabled()
            ->where('kind', 'mcp')
            ->each(function (Connection $connection) use (&$prompts, $discovery): void {
                try {
                    foreach ($discovery->prompts($connection) as $prompt) {
                        $prompts[] = [
                            'connection_id' => $connection->id,
                            'connection_name' => $connection->name,
                            'name' => $prompt->name,
                            'title' => $prompt->title,
                            'description' => $prompt->description,
                            'arguments' => $prompt->arguments,
                        ];
                    }
                } catch (Throwable) {
                    // Servidor caído o sin autorización: se omite.
                }
            });

        return response()->json(['prompts' => $prompts]);
    }

    public function render(Request $request, ConnectorRegistry $registry): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'arguments' => ['nullable', 'array'],
        ]);

        $connection = Connection::query()
            ->forUser($request->user())
            ->enabled()
            ->where('kind', 'mcp')
            ->findOrFail($validated['connection_id']);

        $result = $registry->for('mcp')->execute($connection, 'prompts.get', [
            'name' => $validated['name'],
            'arguments' => $validated['arguments'] ?? [],
        ]);

        return response()->json([
            'ok' => $result->ok,
            'text' => $result->data['text'] ?? null,
            'error' => $result->ok ? null : Str::limit((string) $result->error, 300),
        ]);
    }
}
