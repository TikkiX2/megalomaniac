<?php

namespace App\Ai\Tools;

use App\Models\Debt;
use App\Models\Income;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FinanceQueryTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Query the user\'s financial data: purchases, incomes, debts, credit cards. Use this to answer questions about spending, income, budgets, and debt.';
    }

    public function handle(Request $request): Stringable|string
    {
        $type = $request['type'] ?? 'purchases';
        $days = $request['days'] ?? 30;

        $data = match ($type) {
            'purchases' => Purchase::with(['category', 'currency'])
                ->where('user_id', $this->user->id)
                ->where('date', '>=', now()->subDays($days))
                ->latest()
                ->limit(20)
                ->get(),
            'incomes' => Income::with(['source', 'currency'])
                ->where('user_id', $this->user->id)
                ->where('date', '>=', now()->subDays($days))
                ->latest()
                ->limit(20)
                ->get(),
            'debts' => Debt::with('payments')
                ->where('user_id', $this->user->id)
                ->latest()
                ->limit(10)
                ->get(),
            default => collect(),
        };

        if ($data->isEmpty()) {
            return "No {$type} found matching the criteria.";
        }

        return json_encode($data->toArray(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['purchases', 'incomes', 'debts'])
                ->description('Type of financial data to query')
                ->default('purchases'),
            'days' => $schema->integer()
                ->description('Number of days to look back (default: 30)')
                ->default(30),
        ];
    }
}
