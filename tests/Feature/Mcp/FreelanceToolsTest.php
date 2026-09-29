<?php

use App\Mcp\Servers\MegalomaniacServer;
use App\Mcp\Tools\FreelanceWriteTool;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a client and a freelance project through mcp', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(FreelanceWriteTool::class, ['action' => 'create_client', 'name' => 'Acme'])
        ->assertOk();

    $client = Client::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(FreelanceWriteTool::class, [
            'action' => 'create_project',
            'name' => 'Rediseño',
            'client_id' => $client->id,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('project.type', 'freelance')
            ->etc());

    expect(Project::where('user_id', $user->id)->where('type', 'freelance')->exists())->toBeTrue();
});

it('requires a client to create a freelance project', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(FreelanceWriteTool::class, ['action' => 'create_project', 'name' => 'Sin cliente'])
        ->assertHasErrors(['client']);

    expect(Project::where('user_id', $user->id)->exists())->toBeFalse();
});

it('moves a freelance project to personal and deletes it', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'type' => 'freelance',
        'client_id' => $client->id,
    ]);

    MegalomaniacServer::actingAs($user)
        ->tool(FreelanceWriteTool::class, [
            'action' => 'update_project',
            'project_id' => $project->id,
            'type' => 'personal',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('project.type', 'personal')
            ->etc());

    expect($project->fresh()->type)->toBe('personal');

    MegalomaniacServer::actingAs($user)
        ->tool(FreelanceWriteTool::class, ['action' => 'delete_project', 'project_id' => $project->id])
        ->assertOk();

    expect(Project::withTrashed()->find($project->id)->trashed())->toBeTrue();
});

it('rejects updating a foreign project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);

    MegalomaniacServer::actingAs($intruder)
        ->tool(FreelanceWriteTool::class, [
            'action' => 'update_project',
            'project_id' => $project->id,
            'name' => 'hack',
        ])
        ->assertHasErrors(['not found']);

    expect($project->fresh()->name)->not->toBe('hack');
});

it('rejects creating freelance tasks inside personal projects', function () {
    $user = User::factory()->create();
    $personal = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);

    MegalomaniacServer::actingAs($user)
        ->tool(FreelanceWriteTool::class, [
            'action' => 'create_task',
            'project_id' => $personal->id,
            'name' => 'Tarea mal ubicada',
        ])
        ->assertHasErrors(['freelance module']);

    expect(ProjectTask::where('title', 'Tarea mal ubicada')->exists())->toBeFalse();
});

it('creates and converts quotes through mcp', function () {
    $user = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $client = Client::factory()->create(['user_id' => $user->id]);

    MegalomaniacServer::actingAs($user)
        ->tool(FreelanceWriteTool::class, [
            'action' => 'create_quote',
            'client_id' => $client->id,
            'title' => 'Landing',
            'issue_date' => now()->toDateString(),
            'currency_id' => $currency->id,
            'items' => [['description' => 'Dev', 'subtotal' => 500]],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('quote.total', '500.00')
            ->etc());

    $quote = Quote::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(FreelanceWriteTool::class, ['action' => 'convert_quote', 'quote_id' => $quote->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('project.type', 'freelance')
            ->etc());

    expect($quote->fresh()->status)->toBe('accepted')
        ->and($quote->fresh()->project_id)->not->toBeNull();
});
