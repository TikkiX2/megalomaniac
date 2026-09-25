<?php

namespace App\Integrations;

use App\Exceptions\Integrations\UnknownConnectorException;
use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\ExecutionContext;
use App\Integrations\Actions\ParamRules;
use App\Integrations\Contracts\Connector;
use App\Integrations\Enums\ActionAccess;
use App\Integrations\Enums\ApprovalStatus;
use App\Integrations\Enums\ConnectionStatus;
use App\Integrations\Support\SecretRedactor;
use App\Jobs\RunIntegrationActionJob;
use App\Models\ApprovalRequest;
use App\Models\Connection;
use App\Models\IntegrationActionLog;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class IntegrationExecutor
{
    public function __construct(
        private readonly ConnectorRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(Connection $connection, string $actionKey, array $params, ExecutionContext $context): ActionResult
    {
        if (! $connection->enabled) {
            return ActionResult::failure('La conexión está deshabilitada.');
        }

        try {
            $connector = $this->registry->for($connection->kind);
        } catch (UnknownConnectorException) {
            return ActionResult::failure("Conector desconocido [{$connection->kind}].");
        }

        $action = collect($connector->actions())->firstWhere('key', $actionKey);

        if (! $action instanceof Action) {
            return ActionResult::failure("Acción desconocida [{$actionKey}].");
        }

        try {
            $validated = Validator::make(
                $params,
                ParamRules::forParams($action->params),
                [],
                ParamRules::labels($action->params),
            )->validate();
        } catch (ValidationException $e) {
            return ActionResult::failure('Parámetros inválidos.', $e->errors());
        }

        if ($action->access->requiresApproval() && ! $context->preApproved) {
            return $this->queueApproval($connection, $action, $validated, $context);
        }

        return $this->run($connection, $connector, $action, $validated, $context);
    }

    public function testConnection(Connection $connection): ConnectionTestResult
    {
        try {
            $result = $this->registry->for($connection->kind)->test($connection);
        } catch (Throwable $e) {
            $result = ConnectionTestResult::fail($e->getMessage());
        }

        if ($connection->exists) {
            $connection->forceFill([
                'status' => $result->ok ? ConnectionStatus::Ok : ConnectionStatus::Error,
                'status_message' => $result->ok ? null : Str::limit($result->message, 500),
                'last_tested_at' => now(),
            ])->save();
        }

        return $result;
    }

    public function approve(ApprovalRequest $approval, User $user, ?string $note = null): void
    {
        $approval->update([
            'status' => ApprovalStatus::Approved,
            'decided_at' => now(),
            'decided_by' => $user->id,
            'decision_note' => $note,
        ]);

        RunIntegrationActionJob::dispatch(
            $approval->connection_id,
            $this->relativeActionKey($approval),
            $approval->params,
            $approval->id,
            'approval',
        );
    }

    public function reject(ApprovalRequest $approval, User $user, ?string $note = null): void
    {
        $approval->update([
            'status' => ApprovalStatus::Rejected,
            'decided_at' => now(),
            'decided_by' => $user->id,
            'decision_note' => $note,
        ]);

        $approval->log?->update(['status' => 'denied']);
    }

    public function runApproval(ApprovalRequest $approval): void
    {
        $connection = $approval->connection;

        if (! $connection) {
            $approval->update(['status' => ApprovalStatus::Failed]);

            return;
        }

        try {
            $connector = $this->registry->for($connection->kind);
        } catch (UnknownConnectorException) {
            $approval->update(['status' => ApprovalStatus::Failed]);

            return;
        }

        $action = collect($connector->actions())->firstWhere('key', $this->relativeActionKey($approval));

        if (! $action instanceof Action) {
            $approval->update(['status' => ApprovalStatus::Failed]);

            return;
        }

        $result = $this->run(
            $connection,
            $connector,
            $action,
            $approval->params,
            new ExecutionContext(actor: $approval->user, source: 'approval', preApproved: true),
            $approval,
        );

        $approval->update([
            'status' => $result->ok ? ApprovalStatus::Executed : ApprovalStatus::Failed,
            'executed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function queueApproval(Connection $connection, Action $action, array $params, ExecutionContext $context): ActionResult
    {
        $approval = ApprovalRequest::create([
            'connection_id' => $connection->id,
            'user_id' => $connection->user_id,
            'action_key' => $connection->kind.'.'.$action->key,
            'params' => SecretRedactor::redact($params, $action->params),
            'access' => $action->access->value,
            'summary' => $action->label.' · '.$connection->name,
            'rationale' => $context->rationale,
            'status' => ApprovalStatus::Pending->value,
            'expires_at' => now()->addHours((int) config('integrations.approval.ttl_hours')),
        ]);

        IntegrationActionLog::create([
            'connection_id' => $connection->id,
            'user_id' => $connection->user_id,
            'approval_request_id' => $approval->id,
            'action_key' => $connection->kind.'.'.$action->key,
            'access' => $action->access->value,
            'actor_type' => $context->actor?->getMorphClass(),
            'actor_id' => $context->actor?->getKey(),
            'source' => $context->source,
            'params' => SecretRedactor::redact($params, $action->params),
            'status' => 'pending',
        ]);

        return ActionResult::pending($approval->id, $approval->summary);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function run(
        Connection $connection,
        Connector $connector,
        Action $action,
        array $params,
        ExecutionContext $context,
        ?ApprovalRequest $approval = null,
    ): ActionResult {
        if ($this->rateLimited($connection, $action)) {
            return ActionResult::failure('Límite de uso alcanzado; intentá de nuevo en un minuto.');
        }

        $started = microtime(true);

        $log = $approval?->log;

        if ($log) {
            $log->update([
                'status' => 'running',
                'source' => $context->source,
                'actor_type' => $context->actor?->getMorphClass(),
                'actor_id' => $context->actor?->getKey(),
            ]);
        } else {
            $log = IntegrationActionLog::create([
                'connection_id' => $connection->id,
                'user_id' => $connection->user_id,
                'approval_request_id' => $approval?->id,
                'action_key' => $connection->kind.'.'.$action->key,
                'access' => $action->access->value,
                'actor_type' => $context->actor?->getMorphClass(),
                'actor_id' => $context->actor?->getKey(),
                'source' => $context->source,
                'params' => SecretRedactor::redact($params, $action->params),
                'status' => 'running',
            ]);
        }

        try {
            $result = $connector->execute($connection, $action->key, $params);
        } catch (Throwable $e) {
            report($e);
            $result = ActionResult::failure('Error inesperado al ejecutar la acción.');
        }

        $connection->forceFill([
            'last_used_at' => now(),
            'status' => $result->ok ? ConnectionStatus::Ok : ConnectionStatus::Error,
            'status_message' => $result->ok ? null : Str::limit($result->error ?? '', 500),
        ])->save();

        $log->update([
            'status' => $result->ok ? 'success' : 'failed',
            'result_summary' => Str::limit(SecretRedactor::redactString($result->summary), 1000),
            'error' => $result->error ? SecretRedactor::redactString($result->error) : null,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);

        return $result;
    }

    protected function rateLimited(Connection $connection, Action $action): bool
    {
        $limit = $action->access === ActionAccess::Read
            ? (int) config('integrations.limits.read_per_minute')
            : (int) config('integrations.limits.write_per_minute');

        $key = "integrations:{$connection->id}:{$action->access->value}";

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }

    protected function relativeActionKey(ApprovalRequest $approval): string
    {
        $prefix = $approval->connection->kind.'.';

        return str_starts_with($approval->action_key, $prefix)
            ? Str::after($approval->action_key, $prefix)
            : $approval->action_key;
    }
}
