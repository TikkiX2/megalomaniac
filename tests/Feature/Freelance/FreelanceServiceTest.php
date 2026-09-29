<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Quote;
use App\Models\User;
use App\Services\Freelance\FreelanceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('manages clients with ownership checks', function () {
    $user = User::factory()->create();
    $intruder = User::factory()->create();
    $service = app(FreelanceService::class);

    $client = $service->createClient($user, ['name' => 'Acme', 'email' => 'hi@acme.dev']);

    expect($client->user_id)->toBe($user->id)
        ->and($client->is_active)->toBeTrue();

    $updated = $service->updateClient($user, $client, ['name' => 'Acme Corp']);
    expect($updated->fresh()->name)->toBe('Acme Corp');

    expect(fn () => $service->updateClient($intruder, $client, ['name' => 'hack']))
        ->toThrow(AuthorizationException::class);

    $service->deleteClient($user, $client);
    expect(Client::withTrashed()->find($client->id)->trashed())->toBeTrue();
});

it('avoids duplicating the auto Personal client as a business client', function () {
    $user = User::factory()->create();
    $service = app(FreelanceService::class);

    $client = $service->createClient($user, ['name' => 'Personal']);

    expect($client->name)->toBe('Personal');
});

it('creates quotes with computed totals and items', function () {
    $user = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $client = Client::factory()->create(['user_id' => $user->id]);
    $service = app(FreelanceService::class);

    $quote = $service->createQuote($user, [
        'client_id' => $client->id,
        'title' => 'Landing',
        'issue_date' => now()->toDateString(),
        'currency_id' => $currency->id,
        'items' => [
            ['description' => 'Diseño', 'hours' => 10, 'hourly_rate' => 50, 'subtotal' => 500],
            ['description' => 'Dev', 'hours' => 20, 'hourly_rate' => 60, 'subtotal' => 1200],
        ],
    ]);

    expect($quote->client_id)->toBe($client->id)
        ->and((float) $quote->subtotal)->toBe(1700.0)
        ->and((float) $quote->total)->toBe(1700.0)
        ->and($quote->items()->count())->toBe(2)
        ->and($quote->quote_number)->not->toBeNull();
});

it('rejects quotes with a foreign client', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $foreign = Client::factory()->create(['user_id' => $other->id]);

    expect(fn () => app(FreelanceService::class)->createQuote($user, [
        'client_id' => $foreign->id,
        'issue_date' => now()->toDateString(),
        'currency_id' => $currency->id,
    ]))->toThrow(AuthorizationException::class);
});

it('converts a quote into a freelance project', function () {
    $user = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $client = Client::factory()->create(['user_id' => $user->id]);
    $service = app(FreelanceService::class);

    $quote = $service->createQuote($user, [
        'client_id' => $client->id,
        'title' => 'Landing',
        'issue_date' => now()->toDateString(),
        'currency_id' => $currency->id,
        'items' => [],
    ]);

    $project = $service->convertQuoteToProject($user, $quote);

    expect($project->type)->toBe('freelance')
        ->and($project->client_id)->toBe($client->id)
        ->and($quote->fresh()->project_id)->toBe($project->id)
        ->and($quote->fresh()->status)->toBe('accepted');
});

it('updates and deletes quotes', function () {
    $user = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $client = Client::factory()->create(['user_id' => $user->id]);
    $service = app(FreelanceService::class);

    $quote = $service->createQuote($user, [
        'client_id' => $client->id,
        'issue_date' => now()->toDateString(),
        'currency_id' => $currency->id,
        'items' => [],
    ]);

    $updated = $service->updateQuote($user, $quote, [
        'status' => 'sent',
        'items' => [['description' => 'Extra', 'subtotal' => 100]],
    ]);

    expect($updated->status)->toBe('sent')
        ->and((float) $updated->total)->toBe(100.0)
        ->and(DB::table('quote_items')->where('quote_id', $quote->id)->count())->toBe(1);

    $service->deleteQuote($user, $quote);
    expect(Quote::find($quote->id))->toBeNull();
});
