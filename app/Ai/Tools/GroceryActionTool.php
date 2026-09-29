<?php

namespace App\Ai\Tools;

use App\Models\GroceryItem;
use App\Models\User;
use App\Services\Grocery\GroceryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GroceryActionTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        protected User $user,
        protected GroceryService $grocery,
    ) {}

    public function description(): Stringable|string
    {
        return 'Manage the user\'s grocery inventory: add, update or delete items, consume stock and restock purchases (price history is recorded).';
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Va a '.($this->actionLabels()[$request['action'] ?? ''] ?? 'modificar tus compras').'.');
    }

    /**
     * @return array<string, string>
     */
    protected function actionLabels(): array
    {
        return [
            'add_grocery_item' => 'añadir un producto a la lista de compras',
            'update_grocery_item' => 'actualizar un producto',
            'delete_grocery_item' => 'eliminar un producto',
            'consume_grocery_item' => 'consumir stock de un producto',
            'restock_grocery_item' => 'reponer stock de un producto',
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return match ($request['action'] ?? '') {
                'add_grocery_item' => $this->add($request),
                'update_grocery_item' => $this->update($request),
                'delete_grocery_item' => $this->delete($request),
                'consume_grocery_item' => $this->consume($request),
                'restock_grocery_item' => $this->restock($request),
                default => $this->error('Invalid action. Use: add_grocery_item, update_grocery_item, delete_grocery_item, consume_grocery_item, restock_grocery_item'),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return $this->error($exception->getMessage());
        }
    }

    private function add(Request $request): string
    {
        $item = $this->grocery->create($this->user, [
            'name' => $request['name'] ?? null,
            'category' => $request['category'] ?? null,
            'current_stock' => $request['quantity'] ?? $request['current_stock'] ?? 1,
            'target_stock' => $request['target_stock'] ?? 1,
            'unit' => $request['unit'] ?? null,
            'price' => $request['price'] ?? null,
        ]);

        return $this->success('Grocery item added', ['grocery_item' => $item->toArray()]);
    }

    private function update(Request $request): string
    {
        $item = $this->find($request['grocery_item_id'] ?? null);

        if (! $item) {
            return $this->error('Grocery item not found');
        }

        $data = $this->filtered([
            'name' => $request['name'] ?? null,
            'category' => $request['category'] ?? null,
            'current_stock' => $request['current_stock'] ?? null,
            'target_stock' => $request['target_stock'] ?? null,
            'unit' => $request['unit'] ?? null,
            'price' => $request['price'] ?? null,
        ]);

        $item = $this->grocery->update($this->user, $item, $data);

        return $this->success('Grocery item updated', ['grocery_item' => $item->toArray()]);
    }

    private function delete(Request $request): string
    {
        $item = $this->find($request['grocery_item_id'] ?? null);

        if (! $item) {
            return $this->error('Grocery item not found');
        }

        $this->grocery->delete($this->user, $item);

        return $this->success('Grocery item deleted', ['grocery_item' => ['id' => $item->id]]);
    }

    private function consume(Request $request): string
    {
        $item = $this->find($request['grocery_item_id'] ?? null);

        if (! $item) {
            return $this->error('Grocery item not found');
        }

        $item = $this->grocery->consume($this->user, $item, (float) ($request['quantity'] ?? 1));

        return $this->success('Stock consumed', [
            'grocery_item' => $item->only(['id', 'name', 'current_stock', 'target_stock']),
        ]);
    }

    private function restock(Request $request): string
    {
        $item = $this->find($request['grocery_item_id'] ?? null);

        if (! $item) {
            return $this->error('Grocery item not found');
        }

        $item = $this->grocery->restock(
            $this->user,
            $item,
            (float) ($request['quantity'] ?? 0),
            isset($request['price']) ? (float) $request['price'] : null,
            $request['date'] ?? null,
        );

        return $this->success('Stock restocked', [
            'grocery_item' => $item->only(['id', 'name', 'current_stock', 'target_stock', 'price']),
        ]);
    }

    private function find(mixed $id): ?GroceryItem
    {
        if (! $id) {
            return null;
        }

        return $this->user->groceryItems()->find((int) $id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filtered(array $data): array
    {
        return array_filter($data, fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function success(string $message, array $payload = []): string
    {
        return json_encode(array_merge([
            'success' => true,
            'message' => $message,
        ], $payload), JSON_PRETTY_PRINT);
    }

    private function error(string $message): string
    {
        return json_encode(['success' => false, 'error' => $message], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['add_grocery_item', 'update_grocery_item', 'delete_grocery_item', 'consume_grocery_item', 'restock_grocery_item'])
                ->description('Action to perform')
                ->required(),
            'grocery_item_id' => $schema->integer()->description('Grocery item ID (update, delete, consume, restock)'),
            'name' => $schema->string()->description('Item name (add, update)'),
            'category' => $schema->string()->description('Category (add, update)'),
            'current_stock' => $schema->number()->description('Current stock (add, update)'),
            'target_stock' => $schema->number()->description('Target stock (add, update)'),
            'quantity' => $schema->number()->description('Quantity to consume or restock (default 1 consume)'),
            'unit' => $schema->string()->description('Unit (add, update)'),
            'price' => $schema->number()->description('Price (add, update, restock)'),
            'date' => $schema->string()->description('Purchase date YYYY-MM-DD (restock)'),
        ];
    }
}
