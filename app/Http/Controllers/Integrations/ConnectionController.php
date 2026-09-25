<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\StoreConnectionRequest;
use App\Http\Requests\Integrations\UpdateConnectionRequest;
use App\Integrations\Actions\Action;
use App\Integrations\Actions\AuthField;
use App\Integrations\ConnectorRegistry;
use App\Integrations\Contracts\Connector;
use App\Integrations\Enums\AuthType;
use App\Integrations\Enums\TransportKind;
use App\Integrations\IntegrationExecutor;
use App\Models\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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

        return response()->json([
            'actions' => collect($connector->actions())
                ->map(fn (Action $action): array => [
                    'key' => $action->key,
                    'label' => $action->label,
                    'description' => $action->description,
                    'access' => $action->access->value,
                ])
                ->values(),
        ]);
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
