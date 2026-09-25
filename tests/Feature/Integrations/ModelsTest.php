<?php

use App\Integrations\Enums\ActionAccess;
use App\Integrations\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\Connection;
use App\Models\IntegrationActionLog;
use App\Models\User;

it('encrypts connection credentials at rest', function () {
    $connection = Connection::factory()->create([
        'credentials' => ['token' => 'super-secret'],
    ]);

    expect($connection->credentials['token'])->toBe('super-secret')
        ->and($connection->getRawOriginal('credentials'))->not->toContain('super-secret');
});

it('scopes connections by user and enabled', function () {
    $user = User::factory()->create();
    Connection::factory()->for($user)->create(['enabled' => true]);
    Connection::factory()->for($user)->create(['enabled' => false]);
    Connection::factory()->create();

    expect(Connection::query()->forUser($user)->enabled()->count())->toBe(1);
});

it('creates approvals with defaults and pending scope', function () {
    $approval = ApprovalRequest::factory()->create();

    expect($approval->status)->toBe(ApprovalStatus::Pending)
        ->and(ApprovalRequest::query()->pending()->count())->toBe(1)
        ->and($approval->connection)->toBeInstanceOf(Connection::class);
});

it('links action logs to approvals and connections', function () {
    $log = IntegrationActionLog::factory()->create();

    expect($log->connection)->toBeInstanceOf(Connection::class)
        ->and($log->params)->toBeArray()
        ->and($log->access)->toBe(ActionAccess::Read);
});
