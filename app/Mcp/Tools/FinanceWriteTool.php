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

class FinanceWriteTool extends Tool
{
    protected string $name = 'finance-write';

    protected string $description = 'Create purchases, incomes, and debts for the authenticated user.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform: create_purchase, create_income, create_debt')->enum(['create_purchase', 'create_income', 'create_debt'])->required(),
            'amount' => $schema->number()->description('Amount (required for all actions)')->required(),
            'currency_id' => $schema->integer()->description('Currency ID (required for all actions)')->required(),
            'description' => $schema->string()->description('Description (for create_purchase and create_income)'),
            'date' => $schema->string()->description('Date in YYYY-MM-DD format (for create_purchase and create_income)'),
            'category_id' => $schema->integer()->description('Purchase category ID (for create_purchase)'),
            'income_source_id' => $schema->integer()->description('Income source ID (for create_income)'),
            'purchase_id' => $schema->integer()->description('Purchase ID to associate with debt (for create_debt)'),
            'due_date' => $schema->string()->description('Due date in YYYY-MM-DD format (for create_debt)'),
            'interest_amount' => $schema->number()->description('Interest amount (for create_debt)'),
            'tax_amount' => $schema->number()->description('Tax amount (for create_debt)'),
            'notes' => $schema->string()->description('Notes'),
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
            'create_purchase' => $this->createPurchase($request, $user),
            'create_income' => $this->createIncome($request, $user),
            'create_debt' => $this->createDebt($request, $user),
            default => Response::error("Invalid action: {$action}"),
        };
    }

    private function createPurchase(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'category_id' => ['nullable', 'integer', 'exists:purchase_categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $purchase = Purchase::create([
            'user_id' => $user->id,
            'amount' => $request->get('amount'),
            'currency_id' => $request->get('currency_id'),
            'category_id' => $request->get('category_id'),
            'description' => $request->get('description'),
            'purchase_date' => $request->get('date', now()->toDateString()),
            'notes' => $request->get('notes'),
        ]);

        return Response::structured([
            'purchase' => $purchase->fresh()->load(['currency', 'category']),
            'message' => 'Purchase created successfully.',
        ]);
    }

    private function createIncome(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'income_source_id' => ['nullable', 'integer', 'exists:income_sources,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $income = Income::create([
            'user_id' => $user->id,
            'amount' => $request->get('amount'),
            'currency_id' => $request->get('currency_id'),
            'income_source_id' => $request->get('income_source_id'),
            'description' => $request->get('description'),
            'received_date' => $request->get('date', now()->toDateString()),
        ]);

        return Response::structured([
            'income' => $income->fresh()->load(['currency', 'incomeSource']),
            'message' => 'Income recorded successfully.',
        ]);
    }

    private function createDebt(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'purchase_id' => ['nullable', 'integer', 'exists:purchases,id'],
            'due_date' => ['nullable', 'date'],
            'interest_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $totalAmount = (float) $request->get('amount')
            + (float) $request->get('interest_amount', 0)
            + (float) $request->get('tax_amount', 0);

        $debt = Debt::create([
            'user_id' => $user->id,
            'purchase_id' => $request->get('purchase_id'),
            'currency_id' => $request->get('currency_id'),
            'original_amount' => $request->get('amount'),
            'remaining_amount' => $totalAmount,
            'interest_amount' => $request->get('interest_amount', 0),
            'tax_amount' => $request->get('tax_amount', 0),
            'total_amount' => $totalAmount,
            'due_date' => $request->get('due_date'),
            'status' => 'pending',
            'notes' => $request->get('notes'),
        ]);

        return Response::structured([
            'debt' => $debt->fresh()->load('currency'),
            'message' => 'Debt created successfully.',
        ]);
    }
}
