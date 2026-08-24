<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Models\User;
use App\Models\Project;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('freelance dashboard is accessible', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('freelance.dashboard'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('freelance/Dashboard')
        );
});
