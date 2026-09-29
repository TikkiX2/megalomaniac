<?php

namespace App\Ai\Tools;

use App\Models\Debt;
use App\Models\Income;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Finance\FinanceService;
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

class FinanceActionTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        protected User $user,
        protected FinanceService $finance,
    ) {}

    public function description(): Stringable|string
    {
        return 'Create, update or delete the user\'s financial records: purchases, incomes, debts (including debt payments) and withdrawals. Use this when the user asks to record, edit or remove money movements.';
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Va a '.($this->actionLabels()[$request['action'] ?? ''] ?? 'modificar tus finanzas').'.');
    }

    /**
     * @return array<string, string>
     */
    protected function actionLabels(): array
    {
        return [
            'add_purchase' => 'añadir una compra',
            'update_purchase' => 'actualizar una compra',
            'delete_purchase' => 'eliminar una compra',
            'add_income' => 'añadir un ingreso',
            'update_income' => 'actualizar un ingreso',
            'delete_income' => 'eliminar un ingreso',
            'add_debt' => 'añadir una deuda',
            'update_debt' => 'actualizar una deuda',
            'delete_debt' => 'eliminar una deuda',
            'add_debt_payment' => 'registrar un pago de deuda',
            'add_withdrawal' => 'añadir un retiro',
            'update_withdrawal' => 'actualizar un retiro',
            'delete_withdrawal' => 'eliminar un retiro',
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return match ($request['action'] ?? '') {
                'add_purchase' => $this->addPurchase($request),
                'update_purchase' => $this->updatePurchase($request),
                'delete_purchase' => $this->deletePurchase($request),
                'add_income' => $this->addIncome($request),
                'update_income' => $this->updateIncome($request),
                'delete_income' => $this->deleteIncome($request),
                'add_debt' => $this->addDebt($request),
                'update_debt' => $this->updateDebt($request),
                'delete_debt' => $this->deleteDebt($request),
                'add_debt_payment' => $this->addDebtPayment($request),
                'add_withdrawal' => $this->addWithdrawal($request),
                'update_withdrawal' => $this->updateWithdrawal($request),
                'delete_withdrawal' => $this->deleteWithdrawal($request),
                default => $this->error('Invalid action. Use: add/update/delete_purchase, add/update/delete_income, add/update/delete_debt, add_debt_payment, add/update/delete_withdrawal'),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return $this->error($exception->getMessage());
        }
    }

    private function addPurchase(Request $request): string
    {
        $purchase = $this->finance->createPurchase($this->user, [
            'currency_id' => $request['currency_id'] ?? null,
            'category_id' => $request['category_id'] ?? null,
            'amount' => $request['amount'] ?? null,
            'purchase_date' => $request['purchase_date'] ?? $request['date'] ?? now()->toDateString(),
            'description' => $request['description'] ?? $request['name'] ?? 'Purchase',
            'notes' => $request['notes'] ?? null,
        ]);

        return $this->success('Purchase added', ['purchase' => $purchase->load(['currency', 'category'])->toArray()]);
    }

    private function updatePurchase(Request $request): string
    {
        $purchase = Purchase::where('user_id', $this->user->id)->find($request['purchase_id'] ?? 0);

        if (! $purchase) {
            return $this->error('Purchase not found');
        }

        $purchase = $this->finance->updatePurchase($this->user, $purchase, $this->filtered([
            'currency_id' => $request['currency_id'] ?? null,
            'category_id' => $request['category_id'] ?? null,
            'amount' => $request['amount'] ?? null,
            'purchase_date' => $request['purchase_date'] ?? $request['date'] ?? null,
            'description' => $request['description'] ?? null,
            'notes' => $request['notes'] ?? null,
        ]));

        return $this->success('Purchase updated', ['purchase' => $purchase->toArray()]);
    }

    private function deletePurchase(Request $request): string
    {
        $purchase = Purchase::where('user_id', $this->user->id)->find($request['purchase_id'] ?? 0);

        if (! $purchase) {
            return $this->error('Purchase not found');
        }

        $this->finance->deletePurchase($this->user, $purchase);

        return $this->success('Purchase deleted', ['purchase' => ['id' => $purchase->id]]);
    }

    private function addIncome(Request $request): string
    {
        $income = $this->finance->createIncome($this->user, [
            'income_source_id' => $request['income_source_id'] ?? $request['source_id'] ?? null,
            'currency_id' => $request['currency_id'] ?? null,
            'amount' => $request['amount'] ?? null,
            'received_date' => $request['received_date'] ?? $request['date'] ?? now()->toDateString(),
            'description' => $request['description'] ?? $request['name'] ?? null,
            'is_recurring' => (bool) ($request['is_recurring'] ?? false),
            'recurrence_day' => $request['recurrence_day'] ?? null,
        ]);

        return $this->success('Income added', ['income' => $income->load(['currency', 'incomeSource'])->toArray()]);
    }

    private function updateIncome(Request $request): string
    {
        $income = Income::where('user_id', $this->user->id)->find($request['income_id'] ?? 0);

        if (! $income) {
            return $this->error('Income not found');
        }

        $income = $this->finance->updateIncome($this->user, $income, $this->filtered([
            'income_source_id' => $request['income_source_id'] ?? $request['source_id'] ?? null,
            'currency_id' => $request['currency_id'] ?? null,
            'amount' => $request['amount'] ?? null,
            'received_date' => $request['received_date'] ?? $request['date'] ?? null,
            'description' => $request['description'] ?? null,
        ]));

        return $this->success('Income updated', ['income' => $income->toArray()]);
    }

    private function deleteIncome(Request $request): string
    {
        $income = Income::where('user_id', $this->user->id)->find($request['income_id'] ?? 0);

        if (! $income) {
            return $this->error('Income not found');
        }

        $this->finance->deleteIncome($this->user, $income);

        return $this->success('Income deleted', ['income' => ['id' => $income->id]]);
    }

    private function addDebt(Request $request): string
    {
        $debt = $this->finance->createDebt($this->user, [
            'currency_id' => $request['currency_id'] ?? null,
            'credit_card_id' => $request['credit_card_id'] ?? null,
            'purchase_id' => $request['purchase_id'] ?? null,
            'original_amount' => $request['amount'] ?? $request['original_amount'] ?? null,
            'due_date' => $request['due_date'] ?? null,
            'notes' => $request['description'] ?? $request['notes'] ?? null,
        ]);

        return $this->success('Debt added', ['debt' => $debt->load('currency')->toArray()]);
    }

    private function updateDebt(Request $request): string
    {
        $debt = Debt::where('user_id', $this->user->id)->find($request['debt_id'] ?? 0);

        if (! $debt) {
            return $this->error('Debt not found');
        }

        $debt = $this->finance->updateDebt($this->user, $debt, $this->filtered([
            'currency_id' => $request['currency_id'] ?? null,
            'credit_card_id' => $request['credit_card_id'] ?? null,
            'original_amount' => $request['amount'] ?? $request['original_amount'] ?? null,
            'due_date' => $request['due_date'] ?? null,
            'notes' => $request['description'] ?? $request['notes'] ?? null,
        ]));

        return $this->success('Debt updated', ['debt' => $debt->toArray()]);
    }

    private function deleteDebt(Request $request): string
    {
        $debt = Debt::where('user_id', $this->user->id)->find($request['debt_id'] ?? 0);

        if (! $debt) {
            return $this->error('Debt not found');
        }

        $this->finance->deleteDebt($this->user, $debt);

        return $this->success('Debt deleted', ['debt' => ['id' => $debt->id]]);
    }

    private function addDebtPayment(Request $request): string
    {
        $debt = Debt::where('user_id', $this->user->id)->find($request['debt_id'] ?? 0);

        if (! $debt) {
            return $this->error('Debt not found');
        }

        $payment = $this->finance->addDebtPayment(
            $this->user,
            $debt,
            (float) ($request['amount'] ?? 0),
            (string) ($request['payment_date'] ?? $request['date'] ?? now()->toDateString()),
            $request['notes'] ?? null,
        );

        return $this->success('Debt payment recorded', [
            'payment' => $payment->toArray(),
            'debt' => $debt->fresh()->only(['id', 'remaining_amount', 'status']),
        ]);
    }

    private function addWithdrawal(Request $request): string
    {
        $withdrawal = $this->finance->createWithdrawal($this->user, [
            'currency_id' => $request['currency_id'] ?? null,
            'category_id' => $request['category_id'] ?? null,
            'amount' => $request['amount'] ?? null,
            'withdrawal_date' => $request['withdrawal_date'] ?? $request['date'] ?? now()->toDateString(),
            'description' => $request['description'] ?? 'Withdrawal',
            'notes' => $request['notes'] ?? null,
        ]);

        return $this->success('Withdrawal added', ['withdrawal' => $withdrawal->toArray()]);
    }

    private function updateWithdrawal(Request $request): string
    {
        $withdrawal = Withdrawal::where('user_id', $this->user->id)->find($request['withdrawal_id'] ?? 0);

        if (! $withdrawal) {
            return $this->error('Withdrawal not found');
        }

        $withdrawal = $this->finance->updateWithdrawal($this->user, $withdrawal, $this->filtered([
            'currency_id' => $request['currency_id'] ?? null,
            'category_id' => $request['category_id'] ?? null,
            'amount' => $request['amount'] ?? null,
            'withdrawal_date' => $request['withdrawal_date'] ?? $request['date'] ?? null,
            'description' => $request['description'] ?? null,
            'notes' => $request['notes'] ?? null,
        ]));

        return $this->success('Withdrawal updated', ['withdrawal' => $withdrawal->toArray()]);
    }

    private function deleteWithdrawal(Request $request): string
    {
        $withdrawal = Withdrawal::where('user_id', $this->user->id)->find($request['withdrawal_id'] ?? 0);

        if (! $withdrawal) {
            return $this->error('Withdrawal not found');
        }

        $this->finance->deleteWithdrawal($this->user, $withdrawal);

        return $this->success('Withdrawal deleted', ['withdrawal' => ['id' => $withdrawal->id]]);
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
                ->enum([
                    'add_purchase', 'update_purchase', 'delete_purchase',
                    'add_income', 'update_income', 'delete_income',
                    'add_debt', 'update_debt', 'delete_debt', 'add_debt_payment',
                    'add_withdrawal', 'update_withdrawal', 'delete_withdrawal',
                ])
                ->description('Action to perform')
                ->required(),
            'purchase_id' => $schema->integer()->description('Purchase ID (update_purchase, delete_purchase, add_debt)'),
            'income_id' => $schema->integer()->description('Income ID (update_income, delete_income)'),
            'debt_id' => $schema->integer()->description('Debt ID (update_debt, delete_debt, add_debt_payment)'),
            'withdrawal_id' => $schema->integer()->description('Withdrawal ID (update_withdrawal, delete_withdrawal)'),
            'amount' => $schema->number()->description('Amount (add/update purchases, incomes, debts, payments, withdrawals)'),
            'currency_id' => $schema->integer()->description('Currency ID (required for purchases, incomes, debts and withdrawals)'),
            'category_id' => $schema->integer()->description('Category ID (purchases and withdrawals)'),
            'income_source_id' => $schema->integer()->description('Income source ID (required for add_income)'),
            'source_id' => $schema->integer()->description('Alias for income_source_id'),
            'credit_card_id' => $schema->integer()->description('Credit card ID (add_debt, update_debt; interest and tax are computed from the card)'),
            'date' => $schema->string()->description('Date YYYY-MM-DD (alias for the entity date)'),
            'purchase_date' => $schema->string()->description('Purchase date YYYY-MM-DD'),
            'received_date' => $schema->string()->description('Income date YYYY-MM-DD'),
            'withdrawal_date' => $schema->string()->description('Withdrawal date YYYY-MM-DD'),
            'payment_date' => $schema->string()->description('Debt payment date YYYY-MM-DD'),
            'due_date' => $schema->string()->description('Debt due date YYYY-MM-DD'),
            'description' => $schema->string()->description('Description'),
            'notes' => $schema->string()->description('Notes'),
            'is_recurring' => $schema->boolean()->description('Recurring income flag (add_income)'),
            'recurrence_day' => $schema->integer()->description('Recurrence day 1-31 (add_income)'),
        ];
    }
}
