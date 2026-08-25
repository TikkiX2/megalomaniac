<?php

namespace App\Ai\Tools;

use App\Models\GroceryItem;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GroceryQueryTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Query the user\'s grocery inventory: items, stock levels, low stock alerts. Use this to answer questions about groceries, shopping lists, and restocking.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = GroceryItem::where('user_id', $this->user->id);

        if (! empty($request['low_stock'])) {
            $query->whereColumn('quantity', '<=', 'low_stock_threshold');
        }

        if (isset($request['category'])) {
            $query->where('category', $request['category']);
        }

        $items = $query->latest()->get();

        if ($items->isEmpty()) {
            return 'No grocery items found.';
        }

        $lowStock = $items->filter(fn ($item) => $item->quantity <= $item->low_stock_threshold);

        return json_encode([
            'total_items' => $items->count(),
            'low_stock_count' => $lowStock->count(),
            'low_stock_items' => $lowStock->values()->toArray(),
            'items' => $items->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'low_stock' => $schema->boolean()
                ->description('Only show items at or below stock threshold')
                ->default(false),
            'category' => $schema->string()
                ->description('Filter by category'),
        ];
    }
}
