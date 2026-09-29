<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\GroceryItem;
use App\Services\Grocery\GroceryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GroceryWriteTool extends Tool
{
    protected string $name = 'grocery-write';

    protected string $description = 'Manage the authenticated user\'s grocery inventory: create, update or delete items, consume stock and restock purchases (price history is recorded).';

    public function __construct(protected GroceryService $grocery) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description('Action: create_grocery_item, update_grocery_item, delete_grocery_item, consume_item, restock_item')
                ->enum(['create_grocery_item', 'update_grocery_item', 'delete_grocery_item', 'consume_item', 'restock_item'])
                ->required(),
            'item_id' => $schema->integer()->description('Grocery item ID (update, delete, consume, restock)'),
            'name' => $schema->string()->description('Item name (create, update)'),
            'category' => $schema->string()->description('Category (create, update)'),
            'current_stock' => $schema->number()->description('Current stock (create, update)'),
            'target_stock' => $schema->number()->description('Target stock (create, update)'),
            'quantity' => $schema->number()->description('Quantity to consume or restock (create uses 1 by default)'),
            'unit' => $schema->string()->description('Unit (create, update)'),
            'price' => $schema->number()->description('Price (create, update, restock)'),
            'date' => $schema->string()->description('Purchase date YYYY-MM-DD (restock)'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $action = (string) $request->get('action');

        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        try {
            return match ($action) {
                'create_grocery_item' => $this->create($request, $user),
                'update_grocery_item' => $this->update($request, $user),
                'delete_grocery_item' => $this->delete($request, $user),
                'consume_item' => $this->consume($request, $user),
                'restock_item' => $this->restock($request, $user),
                default => Response::error("Invalid action: {$action}"),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }
    }

    private function create(Request $request, $user): Response|ResponseFactory
    {
        $item = $this->grocery->create($user, [
            'name' => $request->get('name'),
            'category' => $request->get('category'),
            'current_stock' => $request->get('current_stock', $request->get('quantity', 1)),
            'target_stock' => $request->get('target_stock', 1),
            'unit' => $request->get('unit'),
            'price' => $request->get('price'),
        ]);

        return Response::structured([
            'grocery_item' => $item->fresh(),
            'message' => 'Grocery item created successfully.',
        ]);
    }

    private function update(Request $request, $user): Response|ResponseFactory
    {
        $item = $this->find($request, $user);

        if (! $item instanceof GroceryItem) {
            return $item;
        }

        $item = $this->grocery->update($user, $item, array_filter([
            'name' => $request->get('name'),
            'category' => $request->get('category'),
            'current_stock' => $request->get('current_stock'),
            'target_stock' => $request->get('target_stock'),
            'unit' => $request->get('unit'),
            'price' => $request->get('price'),
        ], fn ($value) => $value !== null));

        return Response::structured([
            'grocery_item' => $item,
            'message' => 'Grocery item updated successfully.',
        ]);
    }

    private function delete(Request $request, $user): Response|ResponseFactory
    {
        $item = $this->find($request, $user);

        if (! $item instanceof GroceryItem) {
            return $item;
        }

        $this->grocery->delete($user, $item);

        return Response::structured(['message' => 'Grocery item deleted successfully.']);
    }

    private function consume(Request $request, $user): Response|ResponseFactory
    {
        $item = $this->find($request, $user);

        if (! $item instanceof GroceryItem) {
            return $item;
        }

        $item = $this->grocery->consume($user, $item, (float) $request->get('quantity', 1));

        return Response::structured([
            'grocery_item' => $item->only(['id', 'name', 'current_stock', 'target_stock']),
            'message' => 'Stock consumed successfully.',
        ]);
    }

    private function restock(Request $request, $user): Response|ResponseFactory
    {
        $item = $this->find($request, $user);

        if (! $item instanceof GroceryItem) {
            return $item;
        }

        $item = $this->grocery->restock(
            $user,
            $item,
            (float) $request->get('quantity', 0),
            $request->get('price') !== null ? (float) $request->get('price') : null,
            $request->get('date'),
        );

        return Response::structured([
            'grocery_item' => $item->only(['id', 'name', 'current_stock', 'target_stock', 'price']),
            'message' => 'Stock restocked successfully.',
        ]);
    }

    private function find(Request $request, $user): GroceryItem|Response|ResponseFactory
    {
        $item = GroceryItem::where('user_id', $user->id)->find($request->get('item_id', 0));

        if (! $item) {
            return Response::error('Grocery item not found or unauthorized.');
        }

        return $item;
    }
}
