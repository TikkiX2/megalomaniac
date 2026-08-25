<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\GroceryItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GroceryReadTool extends Tool
{
    protected string $name = 'grocery-read';

    protected string $description = 'Read the authenticated user\'s grocery inventory with optional low-stock filter.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'low_stock' => $schema->boolean()->description('If true, only return items where current_stock <= target_stock (default: false)'),
            'limit' => $schema->integer()->description('Maximum number of items to return (default: 50)')->minimum(1)->maximum(200),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $lowStock = (bool) $request->get('low_stock', false);
        $limit = (int) $request->get('limit', 50);

        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $query = GroceryItem::where('user_id', $user->id);

        if ($lowStock) {
            $query->whereColumn('current_stock', '<=', 'target_stock');
        }

        $items = $query->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (GroceryItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'current_stock' => $item->current_stock,
                'target_stock' => $item->target_stock,
                'unit' => $item->unit,
                'price' => $item->price,
                'purchased_at' => $item->purchased_at?->toISOString(),
                'is_low_stock' => (float) $item->current_stock <= (float) $item->target_stock,
            ]);

        return Response::structured([
            'grocery_items' => $items->all(),
            'count' => $items->count(),
            'low_stock_filter' => $lowStock,
            'limit' => $limit,
        ]);
    }
}
