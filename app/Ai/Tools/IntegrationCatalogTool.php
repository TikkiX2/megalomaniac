<?php

namespace App\Ai\Tools;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\Param;
use App\Integrations\ConnectorRegistry;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class IntegrationCatalogTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'List the user\'s enabled integration connections (GitHub, Google, Docker, etc.) and the actions available on each one. Use it before integration_call to discover exact action keys and parameters.';
    }

    public function handle(Request $request): Stringable|string
    {
        $registry = app(ConnectorRegistry::class);
        $search = $request['search'] ?? null;

        $connections = Connection::query()
            ->forUser($this->user)
            ->enabled()
            ->when($request['connection'] ?? null, fn ($query, $name) => $query->where('name', 'like', "%{$name}%"))
            ->orderBy('name')
            ->get()
            ->map(function (Connection $connection) use ($registry, $search): ?array {
                if (! $registry->has($connection->kind)) {
                    return null;
                }

                $actions = collect($registry->for($connection->kind)->actions())
                    ->when($search, fn ($actions) => $actions->filter(
                        fn (Action $action): bool => str_contains(
                            strtolower($action->key.' '.$action->label.' '.$action->description),
                            strtolower((string) $search),
                        ),
                    ))
                    ->map(fn (Action $action): array => [
                        'key' => $action->key,
                        'label' => $action->label,
                        'description' => $action->description,
                        'access' => $action->access->value,
                        'params' => array_map(fn (Param $param): array => [
                            'name' => $param->name,
                            'type' => $param->type,
                            'required' => $param->required,
                            'description' => $param->description,
                            'enum' => $param->enum,
                        ], $action->params),
                    ])
                    ->values()
                    ->all();

                return [
                    'name' => $connection->name,
                    'kind' => $connection->kind,
                    'actions' => $actions,
                ];
            })
            ->filter()
            ->values();

        return json_encode(['connections' => $connections], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'connection' => $schema->string()
                ->description('Filtrar por nombre de conexión (parcial)'),
            'search' => $schema->string()
                ->description('Filtrar acciones por texto (clave, label o descripción)'),
        ];
    }
}
