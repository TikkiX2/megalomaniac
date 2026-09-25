<?php

use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\ExecutionContext;
use App\Integrations\Enums\ActionAccess;
use App\Models\User;

it('knows which accesses require approval', function () {
    expect(ActionAccess::Read->requiresApproval())->toBeFalse()
        ->and(ActionAccess::Write->requiresApproval())->toBeTrue()
        ->and(ActionAccess::Destructive->requiresApproval())->toBeTrue();
});

it('builds action results', function () {
    expect(ActionResult::success('ok', ['a' => 1])->ok)->toBeTrue()
        ->and(ActionResult::failure('boom')->error)->toBe('boom')
        ->and(ActionResult::pending(42, 'Reiniciar contenedor')->approvalId)->toBe(42)
        ->and(ActionResult::pending(42, 'Reiniciar contenedor')->pending)->toBeTrue();
});

it('builds execution contexts for ui and agent', function () {
    $user = new User;

    $ui = ExecutionContext::forUi($user);
    expect($ui->source)->toBe('ui')->and($ui->preApproved)->toBeTrue();

    $agent = ExecutionContext::forAgent($user, 'Pedido por chat');
    expect($agent->source)->toBe('chat')->and($agent->preApproved)->toBeFalse()
        ->and($agent->rationale)->toBe('Pedido por chat');

    $schedule = ExecutionContext::forSchedule($user);
    expect($schedule->source)->toBe('schedule')->and($schedule->preApproved)->toBeFalse();
});

it('builds connection test results', function () {
    expect(ConnectionTestResult::ok('todo bien', ['login' => 'me'])->ok)->toBeTrue()
        ->and(ConnectionTestResult::fail('rompió')->message)->toBe('rompió');
});
