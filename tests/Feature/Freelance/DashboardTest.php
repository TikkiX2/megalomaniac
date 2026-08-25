<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Models\User;

use function Pest\Laravel\actingAs;

test('freelance dashboard is accessible', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('freelance.dashboard'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('freelance/Dashboard')
        );
});
