<?php

use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('authorizes workout actions only for the owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('view', $workout))->toBeTrue()
        ->and($owner->can('update', $workout))->toBeTrue()
        ->and($owner->can('delete', $workout))->toBeTrue()
        ->and($other->can('view', $workout))->toBeFalse()
        ->and($other->can('update', $workout))->toBeFalse()
        ->and($other->can('delete', $workout))->toBeFalse();
});

it('authorizes routine actions only for the owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('view', $routine))->toBeTrue()
        ->and($owner->can('update', $routine))->toBeTrue()
        ->and($owner->can('delete', $routine))->toBeTrue()
        ->and($other->can('view', $routine))->toBeFalse()
        ->and($other->can('update', $routine))->toBeFalse()
        ->and($other->can('delete', $routine))->toBeFalse();
});
