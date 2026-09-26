<?php

use App\Models\Currency;
use App\Models\Income;
use App\Models\IncomeSource;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('finance statistics groups income and expenses by month', function () {
    $user = User::factory()->create();
    $currency = Currency::factory()->create();
    $source = IncomeSource::create([
        'user_id' => $user->id,
        'name' => 'Salary',
        'default_currency_id' => $currency->id,
    ]);

    Income::create([
        'user_id' => $user->id,
        'income_source_id' => $source->id,
        'currency_id' => $currency->id,
        'amount' => 100,
        'received_date' => '2026-03-10',
    ]);

    Purchase::create([
        'user_id' => $user->id,
        'currency_id' => $currency->id,
        'amount' => 40,
        'purchase_date' => '2026-03-15',
        'description' => 'Test purchase',
    ]);

    $this->actingAs($user)
        ->get(route('finance.statistics', [
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'currency_id' => $currency->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('finance/statistics/index')
            ->where('stats.monthly_trend.0.month', '2026-03')
            ->where('stats.monthly_trend.0.income', fn ($income) => (float) $income === 100.0)
            ->where('stats.monthly_trend.0.expenses', fn ($expenses) => (float) $expenses === 40.0)
        );
});
