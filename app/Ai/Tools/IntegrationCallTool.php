<?php

namespace App\Ai\Tools;

use App\Integrations\Actions\ExecutionContext;
use App\Integrations\IntegrationExecutor;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class IntegrationCallTool implements Tool
{
    /**
     * @param  string[]|null  $allowedKinds  null = todas; ['*'] = todas
     */
    public function __construct(
        protected User $user,
        protected IntegrationExecutor $executor,
        protected ?array $allowedKinds = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Execute an action on one of the user\'s integration connections. Read actions run immediately; write and destructive actions are queued for the user\'s approval. Discover available connections and action keys with integration_catalog first.';
    }

    public function handle(Request $request): Stringable|string
    {
        $connection = Connection::query()
            ->forUser($this->user)
            ->enabled()
            ->where(fn ($query) => $query
                ->where('name', $request['connection'])
                ->orWhere('id', $request['connection']))
            ->first();

        if (! $connection) {
            $available = Connection::query()
                ->forUser($this->user)
                ->enabled()
                ->when(
                    $this->allowedKinds !== null && $this->allowedKinds !== ['*'],
                    fn ($query) => $query->whereIn('kind', $this->allowedKinds),
                )
                ->pluck('name')
                ->implode(', ');

            return json_encode([
                'status' => 'error',
                'message' => $available
                    ? "Conexión no encontrada. Disponibles: {$available}."
                    : 'No hay conexiones habilitadas. Creá una en Settings → Conexiones.',
            ]);
        }

        if ($this->allowedKinds !== null && $this->allowedKinds !== ['*'] && ! in_array($connection->kind, $this->allowedKinds, true)) {
            return json_encode([
                'status' => 'error',
                'message' => "La conexión [{$connection->name}] no está permitida para este agente.",
            ]);
        }

        $result = $this->executor->execute(
            $connection,
            (string) $request['action'],
            (array) ($request['params'] ?? []),
            ExecutionContext::forAgent($this->user),
        );

        if ($result->pending) {
            return json_encode([
                'status' => 'pending_approval',
                'approval_id' => $result->approvalId,
                'summary' => $result->summary,
                'message' => 'La acción quedó pendiente de aprobación del usuario en la bandeja de Aprobaciones.',
            ]);
        }

        return json_encode([
            'status' => $result->ok ? 'success' : 'error',
            'summary' => $result->summary,
            'data' => $result->data,
            'error' => $result->error,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'connection' => $schema->string()
                ->description('Nombre o ID de la conexión (ver integration_catalog)')
                ->required(),
            'action' => $schema->string()
                ->description('Clave de la acción, por ejemplo issues.create o containers.restart')
                ->required(),
            'params' => $schema->object()
                ->description('Parámetros de la acción según integration_catalog'),
        ];
    }
}
