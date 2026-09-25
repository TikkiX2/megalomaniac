<?php

use App\Integrations\Actions\ExecutionContext;
use App\Models\Connection;
use App\Models\IntegrationActionLog;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\FakeConnector;

beforeEach(function () {
    FakeConnector::reset();
    RateLimiter::clear('integrations:1:read');
    RateLimiter::clear('integrations:1:write');
});

it('executes read actions and logs them redacted', function () {
    $connection = Connection::factory()->for(User::factory())->create(['kind' => 'fake']);

    $result = executor()->execute($connection, 'ping', ['secret_token' => 'abc'], ExecutionContext::forAgent($connection->user));

    expect($result->ok)->toBeTrue()->and($result->data)->toBe(['key' => 'ping']);

    $log = IntegrationActionLog::first();
    expect($log->status)->toBe('success')
        ->and($log->params['secret_token'])->toBe('[redacted]')
        ->and($log->source)->toBe('chat')
        ->and($log->action_key)->toBe('fake.ping')
        ->and($log->actor_id)->toBe($connection->user_id)
        ->and($log->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('rejects invalid params without calling the connector', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);

    $result = executor()->execute(
        $connection,
        'ping',
        ['secret_token' => ['array-invalido']],
        ExecutionContext::forUi($connection->user),
    );

    expect($result->ok)->toBeFalse()->and(FakeConnector::$calls)->toBe(0);
});

it('fails on unknown action and disabled connection', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);

    expect(executor()->execute($connection, 'nope', [], ExecutionContext::forUi($connection->user))->ok)->toBeFalse();

    $connection->update(['enabled' => false]);
    expect(executor()->execute($connection, 'ping', [], ExecutionContext::forUi($connection->user))->ok)->toBeFalse();
});

it('enforces the read rate limit', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);
    config(['integrations.limits.read_per_minute' => 1]);

    $first = executor()->execute($connection, 'ping', [], ExecutionContext::forUi($connection->user));
    $second = executor()->execute($connection, 'ping', [], ExecutionContext::forUi($connection->user));

    expect($first->ok)->toBeTrue()->and($second->ok)->toBeFalse()
        ->and($second->error)->toContain('Límite');
});

it('updates connection status after failures', function () {
    FakeConnector::$shouldFail = true;
    $connection = Connection::factory()->create(['kind' => 'fake']);

    executor()->execute($connection, 'ping', [], ExecutionContext::forUi($connection->user));

    expect($connection->fresh()->status->value)->toBe('error')
        ->and($connection->fresh()->status_message)->toContain('falló')
        ->and($connection->fresh()->last_used_at)->not->toBeNull();
});

it('tests a connection and persists the result when it exists', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);

    $result = executor()->testConnection($connection);

    expect($result->ok)->toBeTrue()
        ->and($connection->fresh()->last_tested_at)->not->toBeNull()
        ->and($connection->fresh()->status->value)->toBe('ok');
});

it('does not persist results for unsaved draft connections', function () {
    $connection = Connection::factory()->make(['kind' => 'fake']);

    executor()->testConnection($connection);

    expect($connection->exists)->toBeFalse()
        ->and(Connection::count())->toBe(0);
});
