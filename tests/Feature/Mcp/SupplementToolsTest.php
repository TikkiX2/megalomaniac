<?php

use App\Mcp\Servers\MegalomaniacServer;
use App\Mcp\Tools\SupplementReadTool;
use App\Mcp\Tools\SupplementWriteTool;
use App\Models\Supplement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates, logs and reads supplements through mcp', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(SupplementWriteTool::class, [
            'action' => 'create',
            'name' => 'Creatina',
            'stock_quantity' => 3,
            'low_stock_threshold' => 5,
        ])
        ->assertOk();

    $supplement = Supplement::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(SupplementWriteTool::class, ['action' => 'log', 'supplement_id' => $supplement->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('supplement.stock_quantity', 2)
            ->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(SupplementReadTool::class, ['low_stock' => true])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('count', 1)
            ->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(SupplementWriteTool::class, [
            'action' => 'update',
            'supplement_id' => $supplement->id,
            'stock_quantity' => 30,
        ])
        ->assertOk();

    expect($supplement->fresh()->stock_quantity)->toBe(30);

    MegalomaniacServer::actingAs($user)
        ->tool(SupplementWriteTool::class, ['action' => 'delete', 'supplement_id' => $supplement->id])
        ->assertOk();

    expect(Supplement::find($supplement->id))->toBeNull();
});

it('rejects supplement access for other users', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $supplement = Supplement::factory()->create(['user_id' => $owner->id]);

    MegalomaniacServer::actingAs($intruder)
        ->tool(SupplementWriteTool::class, ['action' => 'delete', 'supplement_id' => $supplement->id])
        ->assertHasErrors(['not found']);

    expect(Supplement::find($supplement->id))->not->toBeNull();
});
