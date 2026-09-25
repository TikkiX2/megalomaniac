<?php

namespace App\Ai\Tools;

use App\Ai\Agents\AgentDefinitionService;
use App\Models\AgentDefinition;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ManageAgentsTool implements Tool
{
    public function __construct(
        protected User $user,
        protected AgentDefinitionService $service,
    ) {}

    public function description(): Stringable|string
    {
        return 'Create and manage the user\'s background agents (durable agents that run on a schedule and report back). Actions: list, create, update, enable, disable, delete. Use it when the user asks for an agent that monitors something periodically.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = (string) ($request['action'] ?? '');

        return match ($action) {
            'list' => $this->list(),
            'create' => $this->create($request),
            'update' => $this->update($request),
            'enable' => $this->toggle($request, true),
            'disable' => $this->toggle($request, false),
            'delete' => $this->delete($request),
            default => $this->error('Acción inválida. Usá: list, create, update, enable, disable, delete.'),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['list', 'create', 'update', 'enable', 'disable', 'delete'])
                ->description('Acción a ejecutar')
                ->required(),
            'key' => $schema->string()->description('Clave del agente (para update/enable/disable/delete)'),
            'name' => $schema->string()->description('Nombre (create/update)'),
            'description' => $schema->string()->description('Descripción corta'),
            'instructions' => $schema->string()->description('Instrucciones permanentes del agente'),
            'schedule_type' => $schema->string()->enum(['interval', 'cron'])->description('Tipo de schedule'),
            'schedule_value' => $schema->string()->description('Intervalo (15m,30m,1h,6h,12h,daily,weekly) o cron'),
            'tools_policy' => $schema->object()->description('{"internal":["finance_query"],"integrations":["github"]|["*"]|[]}'),
        ];
    }

    protected function list(): string
    {
        $agents = AgentDefinition::query()
            ->forUser($this->user)
            ->orderBy('name')
            ->get()
            ->map(fn (AgentDefinition $definition): array => [
                'key' => $definition->key,
                'name' => $definition->name,
                'enabled' => $definition->enabled,
                'schedule' => $definition->schedule_type === 'cron'
                    ? $definition->schedule_value
                    : $definition->schedule_value,
                'next_run_at' => $definition->next_run_at?->toIso8601String(),
                'runs' => $definition->agentRuns()->count(),
            ]);

        return json_encode(['agents' => $agents], JSON_PRETTY_PRINT);
    }

    protected function create(Request $request): string
    {
        try {
            $definition = $this->service->create($this->user, [
                'key' => $request['key'] ?? null,
                'name' => (string) ($request['name'] ?? ''),
                'description' => $request['description'] ?? null,
                'instructions' => (string) ($request['instructions'] ?? ''),
                'schedule_type' => (string) ($request['schedule_type'] ?? 'interval'),
                'schedule_value' => (string) ($request['schedule_value'] ?? '1h'),
                'tools_policy' => (array) ($request['tools_policy'] ?? ['internal' => [], 'integrations' => []]),
            ]);
        } catch (ValidationException $e) {
            return $this->error(implode(' ', array_merge(...array_values($e->errors()))));
        }

        return json_encode([
            'success' => true,
            'message' => 'Agente creado.',
            'agent' => ['key' => $definition->key, 'name' => $definition->name, 'next_run_at' => $definition->next_run_at?->toIso8601String()],
        ]);
    }

    protected function update(Request $request): string
    {
        $definition = $this->find((string) ($request['key'] ?? ''));

        if (! $definition) {
            return $this->error('Agente no encontrado.');
        }

        $data = array_filter([
            'name' => $request['name'] ?? null,
            'description' => $request['description'] ?? null,
            'instructions' => $request['instructions'] ?? null,
            'schedule_type' => $request['schedule_type'] ?? null,
            'schedule_value' => $request['schedule_value'] ?? null,
            'tools_policy' => isset($request['tools_policy']) ? (array) $request['tools_policy'] : null,
        ], fn (mixed $value): bool => $value !== null);

        try {
            $this->service->update($definition, $data);
        } catch (ValidationException $e) {
            return $this->error(implode(' ', array_merge(...array_values($e->errors()))));
        }

        return json_encode(['success' => true, 'message' => 'Agente actualizado.']);
    }

    protected function toggle(Request $request, bool $enabled): string
    {
        $definition = $this->find((string) ($request['key'] ?? ''));

        if (! $definition) {
            return $this->error('Agente no encontrado.');
        }

        $this->service->toggle($definition, $enabled);

        return json_encode([
            'success' => true,
            'message' => $enabled ? 'Agente habilitado.' : 'Agente deshabilitado.',
        ]);
    }

    protected function delete(Request $request): string
    {
        $definition = $this->find((string) ($request['key'] ?? ''));

        if (! $definition) {
            return $this->error('Agente no encontrado.');
        }

        $this->service->delete($definition);

        return json_encode(['success' => true, 'message' => 'Agente eliminado.']);
    }

    protected function find(string $key): ?AgentDefinition
    {
        if ($key === '') {
            return null;
        }

        return AgentDefinition::query()
            ->forUser($this->user)
            ->where('key', $key)
            ->first();
    }

    protected function error(string $message): string
    {
        return json_encode(['success' => false, 'error' => $message]);
    }
}
