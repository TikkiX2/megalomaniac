<?php

use App\Models\GroceryItem;
use App\Models\GroceryPriceHistory;
use App\Models\User;
use App\Services\Grocery\GroceryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates, updates and deletes grocery items', function () {
    $user = User::factory()->create();
    $service = app(GroceryService::class);

    $item = $service->create($user, [
        'name' => 'Leche',
        'current_stock' => 1,
        'target_stock' => 5,
    ]);

    expect($item->user_id)->toBe($user->id);

    $updated = $service->update($user, $item, ['target_stock' => 8]);
    expect((float) $updated->fresh()->target_stock)->toBe(8.0);

    $service->delete($user, $item);
    expect(GroceryItem::find($item->id))->toBeNull();
});

it('requires name and stock levels', function () {
    $user = User::factory()->create();

    expect(fn () => app(GroceryService::class)->create($user, ['name' => 'X']))
        ->toThrow(InvalidArgumentException::class);
});

it('consumes stock without going below zero', function () {
    $user = User::factory()->create();
    $service = app(GroceryService::class);

    $item = $service->create($user, [
        'name' => 'Arroz',
        'current_stock' => 1,
        'target_stock' => 5,
    ]);

    $service->consume($user, $item, 0.5);
    expect((float) $item->fresh()->current_stock)->toBe(0.5);

    $service->consume($user, $item, 5);
    expect((float) $item->fresh()->current_stock)->toBe(0.0);

    expect(fn () => $service->consume($user, $item, 0))
        ->toThrow(InvalidArgumentException::class);
});

it('restocks and records price history', function () {
    $user = User::factory()->create();
    $service = app(GroceryService::class);

    $item = $service->create($user, [
        'name' => 'Café',
        'current_stock' => 1,
        'target_stock' => 10,
    ]);

    $service->restock($user, $item, 4, 12.5, now()->toDateString());

    expect((float) $item->fresh()->current_stock)->toBe(5.0)
        ->and((float) $item->fresh()->price)->toBe(12.5)
        ->and(GroceryPriceHistory::where('grocery_item_id', $item->id)->count())->toBe(1);
});

it('enforces ownership on mutations', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $service = app(GroceryService::class);

    $item = $service->create($owner, [
        'name' => 'Leche',
        'current_stock' => 1,
        'target_stock' => 5,
    ]);

    expect(fn () => $service->update($intruder, $item, ['target_stock' => 1]))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $service->delete($intruder, $item))
        ->toThrow(AuthorizationException::class);

    expect(GroceryItem::find($item->id))->not->toBeNull();
});
