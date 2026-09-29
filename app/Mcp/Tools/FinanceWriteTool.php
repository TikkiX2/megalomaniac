<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Debt;
use App\Models\Income;
use App\Models\Purchase;
use App\Services\Finance\FinanceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class FinanceWriteTool extends Tool
{
    protected string $name = 'finance-write';

    protected string $description = 'Create, update or delete purchases, incomes, debts (including payments) and withdrawals for the authenticated user.';

    public function __construct(protected FinanceService $finance) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description('Action: create/update/delete_purchase, create/update/delete_income, create/update/delete_debt, add_debt_payment, create_withdrawal')
                ->enum([
                    'create_purchase', 'update_purchase', 'delete_purchase',
                    'create_income', 'update_income', 'delete_income',
                    'create_debt', 'update_debt', 'delete_debt', 'add_debt_payment',
                    'create_withdrawal',
                ])
                ->required(),
            'purchase_id' => $schema->integer()->description('Purchase ID (update_purchase, delete_purchase, create_debt)'),
            'income_id' => $schema->integer()->description('Income ID (update_income, delete_income)'),
            'debt_id' => $schema->integer()->description('Debt ID (update_debt, delete_debt, add_debt_payment)'),
            'amount' => $schema->number()->description('Amount (required for create actions and debt payments)')->min(0.01),
            'currency_id' => $schema->integer()->description('Currency ID (required for purchases, incomes, debts and withdrawals)'),
            'category_id' => $schema->integer()->description('Purchase category ID (create/update_purchase)'),
            'income_source_id' => $schema->integer()->description('Income source ID (required for create_income)'),
            'credit_card_id' => $schema->integer()->description('Credit card ID (create/update_debt; interest and tax are computed from the card)'),
            'description' => $schema->string()->description('Description (purchases, incomes and withdrawals)'),
            'date' => $schema->string()->description('Date in YYYY-MM-DD format (falls back to each entity date)'),
            'due_date' => $schema->string()->description('Debt due date in YYYY-MM-DD format'),
            'payment_date' => $schema->string()->description('Debt payment date in YYYY-MM-DD format'),
            'notes' => $schema->string()->description('Notes'),
            'is_recurring' => $schema->boolean()->description('Recurring income flag (create_income)'),
            'recurrence_day' => $schema->integer()->description('Recurrence day 1-31 (create_income)'),
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
                'create_purchase' => $this->createPurchase($request, $user),
                'update_purchase' => $this->updatePurchase($request, $user),
                'delete_purchase' => $this->deletePurchase($request, $user),
                'create_income' => $this->createIncome($request, $user),
                'update_income' => $this->updateIncome($request, $user),
                'delete_income' => $this->deleteIncome($request, $user),
                'create_debt' => $this->createDebt($request, $user),
                'update_debt' => $this->updateDebt($request, $user),
                'delete_debt' => $this->deleteDebt($request, $user),
                'add_debt_payment' => $this->addDebtPayment($request, $user),
                'create_withdrawal' => $this->createWithdrawal($request, $user),
                default => Response::error("Invalid action: {$action}"),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }
    }

    private function createPurchase(Request $request, $user): Response|ResponseFactory
    {
        $purchase = $this->finance->createPurchase($user, [
            'currency_id' => $request->get('currency_id'),
            'category_id' => $request->get('category_id'),
            'amount' => $request->get('amount'),
            'purchase_date' => $request->get('date', now()->toDateString()),
            'description' => $request->get('description', 'Purchase'),
            'notes' => $request->get('notes'),
        ]);

        return Response::structured([
            'purchase' => $purchase->fresh()->load(['currency', 'category']),
            'message' => 'Purchase created successfully.',
        ]);
    }

    private function updatePurchase(Request $request, $user): Response|ResponseFactory
    {
        $purchase = Purchase::where('user_id', $user->id)->find($request->get('purchase_id', 0));

        if (! $purchase) {
            return Response::error('Purchase not found or unauthorized.');
        }

        $purchase = $this->finance->updatePurchase($user, $purchase, $this->filtered([
            'currency_id' => $request->get('currency_id'),
            'category_id' => $request->get('category_id'),
            'amount' => $request->get('amount'),
            'purchase_date' => $request->get('date'),
            'description' => $request->get('description'),
            'notes' => $request->get('notes'),
        ]));

        return Response::structured([
            'purchase' => $purchase->load(['currency', 'category']),
            'message' => 'Purchase updated successfully.',
        ]);
    }

    private function deletePurchase(Request $request, $user): Response|ResponseFactory
    {
        $purchase = Purchase::where('user_id', $user->id)->find($request->get('purchase_id', 0));

        if (! $purchase) {
            return Response::error('Purchase not found or unauthorized.');
        }

        $this->finance->deletePurchase($user, $purchase);

        return Response::structured(['message' => 'Purchase deleted successfully.']);
    }

    private function createIncome(Request $request, $user): Response|ResponseFactory
    {
        $income = $this->finance->createIncome($user, [
            'currency_id' => $request->get('currency_id'),
            'income_source_id' => $request->get('income_source_id'),
            'amount' => $request->get('amount'),
            'received_date' => $request->get('date', now()->toDateString()),
            'description' => $request->get('description'),
            'is_recurring' => (bool) $request->get('is_recurring', false),
            'recurrence_day' => $request->get('recurrence_day'),
        ]);

        return Response::structured([
            'income' => $income->fresh()->load(['currency', 'incomeSource']),
            'message' => 'Income recorded successfully.',
        ]);
    }

    private function updateIncome(Request $request, $user): Response|ResponseFactory
    {
        $income = Income::where('user_id', $user->id)->find($request->get('income_id', 0));

        if (! $income) {
            return Response::error('Income not found or unauthorized.');
        }

        $income = $this->finance->updateIncome($user, $income, $this->filtered([
            'currency_id' => $request->get('currency_id'),
            'income_source_id' => $request->get('income_source_id'),
            'amount' => $request->get('amount'),
            'received_date' => $request->get('date'),
            'description' => $request->get('description'),
        ]));

        return Response::structured([
            'income' => $income->load(['currency', 'incomeSource']),
            'message' => 'Income updated successfully.',
        ]);
    }

    private function deleteIncome(Request $request, $user): Response|ResponseFactory
    {
        $income = Income::where('user_id', $user->id)->find($request->get('income_id', 0));

        if (! $income) {
            return Response::error('Income not found or unauthorized.');
        }

        $this->finance->deleteIncome($user, $income);

        return Response::structured(['message' => 'Income deleted successfully.']);
    }

    private function createDebt(Request $request, $user): Response|ResponseFactory
    {
        $debt = $this->finance->createDebt($user, [
            'currency_id' => $request->get('currency_id'),
            'credit_card_id' => $request->get('credit_card_id'),
            'purchase_id' => $request->get('purchase_id'),
            'original_amount' => $request->get('amount'),
            'due_date' => $request->get('due_date'),
            'notes' => $request->get('notes'),
        ]);

        return Response::structured([
            'debt' => $debt->fresh()->load('currency'),
            'message' => 'Debt created successfully.',
        ]);
    }

    private function updateDebt(Request $request, $user): Response|ResponseFactory
    {
        $debt = Debt::where('user_id', $user->id)->find($request->get('debt_id', 0));

        if (! $debt) {
            return Response::error('Debt not found or unauthorized.');
        }

        $debt = $this->finance->updateDebt($user, $debt, $this->filtered([
            'currency_id' => $request->get('currency_id'),
            'credit_card_id' => $request->get('credit_card_id'),
            'original_amount' => $request->get('amount'),
            'due_date' => $request->get('due_date'),
            'notes' => $request->get('notes'),
        ]));

        return Response::structured([
            'debt' => $debt->load('currency'),
            'message' => 'Debt updated successfully.',
        ]);
    }

    private function deleteDebt(Request $request, $user): Response|ResponseFactory
    {
        $debt = Debt::where('user_id', $user->id)->find($request->get('debt_id', 0));

        if (! $debt) {
            return Response::error('Debt not found or unauthorized.');
        }

        $this->finance->deleteDebt($user, $debt);

        return Response::structured(['message' => 'Debt deleted successfully.']);
    }

    private function addDebtPayment(Request $request, $user): Response|ResponseFactory
    {
        $debt = Debt::where('user_id', $user->id)->find($request->get('debt_id', 0));

        if (! $debt) {
            return Response::error('Debt not found or unauthorized.');
        }

        $payment = $this->finance->addDebtPayment(
            $user,
            $debt,
            (float) $request->get('amount', 0),
            (string) $request->get('payment_date', now()->toDateString()),
            $request->get('notes'),
        );

        return Response::structured([
            'payment' => $payment,
            'debt' => $debt->fresh()->only(['id', 'remaining_amount', 'status']),
            'message' => 'Debt payment recorded successfully.',
        ]);
    }

    private function createWithdrawal(Request $request, $user): Response|ResponseFactory
    {
        $withdrawal = $this->finance->createWithdrawal($user, [
            'currency_id' => $request->get('currency_id'),
            'amount' => $request->get('amount'),
            'withdrawal_date' => $request->get('date', now()->toDateString()),
            'description' => $request->get('description', 'Withdrawal'),
            'notes' => $request->get('notes'),
        ]);

        return Response::structured([
            'withdrawal' => $withdrawal->fresh()->load('currency'),
            'message' => 'Withdrawal created successfully.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filtered(array $data): array
    {
        return array_filter($data, fn (mixed $value): bool => $value !== null);
    }
}
