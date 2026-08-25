<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Models\User;

use function Pest\Laravel\actingAs;

test('clients index is accessible', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('freelance.clients.index'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('freelance/clients/Index')
        );
});

test('client can be created', function () {
    $user = User::factory()->create();

    $clientData = [
        'name' => 'Test Client',
        'email' => 'test@example.com',
        'company' => 'Test Co',
        'is_active' => true,
    ];

    actingAs($user)
        ->post(route('freelance.clients.store'), $clientData)
        ->assertRedirect(route('freelance.clients.index'));

    $this->assertDatabaseHas('clients', [
        'name' => 'Test Client',
        'email' => 'test@example.com',
    ]);
});
