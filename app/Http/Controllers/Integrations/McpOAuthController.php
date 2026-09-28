<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Integrations\Mcp\McpClientFactory;
use App\Integrations\Mcp\McpTokenStore;
use App\Models\Connection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class McpOAuthController extends Controller
{
    public function __construct(
        private readonly McpClientFactory $factory,
        private readonly McpTokenStore $store,
    ) {}

    public function connect(Request $request, int $connection): RedirectResponse
    {
        $model = $this->owned($request, $connection);

        try {
            $web = $this->factory->base($model)->withOAuth(
                $model->credentials['client_id'] ?? null,
                $model->credentials['client_secret'] ?? null,
                $model->credentials['scope'] ?? null,
                redirectUri: route('integrations.mcp.callback', $model),
            );

            $oauth = $web->oAuthClient(
                resourceMetadataUrl: is_string($request->query('resource_metadata')) ? $request->query('resource_metadata') : null,
                challengeScope: is_string($request->query('scope')) ? $request->query('scope') : null,
            );

            return $oauth->redirect(returnTo: route('connections.index'));
        } catch (Throwable $e) {
            return to_route('connections.index')
                ->with('error', 'No se pudo iniciar OAuth: '.Str::limit($e->getMessage(), 200));
        }
    }

    public function callback(Request $request, int $connection): RedirectResponse
    {
        $model = $this->owned($request, $connection);

        try {
            $web = $this->factory->base($model)->withOAuth(
                $model->credentials['client_id'] ?? null,
                $model->credentials['client_secret'] ?? null,
                $model->credentials['scope'] ?? null,
                redirectUri: route('integrations.mcp.callback', $model),
            );

            $token = $web->oAuthClient()->exchangeCallback();

            $this->store->store($model, $token);

            return to_route('connections.index')->with('success', 'MCP conectado con OAuth.');
        } catch (Throwable $e) {
            return to_route('connections.index')
                ->with('error', 'No se pudo completar OAuth: '.Str::limit($e->getMessage(), 200));
        }
    }

    protected function owned(Request $request, int $connectionId): Connection
    {
        return Connection::query()
            ->forUser($request->user())
            ->where('kind', 'mcp')
            ->findOrFail($connectionId);
    }
}
