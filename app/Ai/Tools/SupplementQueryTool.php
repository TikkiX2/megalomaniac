<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Services\Supplement\SupplementService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SupplementQueryTool implements Tool
{
    public function __construct(
        protected User $user,
        protected SupplementService $supplements,
    ) {}

    public function description(): Stringable|string
    {
        return 'Query the user\'s supplements: inventory with low-stock flags and the most recent intake logs.';
    }

    public function handle(Request $request): Stringable|string
    {
        $supplements = $this->user->supplements()->orderBy('name')->get()->map(fn ($supplement) => [
            ...$supplement->toArray(),
            'is_low_stock' => $supplement->stock_quantity <= $supplement->low_stock_threshold,
        ]);

        if ($request['low_stock'] ?? false) {
            $supplements = $supplements->where('is_low_stock', true);
        }

        return json_encode([
            'supplements' => $supplements->values()->all(),
            'low_stock_count' => $supplements->where('is_low_stock', true)->count(),
            'recent_logs' => $this->supplements->recentLogs($this->user, 10)->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'low_stock' => $schema->boolean()
                ->description('Only return supplements at or below their low stock threshold')
                ->default(false),
        ];
    }
}
