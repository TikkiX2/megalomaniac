<?php

use App\Mcp\Servers\MegalomaniacServer;
use App\Mcp\Tools\FinanceReadTool;
use App\Mcp\Tools\FinanceWriteTool;
use App\Models\Currency;
use App\Models\Debt;
use App\Models\IncomeSource;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates, updates and deletes purchases through mcp', function () {
    $user = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);

    MegalomaniacServer::actingAs($user)
        ->tool(FinanceWriteTool::class, [
            'action' => 'create_purchase',
            'amount' => 15,
            'currency_id' => $currency->id,
            'description' => 'Café',
            'date' => now()->toDateString(),
        ])
        ->assertOk();

    $purchase = Purchase::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(FinanceWriteTool::class, [
            'action' => 'update_purchase',
            'purchase_id' => $purchase->id,
            'amount' => 20,
        ])
        ->assertOk();

    expect($purchase->fresh()->amount)->toBe('20.00');

    MegalomaniacServer::actingAs($user)
        ->tool(FinanceWriteTool::class, ['action' => 'delete_purchase', 'purchase_id' => $purchase->id])
        ->assertOk();

    expect(Purchase::find($purchase->id))->toBeNull();
});

it('rejects incomes without an owned income source', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $foreign = IncomeSource::create([
        'user_id' => $other->id,
        'name' => 'Ajena',
        'default_currency_id' => $currency->id,
    ]);

    MegalomaniacServer::actingAs($user)
        ->tool(FinanceWriteTool::class, [
            'action' => 'create_income',
            'amount' => 100,
            'currency_id' => $currency->id,
            'income_source_id' => $foreign->id,
        ])
        ->assertHasErrors(['Related record not found']);
});

it('records debt payments and keeps overdue debts readable', function () {
    $user = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);

    $debt = Debt::create([
        'user_id' => $user->id,
        'currency_id' => $currency->id,
        'original_amount' => 100,
        'remaining_amount' => 100,
        'total_amount' => 100,
        'status' => 'pending',
        'due_date' => now()->subMonths(2)->toDateString(),
    ]);

    MegalomaniacServer::actingAs($user)
        ->tool(FinanceWriteTool::class, [
            'action' => 'add_debt_payment',
            'debt_id' => $debt->id,
            'amount' => 30,
            'payment_date' => now()->toDateString(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('debt.remaining_amount', '70.00')
            ->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(FinanceReadTool::class, ['type' => 'debts', 'days' => 7])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('count', 1)
            ->etc());
});
