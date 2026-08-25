<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Models\Client;
use App\Models\Currency;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

test('quotes index is accessible', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('freelance.quotes.index'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('freelance/quotes/Index')
        );
});

test('quote can be created', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['user_id' => $user->id]);
    $currency = Currency::where('code', 'USD')->first() ?? Currency::factory()->create(['code' => 'USD']);

    $quoteData = [
        'client_id' => $client->id,
        'currency_id' => $currency->id,
        'quote_number' => 'QT-2026-001',
        'issue_date' => now()->toDateString(),
        'status' => 'draft',
        'items' => [
            [
                'description' => 'Test Item',
                'hours' => 10,
                'hourly_rate' => 50,
                'subtotal' => 500,
                'order' => 1,
            ],
        ],
        'total_amount' => 500,
    ];

    actingAs($user)
        ->post(route('freelance.quotes.store'), $quoteData)
        ->assertRedirect(); // Redirects to show

    assertDatabaseHas('quotes', [
        'quote_number' => 'QT-2026-001',
        'client_id' => $client->id,
    ]);
});
