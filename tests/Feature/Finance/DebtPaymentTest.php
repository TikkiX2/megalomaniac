<?php

use App\Models\Currency;
use App\Models\Debt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('paying a debt in full marks it as paid', function () {
    $user = User::factory()->create();

    $debt = Debt::create([
        'user_id' => $user->id,
        'currency_id' => Currency::factory()->create()->id,
        'original_amount' => 100,
        'total_amount' => 100,
        'remaining_amount' => 100,
        'due_date' => '2026-10-30',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->post(route('finance.debts.payments.store', $debt), [
            'amount' => '100.00',
            'payment_date' => '2026-10-13',
        ])
        ->assertRedirect(route('finance.debts.show', $debt));

    expect($debt->fresh()->status)->toBe('paid')
        ->and($debt->fresh()->remaining_amount)->toBe('0.00')
        ->and($debt->payments()->count())->toBe(1);
});
