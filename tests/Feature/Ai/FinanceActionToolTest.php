<?php

use App\Ai\Tools\FinanceActionTool;
use App\Models\Currency;
use App\Models\Debt;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Finance\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function financeTool(User $user): FinanceActionTool
{
    return new FinanceActionTool($user, app(FinanceService::class));
}

it('adds purchases, incomes and debts through the finance action tool', function () {
    $user = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $source = $user->incomeSources()->create([
        'name' => 'Sueldo',
        'default_currency_id' => $currency->id,
    ]);

    $purchase = json_decode((string) financeTool($user)->handle(new Request([
        'action' => 'add_purchase',
        'amount' => 29.99,
        'currency_id' => $currency->id,
        'description' => 'Super',
        'purchase_date' => now()->toDateString(),
    ])), true);

    expect($purchase['success'])->toBeTrue()
        ->and($purchase['purchase']['amount'])->toBe('29.99');

    $income = json_decode((string) financeTool($user)->handle(new Request([
        'action' => 'add_income',
        'amount' => 500,
        'currency_id' => $currency->id,
        'income_source_id' => $source->id,
        'received_date' => now()->toDateString(),
    ])), true);

    expect($income['success'])->toBeTrue();

    $debt = json_decode((string) financeTool($user)->handle(new Request([
        'action' => 'add_debt',
        'amount' => 100,
        'currency_id' => $currency->id,
        'due_date' => now()->addMonth()->toDateString(),
    ])), true);

    expect($debt['success'])->toBeTrue()
        ->and($debt['debt']['status'])->toBe('pending');
});

it('updates and deletes records with ownership checks', function () {
    $user = User::factory()->create();
    $intruder = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);

    $created = json_decode((string) financeTool($user)->handle(new Request([
        'action' => 'add_purchase',
        'amount' => 10,
        'currency_id' => $currency->id,
        'description' => 'X',
        'purchase_date' => now()->toDateString(),
    ])), true);

    $purchaseId = $created['purchase']['id'];

    $updated = json_decode((string) financeTool($user)->handle(new Request([
        'action' => 'update_purchase',
        'purchase_id' => $purchaseId,
        'amount' => 42,
    ])), true);

    expect($updated['success'])->toBeTrue()
        ->and(Purchase::find($purchaseId)->amount)->toBe('42.00');

    $foreignAttempt = json_decode((string) financeTool($intruder)->handle(new Request([
        'action' => 'delete_purchase',
        'purchase_id' => $purchaseId,
    ])), true);

    expect($foreignAttempt['success'])->toBeFalse()
        ->and(Purchase::find($purchaseId))->not->toBeNull();

    $deleted = json_decode((string) financeTool($user)->handle(new Request([
        'action' => 'delete_purchase',
        'purchase_id' => $purchaseId,
    ])), true);

    expect($deleted['success'])->toBeTrue()
        ->and(Purchase::find($purchaseId))->toBeNull();
});

it('records debt payments and blocks overpayment', function () {
    $user = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);

    $debt = Debt::create([
        'user_id' => $user->id,
        'currency_id' => $currency->id,
        'original_amount' => 100,
        'remaining_amount' => 100,
        'total_amount' => 100,
        'status' => 'pending',
    ]);

    $paid = json_decode((string) financeTool($user)->handle(new Request([
        'action' => 'add_debt_payment',
        'debt_id' => $debt->id,
        'amount' => 40,
        'payment_date' => now()->toDateString(),
    ])), true);

    expect($paid['success'])->toBeTrue()
        ->and($paid['debt']['remaining_amount'])->toBe('60.00')
        ->and($paid['debt']['status'])->toBe('partial');

    $overpaid = json_decode((string) financeTool($user)->handle(new Request([
        'action' => 'add_debt_payment',
        'debt_id' => $debt->id,
        'amount' => 999,
        'payment_date' => now()->toDateString(),
    ])), true);

    expect($overpaid['success'] ?? false)->toBeFalse()
        ->and($debt->fresh()->remaining_amount)->toBe('60.00');
});
