<?php

use App\Ai\Enums\AiScope;
use App\Models\AiProvider;
use App\Models\User;
use App\Models\UserAiScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('provider key is encrypted at rest', function () {
    $user = User::factory()->create();
    AiProvider::factory()->for($user)->create(['key' => 'sk-plain']);

    $stored = DB::table('ai_providers')->value('key');
    expect($stored)->not->toBe('sk-plain')
        ->and(decrypt($stored, false))->toBe('sk-plain');
});

test('provider name is unique per user', function () {
    $user = User::factory()->create();
    AiProvider::factory()->for($user)->create(['name' => 'Principal']);

    AiProvider::factory()->for($user)->create(['name' => 'Principal']);
})->throws(QueryException::class);

test('scope chain casts to array and scope values are valid', function () {
    $user = User::factory()->create();
    $scope = UserAiScope::factory()->for($user)->create(['scope' => 'surface:chat', 'provider_chain' => [1, 2]]);

    expect($scope->fresh()->provider_chain)->toBe([1, 2])
        ->and(AiScope::tryFrom('module:people'))->not->toBeNull()
        ->and(AiScope::fromModuleKey('health')->value)->toBe('module:health');
});
