<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Debt;
use App\Models\Income;
use App\Models\Purchase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class FinanceReadTool extends Tool
{
    protected string $name = 'finance-read';

    protected string $description = 'Read the authenticated user\'s financial data: purchases, incomes, and debts with optional filters.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('Data type to read: purchases, incomes, or debts (default: purchases)')->enum(['purchases', 'incomes', 'debts']),
            'days' => $schema->integer()->description('Number of past days to include (default: 30)')->min(1)->max(365),
            'limit' => $schema->integer()->description('Maximum records to return (default: 20)')->min(1)->max(100),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $type = (string) $request->get('type', 'purchases');
        $days = (int) $request->get('days', 30);
        $limit = (int) $request->get('limit', 20);

        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $dateColumn = match ($type) {
            'purchases' => 'purchase_date',
            'incomes' => 'received_date',
            'debts' => 'due_date',
            default => 'created_at',
        };

        $query = match ($type) {
            'purchases' => Purchase::where('user_id', $user->id)->with(['currency', 'category']),
            'incomes' => Income::where('user_id', $user->id)->with(['currency', 'incomeSource']),
            'debts' => Debt::where('user_id', $user->id)->with(['currency', 'purchase']),
            default => null,
        };

        if (! $query) {
            return Response::error("Invalid type: {$type}");
        }

        $records = $query->where($dateColumn, '>=', now()->subDays($days))
            ->latest($dateColumn)
            ->limit($limit)
            ->get();

        return Response::structured([
            'type' => $type,
            'records' => $records->all(),
            'count' => $records->count(),
            'days' => $days,
            'limit' => $limit,
        ]);
    }
}
