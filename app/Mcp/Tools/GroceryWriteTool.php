<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\GroceryItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GroceryWriteTool extends Tool
{
    protected string $name = 'grocery-write';

    protected string $description = 'Create grocery items and record consumption for the authenticated user.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform: create_grocery_item, consume_item')->enum(['create_grocery_item', 'consume_item'])->required(),
            'name' => $schema->string()->description('Item name (required for create_grocery_item)')->max(255),
            'category' => $schema->string()->description('Category (for create_grocery_item)')->max(100),
            'current_stock' => $schema->number()->description('Initial stock quantity (for create_grocery_item)')->min(0),
            'target_stock' => $schema->number()->description('Target stock level (for create_grocery_item)')->min(0),
            'unit' => $schema->string()->description('Unit of measurement (for create_grocery_item)')->max(50),
            'price' => $schema->number()->description('Price per unit (for create_grocery_item)')->min(0),
            'grocery_item_id' => $schema->integer()->description('Grocery item ID (required for consume_item)'),
            'quantity' => $schema->number()->description('Quantity consumed (required for consume_item)')->min(0.01),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $action = (string) $request->get('action');

        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        return match ($action) {
            'create_grocery_item' => $this->createItem($request, $user),
            'consume_item' => $this->consumeItem($request, $user),
            default => Response::error("Invalid action: {$action}"),
        };
    }

    private function createItem(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'current_stock' => ['nullable', 'numeric', 'min:0'],
            'target_stock' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $item = GroceryItem::create([
            'user_id' => $user->id,
            'name' => $request->get('name'),
            'category' => $request->get('category'),
            'current_stock' => $request->get('current_stock', 0),
            'target_stock' => $request->get('target_stock', 0),
            'unit' => $request->get('unit'),
            'price' => $request->get('price'),
            'purchased_at' => now(),
        ]);

        return Response::structured([
            'grocery_item' => $item->fresh(),
            'message' => 'Grocery item created successfully.',
        ]);
    }

    private function consumeItem(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'grocery_item_id' => ['required', 'integer', 'exists:grocery_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $item = GroceryItem::where('id', $request->get('grocery_item_id'))
            ->where('user_id', $user->id)
            ->first();

        if (! $item) {
            return Response::error('Grocery item not found or unauthorized.');
        }

        $quantity = (float) $request->get('quantity');

        if ($quantity > (float) $item->current_stock) {
            return Response::error('Cannot consume more than current stock.');
        }

        $item->update([
            'current_stock' => max(0, (float) $item->current_stock - $quantity),
        ]);

        return Response::structured([
            'grocery_item' => $item->fresh(),
            'consumed' => $quantity,
            'remaining' => (float) $item->current_stock,
            'is_low_stock' => (float) $item->current_stock <= (float) $item->target_stock,
            'message' => "Consumed {$quantity} {$item->unit} of {$item->name}.",
        ]);
    }
}
