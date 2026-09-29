<?php

use App\Ai\Tools\GroceryActionTool;
use App\Models\GroceryItem;
use App\Models\GroceryPriceHistory;
use App\Models\User;
use App\Services\Grocery\GroceryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function groceryTool(User $user): GroceryActionTool
{
    return new GroceryActionTool($user, app(GroceryService::class));
}

it('adds, updates, consumes and restocks grocery items through the chat tool', function () {
    $user = User::factory()->create();

    $added = json_decode((string) groceryTool($user)->handle(new Request([
        'action' => 'add_grocery_item',
        'name' => 'Leche',
        'quantity' => 2,
        'target_stock' => 5,
    ])), true);

    expect($added['success'])->toBeTrue();
    $itemId = $added['grocery_item']['id'];

    $updated = json_decode((string) groceryTool($user)->handle(new Request([
        'action' => 'update_grocery_item',
        'grocery_item_id' => $itemId,
        'target_stock' => 8,
    ])), true);

    expect($updated['success'])->toBeTrue()
        ->and((float) $updated['grocery_item']['target_stock'])->toBe(8.0);

    $consumed = json_decode((string) groceryTool($user)->handle(new Request([
        'action' => 'consume_grocery_item',
        'grocery_item_id' => $itemId,
        'quantity' => 1,
    ])), true);

    expect($consumed['success'])->toBeTrue()
        ->and((float) $consumed['grocery_item']['current_stock'])->toBe(1.0);

    $restocked = json_decode((string) groceryTool($user)->handle(new Request([
        'action' => 'restock_grocery_item',
        'grocery_item_id' => $itemId,
        'quantity' => 3,
        'price' => 10,
    ])), true);

    expect($restocked['success'])->toBeTrue()
        ->and((float) $restocked['grocery_item']['current_stock'])->toBe(4.0)
        ->and(GroceryPriceHistory::where('grocery_item_id', $itemId)->count())->toBe(1);

    $deleted = json_decode((string) groceryTool($user)->handle(new Request([
        'action' => 'delete_grocery_item',
        'grocery_item_id' => $itemId,
    ])), true);

    expect($deleted['success'])->toBeTrue()
        ->and(GroceryItem::find($itemId))->toBeNull();
});

it('rejects mutations on foreign grocery items', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $item = GroceryItem::factory()->create(['user_id' => $owner->id]);

    $result = json_decode((string) groceryTool($intruder)->handle(new Request([
        'action' => 'delete_grocery_item',
        'grocery_item_id' => $item->id,
    ])), true);

    expect($result['success'] ?? false)->toBeFalse()
        ->and(GroceryItem::find($item->id))->not->toBeNull();
});
