<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\StoreConnectionRequest;
use App\Http\Requests\Integrations\UpdateConnectionRequest;
use App\Integrations\Actions\Action;
use App\Integrations\Actions\AuthField;
use App\Integrations\ConnectorRegistry;
use App\Integrations\Contracts\ConnectionAwareConnector;
use App\Integrations\Contracts\Connector;
use App\Integrations\Enums\AuthType;
use App\Integrations\Enums\TransportKind;
use App\Integrations\IntegrationExecutor;
use App\Integrations\Mcp\McpDiscoveryService;
use App\Integrations\Mcp\McpToolMapper;
use App\Models\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;

class ConnectionController extends Controller
{
    public function index(Request $request): Response
    {
        $connections = Connection::query()
            ->forUser($request->user())
            ->orderBy('kind')
            ->orderBy('name')
            ->get()
            ->map(fn (Connection $connection): array => [
                'id' => $connection->id,
                'kind' => $connection->kind,
                'name' => $connection->name,
                'auth_type' => $connection->auth_type->value,
                'base_url' => $connection->base_url,
                'transport' => $connection->transport->value,
                'enabled' => $connection->enabled,
                'status' => $connection->status->value,
                'status_message' => $connection->status_message,
                'last_tested_at' => $connection->last_tested_at?->toIso8601String(),
                'last_used_at' => $connection->last_used_at?->toIso8601String(),
            ])
            ->values();

        $catalog = collect(app(ConnectorRegistry::class)->all())
            ->map(fn (Connector $connector): array => [
                'kind' => $connector->kind(),
                'label' => $connector->label(),
                'group' => $connector->group(),
                'description' => $connector->description(),
                'auth_type' => $this->inferAuthType($connector)->value,
                'auth_fields' => array_map(fn (AuthField $field): array => [
                    'name' => $field->name,
                    'type' => $field->type,
                    'label' => $field->label,
                    'required' => $field->required,
                    'help' => $field->help,
                    'options' => $field->options,
                ], $connector->authFields()),
                'transports' => $connector->transports(),
                'option_fields' => method_exists($connector, 'optionFields') ? $connector->optionFields() : [],
            ])
            ->values();

        return Inertia::render('settings/connections', [
            'connections' => $connections,
            'catalog' => $catalog,
        ]);
    }

    public function store(StoreConnectionRequest $request): RedirectResponse
    {
        Connection::create($request->validated() + ['user_id' => $request->user()->id]);

        return to_route('connections.index')->with('success', 'Conexión creada.');
    }

    public function update(UpdateConnectionRequest $request, int $connection): RedirectResponse
    {
        $model = $this->owned($request, $connection);
        $data = $request->validated();

        if (array_key_exists('credentials', $data) && blank($data['credentials'])) {
            unset($data['credentials']);
        }

        $model->update($data);

        return back()->with('success', 'Conexión actualizada.');
    }

    public function destroy(Request $request, int $connection): RedirectResponse
    {
        $this->owned($request, $connection)->delete();

        return to_route('connections.index')->with('success', 'Conexión eliminada.');
    }

    public function test(Request $request, IntegrationExecutor $executor): RedirectResponse
    {
        $validated = $request->validate([
            'connection_id' => ['nullable', 'integer'],
            'kind' => ['nullable', 'string'],
            'auth_type' => ['nullable', Rule::enum(AuthType::class)],
            'credentials' => ['nullable', 'array'],
            'base_url' => ['nullable', 'string', 'max:500'],
            'transport' => ['nullable', Rule::enum(TransportKind::class)],
            'transport_config' => ['nullable', 'array'],
        ]);

        if (! empty($validated['connection_id'])) {
            $connection = $this->owned($request, (int) $validated['connection_id']);
        } else {
            $connection = new Connection(
                array_merge($validated, ['user_id' => $request->user()->id]),
            );
        }

        $result = $executor->testConnection($connection);

        return back()->with('test_result', [
            'ok' => $result->ok,
            'message' => $result->message,
        ]);
    }

    public function actions(Request $request, int $connection): JsonResponse
    {
        $model = $this->owned($request, $connection);
        $connector = app(ConnectorRegistry::class)->for($model->kind);
        $actions = $connector instanceof ConnectionAwareConnector
            ? $connector->actionsFor($model)
            : $connector->actions();

        return response()->json([
            'actions' => collect($actions)
                ->map(fn (Action $action): array => [
                    'key' => $action->key,
                    'label' => $action->label,
                    'description' => $action->description,
                    'access' => $action->access->value,
                ])
                ->values(),
        ]);
    }

    public function discover(Request $request, int $connection): JsonResponse
    {
        $model = $this->owned($request, $connection);

        abort_unless($model->kind === 'mcp', 404);

        $discovery = app(McpDiscoveryService::class);
        $enabled = $model->options['tools'] ?? null;

        try {
            $tools = $discovery->tools($model)
                ->map(fn ($tool): array => [
                    'name' => $tool->name,
                    'title' => $tool->title,
                    'description' => $tool->description,
                    'access' => app(McpToolMapper::class)->toAction($tool)->access->value,
                    'enabled' => ! is_array($enabled) || in_array($tool->name, $enabled, true),
                ])
                ->values();

            $resources = $discovery->resources($model)
                ->map(fn ($resource): array => ['uri' => $resource->uri, 'name' => $resource->name, 'mime_type' => $resource->mimeType])
                ->values();

            $prompts = $discovery->prompts($model)
                ->map(fn ($prompt): array => ['name' => $prompt->name, 'description' => $prompt->description])
                ->values();

            return response()->json([
                'ok' => true,
                'tools' => $tools,
                'resources' => $resources,
                'prompts' => $prompts,
            ]);
        } catch (AuthorizationRequiredException $e) {
            return response()->json([
                'ok' => false,
                'authorization_required' => true,
                'connect_url' => route('integrations.mcp.connect', $model).'?'.http_build_query($e->query()),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => Str::limit($e->getMessage(), 300)]);
        }
    }

    public function updateTools(Request $request, int $connection): JsonResponse
    {
        $model = $this->owned($request, $connection);

        abort_unless($model->kind === 'mcp', 404);

        $validated = $request->validate([
            'enabled_tools' => ['required', 'array'],
            'enabled_tools.*' => ['string', 'max:100'],
        ]);

        $model->forceFill([
            'options' => array_merge($model->options ?? [], ['tools' => array_values($validated['enabled_tools'])]),
        ])->save();

        app(McpDiscoveryService::class)->forget($model);

        return response()->json(['ok' => true]);
    }

    protected function owned(Request $request, int $connectionId): Connection
    {
        return Connection::query()
            ->forUser($request->user())
            ->findOrFail($connectionId);
    }

    protected function inferAuthType(Connector $connector): AuthType
    {
        $fields = $connector->authFields();

        if ($fields === []) {
            return AuthType::None;
        }

        return $fields[0]->type === 'oauth' ? AuthType::OAuth2 : AuthType::ApiToken;
    }
}
