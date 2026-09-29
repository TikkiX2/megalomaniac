<?php

use App\Models\Supplement;
use App\Models\SupplementLog;
use App\Models\User;
use App\Services\Supplement\SupplementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

uses(RefreshDatabase::class);

it('creates, updates and deletes supplements', function () {
    $user = User::factory()->create();
    $service = app(SupplementService::class);

    $supplement = $service->create($user, [
        'name' => 'Creatina',
        'stock_quantity' => 30,
        'low_stock_threshold' => 5,
    ]);

    expect($supplement->user_id)->toBe($user->id);

    $updated = $service->update($user, $supplement, ['stock_quantity' => 25]);
    expect($updated->fresh()->stock_quantity)->toBe(25);

    $service->delete($user, $supplement);
    expect(Supplement::find($supplement->id))->toBeNull();
});

it('requires name and stock thresholds', function () {
    $user = User::factory()->create();

    expect(fn () => app(SupplementService::class)->create($user, ['name' => 'X']))
        ->toThrow(InvalidArgumentException::class);
});

it('logs intake and decrements stock', function () {
    $user = User::factory()->create();
    $service = app(SupplementService::class);

    $supplement = $service->create($user, [
        'name' => 'Vitamina D',
        'stock_quantity' => 2,
        'low_stock_threshold' => 1,
    ]);

    $log = $service->logIntake($user, $supplement);

    expect($log)->toBeInstanceOf(SupplementLog::class)
        ->and($log->user_id)->toBe($user->id)
        ->and($supplement->fresh()->stock_quantity)->toBe(1)
        ->and($service->recentLogs($user))->toHaveCount(1);

    $intruder = User::factory()->create();
    expect(fn () => $service->logIntake($intruder, $supplement))
        ->toThrow(AuthorizationException::class);
});
