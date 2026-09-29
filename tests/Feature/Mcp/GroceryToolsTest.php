<?php

use App\Mcp\Servers\MegalomaniacServer;
use App\Mcp\Tools\GroceryReadTool;
use App\Mcp\Tools\GroceryWriteTool;
use App\Models\GroceryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates, restocks and consumes grocery items through mcp', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(GroceryWriteTool::class, [
            'action' => 'create_grocery_item',
            'name' => 'Leche',
            'current_stock' => 1,
            'target_stock' => 5,
        ])
        ->assertOk();

    $item = GroceryItem::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(GroceryWriteTool::class, [
            'action' => 'restock_item',
            'item_id' => $item->id,
            'quantity' => 4,
            'price' => 9.5,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('grocery_item.current_stock', '5.00')
            ->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(GroceryWriteTool::class, [
            'action' => 'consume_item',
            'item_id' => $item->id,
            'quantity' => 2,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('grocery_item.current_stock', '3.00')
            ->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(GroceryReadTool::class, ['low_stock' => false])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('count', 1)
            ->etc());
});

it('rejects grocery mutations for other users', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $item = GroceryItem::factory()->create(['user_id' => $owner->id]);

    MegalomaniacServer::actingAs($intruder)
        ->tool(GroceryWriteTool::class, ['action' => 'delete_grocery_item', 'item_id' => $item->id])
        ->assertHasErrors(['not found']);

    expect(GroceryItem::find($item->id))->not->toBeNull();
});
