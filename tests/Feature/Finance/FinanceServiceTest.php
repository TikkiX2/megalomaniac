<?php

use App\Models\CreditCard;
use App\Models\Currency;
use App\Models\Income;
use App\Models\IncomeSource;
use App\Models\Purchase;
use App\Models\PurchaseCategory;
use App\Models\User;
use App\Services\Finance\FinanceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function financeFixtures(User $user): array
{
    $currency = Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$']);
    $source = IncomeSource::create([
        'user_id' => $user->id,
        'name' => 'Sueldo',
        'default_currency_id' => $currency->id,
    ]);
    $category = PurchaseCategory::create(['user_id' => $user->id, 'name' => 'Comida']);

    return [$currency, $source, $category];
}

it('creates a purchase with an owned category', function () {
    $user = User::factory()->create();
    [$currency, , $category] = financeFixtures($user);

    $purchase = app(FinanceService::class)->createPurchase($user, [
        'currency_id' => $currency->id,
        'category_id' => $category->id,
        'amount' => 25.5,
        'purchase_date' => now()->toDateString(),
        'description' => 'Super',
    ]);

    expect($purchase->user_id)->toBe($user->id)
        ->and($purchase->amount)->toBe('25.50')
        ->and($purchase->category_id)->toBe($category->id);
});

it('rejects a purchase with a foreign category or missing currency', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    [$currency] = financeFixtures($user);
    $foreign = PurchaseCategory::create(['user_id' => $other->id, 'name' => 'Ajena']);

    expect(fn () => app(FinanceService::class)->createPurchase($user, [
        'currency_id' => $currency->id,
        'category_id' => $foreign->id,
        'amount' => 10,
        'purchase_date' => now()->toDateString(),
        'description' => 'X',
    ]))->toThrow(ModelNotFoundException::class);

    expect(fn () => app(FinanceService::class)->createPurchase($user, [
        'amount' => 10,
        'purchase_date' => now()->toDateString(),
        'description' => 'X',
    ]))->toThrow(InvalidArgumentException::class);
});

it('updates and deletes purchases with ownership checks', function () {
    $user = User::factory()->create();
    $intruder = User::factory()->create();
    [$currency] = financeFixtures($user);

    $purchase = app(FinanceService::class)->createPurchase($user, [
        'currency_id' => $currency->id,
        'amount' => 10,
        'purchase_date' => now()->toDateString(),
        'description' => 'X',
    ]);

    $updated = app(FinanceService::class)->updatePurchase($user, $purchase, ['amount' => 99]);
    expect($updated->fresh()->amount)->toBe('99.00');

    expect(fn () => app(FinanceService::class)->updatePurchase($intruder, $purchase, ['amount' => 1]))
        ->toThrow(AuthorizationException::class);

    app(FinanceService::class)->deletePurchase($user, $purchase);
    expect(Purchase::find($purchase->id))->toBeNull();
});

it('requires an owned income source for incomes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    [$currency, $source] = financeFixtures($user);
    $foreign = IncomeSource::create([
        'user_id' => $other->id,
        'name' => 'Ajena',
        'default_currency_id' => $currency->id,
    ]);

    $income = app(FinanceService::class)->createIncome($user, [
        'income_source_id' => $source->id,
        'currency_id' => $currency->id,
        'amount' => 500,
        'received_date' => now()->toDateString(),
    ]);

    expect($income->income_source_id)->toBe($source->id);

    expect(fn () => app(FinanceService::class)->createIncome($user, [
        'income_source_id' => $foreign->id,
        'currency_id' => $currency->id,
        'amount' => 500,
        'received_date' => now()->toDateString(),
    ]))->toThrow(ModelNotFoundException::class);

    expect(fn () => app(FinanceService::class)->createIncome($user, [
        'currency_id' => $currency->id,
        'amount' => 500,
        'received_date' => now()->toDateString(),
    ]))->toThrow(InvalidArgumentException::class);

    app(FinanceService::class)->deleteIncome($user, $income);
    expect(Income::find($income->id))->toBeNull();
});

it('creates debts with credit card interest and records payments', function () {
    $user = User::factory()->create();
    [$currency] = financeFixtures($user);

    $card = CreditCard::create([
        'user_id' => $user->id,
        'name' => 'Visa',
        'interest_rate' => 10,
        'tax_percentage' => 5,
        'apply_interest' => true,
        'apply_tax' => true,
    ]);

    $debt = app(FinanceService::class)->createDebt($user, [
        'currency_id' => $currency->id,
        'credit_card_id' => $card->id,
        'original_amount' => 100,
        'due_date' => now()->addMonth()->toDateString(),
    ]);

    expect($debt->interest_amount)->toBe('10.00')
        ->and($debt->tax_amount)->toBe('5.00')
        ->and($debt->total_amount)->toBe('115.00')
        ->and($debt->remaining_amount)->toBe('115.00')
        ->and($debt->status)->toBe('pending');

    app(FinanceService::class)->addDebtPayment($user, $debt, 15, now()->toDateString());

    expect($debt->fresh()->remaining_amount)->toBe('100.00')
        ->and($debt->fresh()->status)->toBe('partial');

    expect(fn () => app(FinanceService::class)->addDebtPayment($user, $debt, 999, now()->toDateString()))
        ->toThrow(InvalidArgumentException::class);

    app(FinanceService::class)->addDebtPayment($user, $debt->fresh(), 100, now()->toDateString());

    expect($debt->fresh()->status)->toBe('paid')
        ->and($debt->fresh()->remaining_amount)->toBe('0.00');

    $intruder = User::factory()->create();
    expect(fn () => app(FinanceService::class)->deleteDebt($intruder, $debt))
        ->toThrow(AuthorizationException::class);
});

it('creates, updates and deletes withdrawals', function () {
    $user = User::factory()->create();
    [$currency] = financeFixtures($user);

    $withdrawal = app(FinanceService::class)->createWithdrawal($user, [
        'currency_id' => $currency->id,
        'amount' => 50,
        'withdrawal_date' => now()->toDateString(),
        'description' => 'Retiro',
    ]);

    expect($withdrawal->user_id)->toBe($user->id);

    $updated = app(FinanceService::class)->updateWithdrawal($user, $withdrawal, ['amount' => 75]);
    expect($updated->fresh()->amount)->toBe('75.00');

    app(FinanceService::class)->deleteWithdrawal($user, $withdrawal);
    expect(DB::table('withdrawals')->where('id', $withdrawal->id)->exists())->toBeFalse();
});
