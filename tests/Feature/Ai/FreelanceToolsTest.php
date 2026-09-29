<?php

use App\Ai\Tools\FreelanceActionTool;
use App\Ai\Tools\FreelanceQueryTool;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use App\Services\Freelance\FreelanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function freelanceTool(User $user): FreelanceActionTool
{
    return new FreelanceActionTool($user, app(FreelanceService::class));
}

it('manages clients and quotes through the chat tool', function () {
    $user = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);

    $clientPayload = json_decode((string) freelanceTool($user)->handle(new Request([
        'action' => 'create_client',
        'name' => 'Acme',
    ])), true);

    expect($clientPayload['success'])->toBeTrue();
    $clientId = $clientPayload['client']['id'];

    $quotePayload = json_decode((string) freelanceTool($user)->handle(new Request([
        'action' => 'create_quote',
        'client_id' => $clientId,
        'title' => 'Landing',
        'issue_date' => now()->toDateString(),
        'currency_id' => $currency->id,
        'items' => [['description' => 'Dev', 'subtotal' => 800]],
    ])), true);

    expect($quotePayload['success'])->toBeTrue()
        ->and((float) $quotePayload['quote']['total'])->toBe(800.0);

    $quoteId = $quotePayload['quote']['id'];

    $converted = json_decode((string) freelanceTool($user)->handle(new Request([
        'action' => 'convert_quote',
        'quote_id' => $quoteId,
    ])), true);

    expect($converted['success'])->toBeTrue()
        ->and($converted['project']['type'])->toBe('freelance')
        ->and(Quote::find($quoteId)->project_id)->toBe($converted['project']['id']);

    $deleted = json_decode((string) freelanceTool($user)->handle(new Request([
        'action' => 'delete_client',
        'client_id' => $clientId,
    ])), true);

    expect($deleted['success'])->toBeTrue();
});

it('queries clients, projects and quotes', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['user_id' => $user->id]);
    Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance', 'client_id' => $client->id]);
    Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);

    $tool = new FreelanceQueryTool($user);

    $clients = json_decode((string) $tool->handle(new Request(['type' => 'clients'])), true);
    $projects = json_decode((string) $tool->handle(new Request(['type' => 'projects'])), true);

    expect($clients['count'])->toBe(1)
        ->and($projects['count'])->toBe(1)
        ->and($projects['records'][0]['type'])->toBe('freelance');
});

it('rejects foreign quote mutations', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $client = Client::factory()->create(['user_id' => $owner->id]);

    $quote = app(FreelanceService::class)->createQuote($owner, [
        'client_id' => $client->id,
        'issue_date' => now()->toDateString(),
        'currency_id' => $currency->id,
        'items' => [],
    ]);

    $result = json_decode((string) freelanceTool($intruder)->handle(new Request([
        'action' => 'delete_quote',
        'quote_id' => $quote->id,
    ])), true);

    expect($result['success'] ?? false)->toBeFalse()
        ->and(Quote::find($quote->id))->not->toBeNull();
});
