<?php

use App\Ai\Tools\FinanceQueryTool;
use App\Models\Currency;
use App\Models\Income;
use App\Models\IncomeSource;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function financeCurrency(): Currency
{
    return Currency::firstOrCreate(
        ['code' => 'USD'],
        ['name' => 'US Dollar', 'symbol' => '$'],
    );
}

it('filters purchases and incomes by their real date columns', function () {
    $user = User::factory()->create();
    $currency = financeCurrency();

    Purchase::create([
        'user_id' => $user->id,
        'currency_id' => $currency->id,
        'amount' => 10,
        'description' => 'Reciente',
        'purchase_date' => now()->subDays(3)->toDateString(),
    ]);

    Purchase::create([
        'user_id' => $user->id,
        'currency_id' => $currency->id,
        'amount' => 99,
        'description' => 'Vieja',
        'purchase_date' => now()->subDays(40)->toDateString(),
    ]);

    $source = IncomeSource::create([
        'user_id' => $user->id,
        'name' => 'Sueldo',
        'default_currency_id' => $currency->id,
    ]);

    Income::create([
        'user_id' => $user->id,
        'income_source_id' => $source->id,
        'currency_id' => $currency->id,
        'amount' => 500,
        'received_date' => now()->subDays(3)->toDateString(),
    ]);

    Income::create([
        'user_id' => $user->id,
        'income_source_id' => $source->id,
        'currency_id' => $currency->id,
        'amount' => 700,
        'received_date' => now()->subDays(40)->toDateString(),
    ]);

    $tool = new FinanceQueryTool($user);

    $purchases = json_decode($tool->handle(new Request(['type' => 'purchases', 'days' => 7])), true);
    $incomes = json_decode($tool->handle(new Request(['type' => 'incomes', 'days' => 7])), true);

    expect($purchases)->toHaveCount(1)
        ->and($purchases[0]['description'])->toBe('Reciente')
        ->and($incomes)->toHaveCount(1)
        ->and($incomes[0]['amount'])->toBe('500.00')
        ->and($incomes[0]['income_source']['name'])->toBe('Sueldo');
});
