<?php

use App\Integrations\Actions\ExecutionContext;
use App\Integrations\ConnectorRegistry;
use App\Integrations\Enums\ApprovalStatus;
use App\Jobs\RunIntegrationActionJob;
use App\Models\ApprovalRequest;
use App\Models\Connection;
use App\Models\IntegrationActionLog;
use Illuminate\Support\Facades\Bus;
use Tests\Support\FakeConnector;

beforeEach(function () {
    FakeConnector::reset();

    app()->instance(ConnectorRegistry::class, new ConnectorRegistry([FakeConnector::class]));
});

it('queues an approval for agent writes', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);

    $result = executor()->execute($connection, 'write', ['x' => 1], ExecutionContext::forAgent($connection->user, 'motivo'));

    expect($result->pending)->toBeTrue()
        ->and($result->approvalId)->toBeInt()
        ->and(ApprovalRequest::pending()->count())->toBe(1)
        ->and(ApprovalRequest::first()->rationale)->toBe('motivo')
        ->and(ApprovalRequest::first()->action_key)->toBe('fake.write')
        ->and(ApprovalRequest::first()->summary)->toContain('Write')
        ->and(FakeConnector::$calls)->toBe(0)
        ->and(IntegrationActionLog::first()->status)->toBe('pending')
        ->and(IntegrationActionLog::first()->approval_request_id)->toBe(ApprovalRequest::first()->id);
});

it('executes ui writes immediately', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);

    $result = executor()->execute($connection, 'write', [], ExecutionContext::forUi($connection->user));

    expect($result->ok)->toBeTrue()->and(FakeConnector::$calls)->toBe(1)
        ->and(ApprovalRequest::count())->toBe(0);
});

it('approves and dispatches the job', function () {
    Bus::fake();

    $connection = Connection::factory()->create(['kind' => 'fake']);
    $approval = ApprovalRequest::factory()->create([
        'connection_id' => $connection->id,
        'user_id' => $connection->user_id,
        'action_key' => 'fake.write',
        'access' => 'write',
        'params' => [],
    ]);

    executor()->approve($approval, $approval->user, 'ok');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($approval->fresh()->decision_note)->toBe('ok')
        ->and($approval->fresh()->decided_by)->toBe($approval->user_id);

    Bus::assertDispatched(RunIntegrationActionJob::class);
});

it('rejects an approval and denies its log', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);
    $approval = ApprovalRequest::factory()->create([
        'connection_id' => $connection->id,
        'user_id' => $connection->user_id,
        'action_key' => 'fake.write',
        'access' => 'write',
        'params' => [],
    ]);

    IntegrationActionLog::factory()->create([
        'connection_id' => $connection->id,
        'user_id' => $connection->user_id,
        'approval_request_id' => $approval->id,
        'action_key' => 'fake.write',
        'status' => 'pending',
    ]);

    executor()->reject($approval, $approval->user, 'no');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Rejected)
        ->and(IntegrationActionLog::where('approval_request_id', $approval->id)->first()->status)->toBe('denied');
});

it('runs an approved action and marks it executed', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);
    $approval = ApprovalRequest::factory()->create([
        'connection_id' => $connection->id,
        'user_id' => $connection->user_id,
        'action_key' => 'fake.write',
        'access' => 'write',
        'params' => [],
    ]);

    IntegrationActionLog::factory()->create([
        'connection_id' => $connection->id,
        'user_id' => $connection->user_id,
        'approval_request_id' => $approval->id,
        'action_key' => 'fake.write',
        'status' => 'pending',
    ]);

    $job = new RunIntegrationActionJob($connection->id, 'write', [], $approval->id, 'approval');
    app()->call([$job, 'handle']);

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Executed)
        ->and($approval->fresh()->executed_at)->not->toBeNull()
        ->and(IntegrationActionLog::where('approval_request_id', $approval->id)->first()->status)->toBe('success')
        ->and(FakeConnector::$calls)->toBe(1);
});

it('marks the approval failed when the action fails', function () {
    FakeConnector::$shouldFail = true;

    $connection = Connection::factory()->create(['kind' => 'fake']);
    $approval = ApprovalRequest::factory()->create([
        'connection_id' => $connection->id,
        'user_id' => $connection->user_id,
        'action_key' => 'fake.write',
        'access' => 'write',
        'params' => [],
    ]);

    $job = new RunIntegrationActionJob($connection->id, 'write', [], $approval->id, 'approval');
    app()->call([$job, 'handle']);

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Failed);
});
