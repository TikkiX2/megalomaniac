<?php

namespace App\Services\Finance;

use App\Models\CreditCard;
use App\Models\Currency;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Income;
use App\Models\IncomeSource;
use App\Models\Purchase;
use App\Models\PurchaseCategory;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawalCategory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class FinanceService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createPurchase(User $user, array $data): Purchase
    {
        $this->requireFields($data, ['currency_id', 'amount', 'purchase_date', 'description']);

        $this->assertCurrency($data['currency_id']);
        $this->assertOwned($user, PurchaseCategory::class, $data['category_id'] ?? null);

        return Purchase::create([...$data, 'user_id' => $user->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePurchase(User $user, Purchase $purchase, array $data): Purchase
    {
        $this->assertOwner($user, $purchase);

        if (array_key_exists('currency_id', $data)) {
            $this->assertCurrency($data['currency_id']);
        }

        if (array_key_exists('category_id', $data)) {
            $this->assertOwned($user, PurchaseCategory::class, $data['category_id']);
        }

        $purchase->update($data);

        return $purchase->fresh();
    }

    public function deletePurchase(User $user, Purchase $purchase): void
    {
        $this->assertOwner($user, $purchase);

        $purchase->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createIncome(User $user, array $data): Income
    {
        $this->requireFields($data, ['currency_id', 'income_source_id', 'amount', 'received_date']);

        $this->assertCurrency($data['currency_id']);
        $this->assertOwned($user, IncomeSource::class, $data['income_source_id']);

        return Income::create([...$data, 'user_id' => $user->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateIncome(User $user, Income $income, array $data): Income
    {
        $this->assertOwner($user, $income);

        if (array_key_exists('currency_id', $data)) {
            $this->assertCurrency($data['currency_id']);
        }

        if (array_key_exists('income_source_id', $data)) {
            $this->assertOwned($user, IncomeSource::class, $data['income_source_id']);
        }

        $income->update($data);

        return $income->fresh();
    }

    public function deleteIncome(User $user, Income $income): void
    {
        $this->assertOwner($user, $income);

        $income->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDebt(User $user, array $data): Debt
    {
        $this->requireFields($data, ['currency_id', 'original_amount']);

        $this->assertCurrency($data['currency_id']);
        $this->assertOwned($user, Purchase::class, $data['purchase_id'] ?? null);

        $card = $this->resolveCard($user, $data['credit_card_id'] ?? null);

        $originalAmount = (float) $data['original_amount'];
        $interest = $card?->calculateInterest($originalAmount) ?? 0;
        $tax = $card?->calculateTax($originalAmount) ?? 0;
        $total = $originalAmount + $interest + $tax;

        return Debt::create([
            ...$data,
            'user_id' => $user->id,
            'credit_card_id' => $card?->id,
            'interest_amount' => $interest,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'remaining_amount' => $total,
            'status' => 'pending',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDebt(User $user, Debt $debt, array $data): Debt
    {
        $this->assertOwner($user, $debt);

        if (array_key_exists('currency_id', $data)) {
            $this->assertCurrency($data['currency_id']);
        }

        if (array_key_exists('purchase_id', $data)) {
            $this->assertOwned($user, Purchase::class, $data['purchase_id']);
        }

        if (array_key_exists('original_amount', $data) || array_key_exists('credit_card_id', $data)) {
            $originalAmount = (float) ($data['original_amount'] ?? $debt->original_amount);
            $card = array_key_exists('credit_card_id', $data)
                ? $this->resolveCard($user, $data['credit_card_id'])
                : $debt->creditCard;

            $interest = $card?->calculateInterest($originalAmount) ?? 0;
            $tax = $card?->calculateTax($originalAmount) ?? 0;
            $total = $originalAmount + $interest + $tax;
            $paid = (float) $debt->total_amount - (float) $debt->remaining_amount;

            $data['credit_card_id'] = $card?->id;
            $data['interest_amount'] = $interest;
            $data['tax_amount'] = $tax;
            $data['total_amount'] = $total;
            $data['remaining_amount'] = max(0, $total - $paid);
        }

        $debt->update($data);

        return $debt->fresh();
    }

    public function deleteDebt(User $user, Debt $debt): void
    {
        $this->assertOwner($user, $debt);

        $debt->delete();
    }

    public function addDebtPayment(User $user, Debt $debt, float $amount, string $date, ?string $notes = null): DebtPayment
    {
        $this->assertOwner($user, $debt);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if ($amount > (float) $debt->remaining_amount) {
            throw new InvalidArgumentException('Payment amount exceeds the remaining debt.');
        }

        return $debt->addPayment($amount, Carbon::parse($date), $notes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createWithdrawal(User $user, array $data): Withdrawal
    {
        $this->requireFields($data, ['currency_id', 'amount', 'withdrawal_date', 'description']);

        $this->assertCurrency($data['currency_id']);
        $this->assertOwned($user, WithdrawalCategory::class, $data['category_id'] ?? null);

        return Withdrawal::create([...$data, 'user_id' => $user->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateWithdrawal(User $user, Withdrawal $withdrawal, array $data): Withdrawal
    {
        $this->assertOwner($user, $withdrawal);

        if (array_key_exists('currency_id', $data)) {
            $this->assertCurrency($data['currency_id']);
        }

        if (array_key_exists('category_id', $data)) {
            $this->assertOwned($user, WithdrawalCategory::class, $data['category_id']);
        }

        $withdrawal->update($data);

        return $withdrawal->fresh();
    }

    public function deleteWithdrawal(User $user, Withdrawal $withdrawal): void
    {
        $this->assertOwner($user, $withdrawal);

        $withdrawal->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $fields
     */
    private function requireFields(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("The {$field} field is required.");
            }
        }
    }

    private function assertCurrency(mixed $currencyId): void
    {
        if (! $currencyId || ! Currency::whereKey($currencyId)->exists()) {
            throw new InvalidArgumentException('Currency not found.');
        }
    }

    private function resolveCard(User $user, mixed $cardId): ?CreditCard
    {
        if (! $cardId) {
            return null;
        }

        $card = CreditCard::where('user_id', $user->id)->find($cardId);

        if (! $card) {
            throw new ModelNotFoundException('Credit card not found.');
        }

        return $card;
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function assertOwned(User $user, string $class, mixed $id): void
    {
        if (! $id) {
            return;
        }

        $exists = $class::where('user_id', $user->id)->whereKey($id)->exists();

        if (! $exists) {
            throw new ModelNotFoundException('Related record not found.');
        }
    }

    private function assertOwner(User $user, Model $model): void
    {
        if ($model->user_id !== $user->id) {
            throw new AuthorizationException('You do not own this record.');
        }
    }
}
