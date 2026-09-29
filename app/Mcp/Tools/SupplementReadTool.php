<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\Supplement\SupplementService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SupplementReadTool extends Tool
{
    protected string $name = 'supplement-read';

    protected string $description = 'Read the authenticated user\'s supplements with low-stock flags and the most recent intake logs.';

    public function __construct(protected SupplementService $supplements) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'low_stock' => $schema->boolean()->description('Only return supplements at or below their low stock threshold (default: false)'),
            'limit' => $schema->integer()->description('Maximum supplements to return (default: 50)')->min(1)->max(100),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $limit = (int) $request->get('limit', 50);

        $supplements = $user->supplements()
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn ($supplement) => [
                ...$supplement->toArray(),
                'is_low_stock' => $supplement->stock_quantity <= $supplement->low_stock_threshold,
            ]);

        if ($request->get('low_stock', false)) {
            $supplements = $supplements->where('is_low_stock', true);
        }

        return Response::structured([
            'supplements' => $supplements->values()->all(),
            'count' => $supplements->count(),
            'recent_logs' => $this->supplements->recentLogs($user, 10)->all(),
        ]);
    }
}
